<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\Milestone;
use OCA\ProjectManager\Db\MilestoneMapper;
use OCA\ProjectManager\Db\TestEntry;
use OCA\ProjectManager\Db\TestEntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

/**
 * Builds the "Overview" screen: a read-only, computed briefing of a project meant
 * to answer, at a glance, where things stand — for the user returning to the
 * project, and for an AI assistant resuming it via the API.
 */
class OverviewService {
	public function __construct(
		private TrackerService $trackerService,
		private MilestoneMapper $milestoneMapper,
		private TestEntryMapper $testEntryMapper,
	) {
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function build(int $projectId, string $userId): array {
		$grid = $this->trackerService->buildGrid($projectId, $userId);
		$today = new \DateTimeImmutable('today');

		$allPoints = [];
		$recentLeaves = [];
		foreach ($grid['modules'] as $module) {
			foreach ($module['points'] as $point) {
				$allPoints[] = $point + ['moduleCode' => $module['code'], 'moduleName' => $module['name']];
				foreach ($point['leaves'] as $leaf) {
					$recentLeaves[] = $leaf + ['pointCode' => $point['code'], 'pointDescription' => $point['description']];
				}
			}
		}

		usort($recentLeaves, static fn ($a, $b) => strcmp($b['workDate'], $a['workDate']));
		$recentLeaves = array_slice($recentLeaves, 0, 5);

		$inProgressPoints = array_values(array_filter(
			$allPoints,
			static fn ($p) => in_array($p['status'], ['in_progress', 'partial'], true),
		));

		$presentedPoints = array_values(array_filter($allPoints, static fn ($p) => $p['milestoneId'] !== null));

		$milestoneDtos = array_map(function (Milestone $m) use ($today) {
			$reached = $m->getReachedDate() !== null;
			$daysUntil = $m->getTargetDate() !== null
				? (int) $today->diff($m->getTargetDate())->format('%r%a')
				: null;
			return [
				'id' => $m->getId(),
				'name' => $m->getName(),
				'targetDate' => $m->getTargetDate()?->format('Y-m-d'),
				'reachedDate' => $m->getReachedDate()?->format('Y-m-d'),
				'reached' => $reached,
				'daysUntil' => $daysUntil,
				'overdue' => !$reached && $daysUntil !== null && $daysUntil < 0,
			];
		}, $this->milestoneMapper->findAllForProject($projectId));

		$nextMilestone = null;
		foreach ($milestoneDtos as $m) {
			if (!$m['reached']) {
				$nextMilestone = $m;
				break;
			}
		}

		$pendingPoints = array_values(array_filter(
			$allPoints,
			static fn ($p) => trim((string) $p['externalPending']) !== '',
		));
		$notStartedCount = count(array_filter(
			$allPoints,
			static fn ($p) => trim((string) $p['businessValue']) !== '' && $p['status'] === 'todo',
		));

		$tests = $this->testEntryMapper->findAllForProject($projectId);
		$failedTests = array_values(array_map(static fn (TestEntry $t) => [
			'id' => $t->getId(),
			'area' => $t->getArea(),
			'scenario' => $t->getScenario(),
		], array_filter($tests, static fn (TestEntry $t) => $t->getStatus() === TestEntry::STATUS_FAILED)));
		$toTestCount = count(array_filter($tests, static fn (TestEntry $t) => $t->getStatus() === TestEntry::STATUS_TO_TEST));

		return [
			'project' => $grid['project'],
			'summary' => $grid['summary'],
			'milestones' => $milestoneDtos,
			'nextMilestone' => $nextMilestone,
			'waitingOnClient' => $grid['project']['waitingOnClient'],
			'pendingPoints' => $pendingPoints,
			'recentLeaves' => $recentLeaves,
			'inProgressPoints' => $inProgressPoints,
			'presentedPoints' => $presentedPoints,
			'quality' => [
				'failedTests' => $failedTests,
				'toTestCount' => $toTestCount,
				'notStartedCount' => $notStartedCount,
			],
			'brief' => $grid['project']['brief'],
		];
	}
}
