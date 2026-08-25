<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Tests\Unit\Service;

use OCA\ProjectManager\Service\CalculationService;
use PHPUnit\Framework\TestCase;

class CalculationServiceTest extends TestCase {
	private CalculationService $calc;

	protected function setUp(): void {
		parent::setUp();
		$this->calc = new CalculationService();
	}

	public function testLargestRemainderSumsToOneHundred(): void {
		// 3 points with 1 leaf each on the same day -> 33/33/34, largest
		// remainder breaks the tie by point order (lowest wins the +1).
		$pct = $this->calc->computePercentages(
			['2026-07-24'],
			['2026-07-24' => [1 => 1, 2 => 1, 3 => 1]],
			[1 => 0, 2 => 1, 3 => 2],
		);

		$day = $pct['2026-07-24'];
		self::assertSame(100, array_sum($day));
		self::assertSame(34, $day[1]);
		self::assertSame(33, $day[2]);
		self::assertSame(33, $day[3]);
	}

	public function testLargestRemainderWithUnevenWeights(): void {
		// Point 1 has 5 leaves, point 2 has 1 leaf -> 83.33% / 16.67%.
		$pct = $this->calc->computePercentages(
			['2026-08-01'],
			['2026-08-01' => [1 => 5, 2 => 1]],
			[1 => 0, 2 => 1],
		);

		$day = $pct['2026-08-01'];
		self::assertSame(100, array_sum($day));
		self::assertSame(83, $day[1]);
		self::assertSame(17, $day[2]);
	}

	public function testDayWithNoLeavesIsOmitted(): void {
		$pct = $this->calc->computePercentages(['2026-08-02'], [], []);
		self::assertArrayNotHasKey('2026-08-02', $pct);
	}

	public function testComputeDoneHours(): void {
		$days = ['2026-07-24', '2026-07-25'];
		$pctByDay = ['2026-07-24' => 50, '2026-07-25' => 100];
		$hoursByDay = ['2026-07-24' => 8.0, '2026-07-25' => 4.0];

		self::assertEqualsWithDelta(8.0, $this->calc->computeDoneHours($days, $pctByDay, $hoursByDay), 0.001);
	}

	public function testRemainingHoursIsNullWithoutEstimate(): void {
		self::assertNull($this->calc->computeRemainingHours(null, 12.0));
	}

	public function testRemainingHoursCanGoNegative(): void {
		self::assertEqualsWithDelta(-2.0, $this->calc->computeRemainingHours(10.0, 12.0), 0.001);
	}

	public function testSumPercentagesForPoints(): void {
		$days = ['2026-07-24'];
		$pctByDay = ['2026-07-24' => [1 => 30, 2 => 70]];

		self::assertSame(['2026-07-24' => 100], $this->calc->sumPercentagesForPoints($days, $pctByDay, [1, 2]));
		self::assertSame(['2026-07-24' => 30], $this->calc->sumPercentagesForPoints($days, $pctByDay, [1]));
	}

	public function testHoursToDays(): void {
		self::assertEqualsWithDelta(2.0, $this->calc->hoursToDays(14.0, 7.0), 0.001);
		self::assertSame(0.0, $this->calc->hoursToDays(14.0, 0.0));
	}
}
