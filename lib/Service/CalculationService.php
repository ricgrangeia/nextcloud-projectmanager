<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

/**
 * Pure calculation engine for the project tracker.
 *
 * Mirrors the "largest remainder" percentage split and hour aggregation of the
 * original Python/Excel generator exactly. Contains no I/O and no framework
 * dependencies so it can be unit tested in isolation.
 */
class CalculationService {
	/**
	 * Splits 100% across the points that have leaves on each day, weighted by
	 * leaf count, using the largest-remainder method so each day sums to
	 * exactly 100 (when it has any leaves at all).
	 *
	 * @param string[] $days ordered list of day keys (e.g. "2026-07-24")
	 * @param array<string, array<int, int>> $leafCountsByDayAndPoint day => [pointId => leafCount]
	 * @param array<int, int> $pointOrder pointId => stable sort order, used as a tie-break
	 * @return array<string, array<int, int>> day => [pointId => integer percentage]
	 */
	public function computePercentages(array $days, array $leafCountsByDayAndPoint, array $pointOrder): array {
		$pctByDay = [];

		foreach ($days as $day) {
			$counts = $leafCountsByDayAndPoint[$day] ?? [];
			$total = array_sum($counts);
			if ($total <= 0) {
				continue;
			}

			$raw = [];
			$base = [];
			foreach ($counts as $pointId => $count) {
				$value = $count / $total * 100;
				$raw[$pointId] = $value;
				$base[$pointId] = (int) floor($value);
			}

			$remainder = 100 - array_sum($base);
			$pointIds = array_keys($counts);
			usort($pointIds, function (int $a, int $b) use ($raw, $pointOrder): int {
				$fracA = $raw[$a] - floor($raw[$a]);
				$fracB = $raw[$b] - floor($raw[$b]);
				if (abs($fracA - $fracB) > 1e-9) {
					return $fracB <=> $fracA;
				}
				$orderA = $pointOrder[$a] ?? $a;
				$orderB = $pointOrder[$b] ?? $b;
				if ($orderA !== $orderB) {
					return $orderA <=> $orderB;
				}
				return $a <=> $b;
			});

			for ($i = 0; $i < $remainder; $i++) {
				$base[$pointIds[$i]] += 1;
			}

			$pctByDay[$day] = $base;
		}

		return $pctByDay;
	}

	/**
	 * Aggregates the per-day percentage of a set of points (e.g. all points of
	 * a module) into a single per-day percentage.
	 *
	 * @param string[] $days
	 * @param array<string, array<int, int>> $pctByDay day => [pointId => pct]
	 * @param int[] $pointIds
	 * @return array<string, int> day => summed percentage
	 */
	public function sumPercentagesForPoints(array $days, array $pctByDay, array $pointIds): array {
		$result = [];
		$pointIdSet = array_flip($pointIds);
		foreach ($days as $day) {
			$sum = 0;
			foreach (($pctByDay[$day] ?? []) as $pointId => $pct) {
				if (isset($pointIdSet[$pointId])) {
					$sum += $pct;
				}
			}
			$result[$day] = $sum;
		}
		return $result;
	}

	/**
	 * Done(h) = Σ_days ( pct[day] / 100 × hours[day] ), for a single entity's
	 * per-day percentage series (a point, or the summed series of a module).
	 *
	 * @param string[] $days
	 * @param array<string, int|float> $pctByDay day => percentage
	 * @param array<string, float> $hoursByDay day => actual hours worked
	 */
	public function computeDoneHours(array $days, array $pctByDay, array $hoursByDay): float {
		$done = 0.0;
		foreach ($days as $day) {
			$pct = $pctByDay[$day] ?? 0;
			if ($pct <= 0) {
				continue;
			}
			$hours = $hoursByDay[$day] ?? 0.0;
			$done += $pct / 100 * $hours;
		}
		return $done;
	}

	/**
	 * Remaining(h) = estimate(h) − done(h). Returns null when there is no
	 * estimate (e.g. an OTHERS point) since "remaining" is meaningless there.
	 */
	public function computeRemainingHours(?float $estimateH, float $doneH): ?float {
		if ($estimateH === null) {
			return null;
		}
		return $estimateH - $doneH;
	}

	/**
	 * Total percentage per day across ALL points of a project — should read
	 * ~100 on any day that has leaves logged.
	 *
	 * @param string[] $days
	 * @param array<string, array<int, int>> $pctByDay day => [pointId => pct]
	 * @return array<string, int> day => total percentage
	 */
	public function computeTotalPercentagePerDay(array $days, array $pctByDay): array {
		$result = [];
		foreach ($days as $day) {
			$result[$day] = array_sum($pctByDay[$day] ?? []);
		}
		return $result;
	}

	/** 1 day = hoursPerWorkingDay hours. */
	public function hoursToDays(float $hours, float $hoursPerWorkingDay): float {
		if ($hoursPerWorkingDay <= 0) {
			return 0.0;
		}
		return $hours / $hoursPerWorkingDay;
	}
}
