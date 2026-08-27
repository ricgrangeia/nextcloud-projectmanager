<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\ClientMapper;
use OCA\ProjectManager\Db\DayHours;
use OCA\ProjectManager\Db\DayHoursMapper;
use OCA\ProjectManager\Db\Leaf;
use OCA\ProjectManager\Db\LeafMapper;
use OCA\ProjectManager\Db\Module;
use OCA\ProjectManager\Db\ModuleMapper;
use OCA\ProjectManager\Db\Point;
use OCA\ProjectManager\Db\PointMapper;
use OCA\ProjectManager\Db\ProjectMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

/**
 * CRUD for the Module/Point/Leaf/DayHours tracker data, plus assembly of the
 * fully computed project grid (§4 of the spec) via CalculationService.
 */
class TrackerService {
	public function __construct(
		private ProjectMapper $projectMapper,
		private ClientMapper $clientMapper,
		private ModuleMapper $moduleMapper,
		private PointMapper $pointMapper,
		private LeafMapper $leafMapper,
		private DayHoursMapper $dayHoursMapper,
		private CalculationService $calc,
	) {
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function assertProjectOwned(int $projectId, string $userId): void {
		$this->projectMapper->find($projectId, $userId);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function assertModuleOwned(int $moduleId, string $userId): Module {
		$module = $this->moduleMapper->find($moduleId);
		$this->assertProjectOwned($module->getProjectId(), $userId);
		return $module;
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function assertPointOwned(int $pointId, string $userId): Point {
		$point = $this->pointMapper->find($pointId);
		$this->assertModuleOwned($point->getModuleId(), $userId);
		return $point;
	}

	// --- Module -------------------------------------------------------

	public function createModule(int $projectId, string $userId, string $code, string $name, bool $inEstimate, int $sortOrder = 0): Module {
		$this->assertProjectOwned($projectId, $userId);
		$module = new Module();
		$module->setProjectId($projectId);
		$module->setCode($code);
		$module->setName($name);
		$module->setInEstimate($inEstimate);
		$module->setSortOrder($sortOrder);
		return $this->moduleMapper->insert($module);
	}

	public function updateModule(int $moduleId, string $userId, ?string $code, ?string $name, ?bool $inEstimate, ?int $sortOrder): Module {
		$module = $this->assertModuleOwned($moduleId, $userId);
		if ($code !== null) {
			$module->setCode($code);
		}
		if ($name !== null) {
			$module->setName($name);
		}
		if ($inEstimate !== null) {
			$module->setInEstimate($inEstimate);
		}
		if ($sortOrder !== null) {
			$module->setSortOrder($sortOrder);
		}
		return $this->moduleMapper->update($module);
	}

	public function deleteModule(int $moduleId, string $userId): void {
		$module = $this->assertModuleOwned($moduleId, $userId);
		$pointIds = array_map(static fn (Point $p) => $p->getId(), $this->pointMapper->findAllForModule($moduleId));
		$this->leafMapper->deleteAllForPoints($pointIds);
		$this->pointMapper->deleteAllForModules([$moduleId]);
		$this->moduleMapper->delete($module);
	}

	// --- Point ----------------------------------------------------------

	public function createPoint(int $moduleId, string $userId, string $code, string $description, ?float $estimateH, string $status, int $sortOrder = 0): Point {
		$this->assertModuleOwned($moduleId, $userId);
		$point = new Point();
		$point->setModuleId($moduleId);
		$point->setCode($code);
		$point->setDescription($description);
		$point->setEstimateH($estimateH);
		$point->setStatus($status);
		$point->setSortOrder($sortOrder);
		return $this->pointMapper->insert($point);
	}

	public function updatePoint(int $pointId, string $userId, ?string $code, ?string $description, ?float $estimateH, bool $estimateHProvided, ?string $status, ?int $sortOrder): Point {
		$point = $this->assertPointOwned($pointId, $userId);
		if ($code !== null) {
			$point->setCode($code);
		}
		if ($description !== null) {
			$point->setDescription($description);
		}
		if ($estimateHProvided) {
			$point->setEstimateH($estimateH);
		}
		if ($status !== null) {
			$point->setStatus($status);
		}
		if ($sortOrder !== null) {
			$point->setSortOrder($sortOrder);
		}
		return $this->pointMapper->update($point);
	}

	public function deletePoint(int $pointId, string $userId): void {
		$point = $this->assertPointOwned($pointId, $userId);
		$this->leafMapper->deleteAllForPoints([$pointId]);
		$this->pointMapper->delete($point);
	}

	// --- Leaf -------------------------------------------------------------

	public function createLeaf(int $pointId, string $userId, string $description, \DateTimeImmutable $workDate, int $sortOrder = 0): Leaf {
		$this->assertPointOwned($pointId, $userId);
		$leaf = new Leaf();
		$leaf->setPointId($pointId);
		$leaf->setDescription($description);
		$leaf->setWorkDate($workDate);
		$leaf->setSortOrder($sortOrder);
		return $this->leafMapper->insert($leaf);
	}

	public function updateLeaf(int $leafId, string $userId, ?string $description, ?\DateTimeImmutable $workDate, ?int $sortOrder): Leaf {
		$leaf = $this->leafMapper->find($leafId);
		$this->assertPointOwned($leaf->getPointId(), $userId);
		if ($description !== null) {
			$leaf->setDescription($description);
		}
		if ($workDate !== null) {
			$leaf->setWorkDate($workDate);
		}
		if ($sortOrder !== null) {
			$leaf->setSortOrder($sortOrder);
		}
		return $this->leafMapper->update($leaf);
	}

	public function deleteLeaf(int $leafId, string $userId): void {
		$leaf = $this->leafMapper->find($leafId);
		$this->assertPointOwned($leaf->getPointId(), $userId);
		$this->leafMapper->delete($leaf);
	}

	// --- DayHours -----------------------------------------------------------

	/** Create-or-update the hours logged for a given day (upsert on project_id+work_date). */
	public function setDayHours(int $projectId, string $userId, \DateTimeImmutable $workDate, float $hours): DayHours {
		$this->assertProjectOwned($projectId, $userId);
		try {
			$dayHours = $this->dayHoursMapper->findByDate($projectId, $workDate);
			$dayHours->setHours($hours);
			return $this->dayHoursMapper->update($dayHours);
		} catch (DoesNotExistException) {
			$dayHours = new DayHours();
			$dayHours->setProjectId($projectId);
			$dayHours->setWorkDate($workDate);
			$dayHours->setHours($hours);
			return $this->dayHoursMapper->insert($dayHours);
		}
	}

	public function deleteDayHours(int $projectId, string $userId, \DateTimeImmutable $workDate): void {
		$this->assertProjectOwned($projectId, $userId);
		try {
			$dayHours = $this->dayHoursMapper->findByDate($projectId, $workDate);
			$this->dayHoursMapper->delete($dayHours);
		} catch (DoesNotExistException) {
			// nothing to delete
		}
	}

	// --- Grid assembly ----------------------------------------------------

	/**
	 * Assembles the fully computed project grid: modules → points → leaves,
	 * with estimate/done/remaining hours, per-day percentages and the
	 * project-level summary, per §4-§5 of the spec.
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function buildGrid(int $projectId, string $userId): array {
		$project = $this->projectMapper->find($projectId, $userId);
		$modules = $this->moduleMapper->findAllForProject($projectId);
		$moduleIds = array_map(static fn (Module $m) => $m->getId(), $modules);
		$points = $this->pointMapper->findAllForModules($moduleIds);
		$pointIds = array_map(static fn (Point $p) => $p->getId(), $points);
		$leaves = $this->leafMapper->findAllForPoints($pointIds);
		$dayHoursRows = $this->dayHoursMapper->findAllForProject($projectId);

		$hoursByDay = [];
		foreach ($dayHoursRows as $dh) {
			$hoursByDay[$dh->getWorkDate()->format('Y-m-d')] = $dh->getHours();
		}

		$leavesByPoint = [];
		$leafCountsByDayAndPoint = [];
		$daySet = array_flip(array_keys($hoursByDay));
		foreach ($leaves as $leaf) {
			$day = $leaf->getWorkDate()->format('Y-m-d');
			$leavesByPoint[$leaf->getPointId()][] = $leaf;
			$leafCountsByDayAndPoint[$day][$leaf->getPointId()] = ($leafCountsByDayAndPoint[$day][$leaf->getPointId()] ?? 0) + 1;
			$daySet[$day] = true;
		}

		$days = array_keys($daySet);
		sort($days);

		$pointsByModule = [];
		$pointOrder = [];
		foreach ($points as $index => $point) {
			$pointsByModule[$point->getModuleId()][] = $point;
			$pointOrder[$point->getId()] = $index;
		}

		$pctByDay = $this->calc->computePercentages($days, $leafCountsByDayAndPoint, $pointOrder);

		$moduleDtos = [];
		$estimatedInScope = 0.0;
		$doneInScope = 0.0;
		$doneOthers = 0.0;

		foreach ($modules as $module) {
			$modulePoints = $pointsByModule[$module->getId()] ?? [];
			$pointDtos = [];
			$moduleEstimate = 0.0;
			$hasEstimate = false;

			foreach ($modulePoints as $point) {
				$pointPctByDay = [];
				foreach ($days as $day) {
					$pointPctByDay[$day] = $pctByDay[$day][$point->getId()] ?? 0;
				}
				$doneH = $this->calc->computeDoneHours($days, $pointPctByDay, $hoursByDay);
				$estimateH = $point->getEstimateH();
				if ($estimateH !== null) {
					$moduleEstimate += $estimateH;
					$hasEstimate = true;
				}

				$pointDtos[] = [
					'id' => $point->getId(),
					'code' => $point->getCode(),
					'description' => $point->getDescription(),
					'estimateH' => $estimateH !== null ? round($estimateH, 2) : null,
					'doneH' => round($doneH, 2),
					'remainingH' => ($r = $this->calc->computeRemainingHours($estimateH, $doneH)) !== null ? round($r, 2) : null,
					'status' => $point->getStatus(),
					'sortOrder' => $point->getSortOrder(),
					'pctByDay' => $pointPctByDay,
					'leaves' => array_map(static fn (Leaf $l) => [
						'id' => $l->getId(),
						'description' => $l->getDescription(),
						'workDate' => $l->getWorkDate()->format('Y-m-d'),
					], $leavesByPoint[$point->getId()] ?? []),
				];
			}

			$modulePointIds = array_map(static fn (Point $p) => $p->getId(), $modulePoints);
			$modulePctByDay = $this->calc->sumPercentagesForPoints($days, $pctByDay, $modulePointIds);
			$moduleDoneH = $this->calc->computeDoneHours($days, $modulePctByDay, $hoursByDay);

			if ($module->getInEstimate()) {
				$estimatedInScope += $moduleEstimate;
				$doneInScope += $moduleDoneH;
			} else {
				$doneOthers += $moduleDoneH;
			}

			$moduleDtos[] = [
				'id' => $module->getId(),
				'code' => $module->getCode(),
				'name' => $module->getName(),
				'inEstimate' => $module->getInEstimate(),
				'sortOrder' => $module->getSortOrder(),
				'estimateH' => $hasEstimate ? round($moduleEstimate, 2) : null,
				'doneH' => round($moduleDoneH, 2),
				'remainingH' => $hasEstimate ? round($moduleEstimate - $moduleDoneH, 2) : null,
				'pctByDay' => $modulePctByDay,
				'points' => $pointDtos,
			];
		}

		$totalPctByDay = $this->calc->computeTotalPercentagePerDay($days, $pctByDay);
		$doneTotal = $doneInScope + $doneOthers;
		$remainingInScope = $estimatedInScope - $doneInScope;
		$hpd = $project->getHoursPerWorkingDay();

		$client = null;
		if ($project->getClientId() !== null) {
			try {
				$client = $this->clientMapper->find($project->getClientId(), $userId);
			} catch (DoesNotExistException) {
				$client = null;
			}
		}

		// A project's own hourly rate always wins; otherwise it falls back to its
		// client's default rate. Currency, however, always follows the client
		// (when one is set) so that a client's projects can be safely summed.
		$effectiveHourlyRate = $project->getHourlyRate() ?? $client?->getHourlyRate();
		$effectiveCurrencySymbol = $client?->getCurrencySymbol() ?? $project->getCurrencySymbol();

		$costEnabled = $project->getShowCostInSummary() && $effectiveHourlyRate !== null;
		$cost = static fn (float $hours): ?float => $costEnabled ? round($hours * $effectiveHourlyRate, 2) : null;

		return [
			'project' => [
				'id' => $project->getId(),
				'name' => $project->getName(),
				'hoursPerWorkingDay' => $hpd,
				'hourlyRate' => $project->getHourlyRate(),
				'currencySymbol' => $project->getCurrencySymbol(),
				'effectiveHourlyRate' => $effectiveHourlyRate,
				'effectiveCurrencySymbol' => $effectiveCurrencySymbol,
				'showCostInSummary' => $project->getShowCostInSummary(),
				'archived' => $project->getArchived(),
				'clientId' => $project->getClientId(),
			],
			'days' => $days,
			'hoursByDay' => $hoursByDay,
			'modules' => $moduleDtos,
			'totalPctByDay' => $totalPctByDay,
			'summary' => [
				'estimatedH' => round($estimatedInScope, 2),
				'estimatedDays' => round($this->calc->hoursToDays($estimatedInScope, $hpd), 2),
				'estimatedCost' => $cost($estimatedInScope),
				'doneInScopeH' => round($doneInScope, 2),
				'doneInScopeDays' => round($this->calc->hoursToDays($doneInScope, $hpd), 2),
				'doneInScopeCost' => $cost($doneInScope),
				'doneOthersH' => round($doneOthers, 2),
				'doneOthersDays' => round($this->calc->hoursToDays($doneOthers, $hpd), 2),
				'doneOthersCost' => $cost($doneOthers),
				'doneTotalH' => round($doneTotal, 2),
				'doneTotalDays' => round($this->calc->hoursToDays($doneTotal, $hpd), 2),
				'doneTotalCost' => $cost($doneTotal),
				'remainingH' => round($remainingInScope, 2),
				'remainingDays' => round($this->calc->hoursToDays($remainingInScope, $hpd), 2),
				'remainingCost' => $cost($remainingInScope),
				'costEnabled' => $costEnabled,
				'currencySymbol' => $effectiveCurrencySymbol,
			],
		];
	}
}
