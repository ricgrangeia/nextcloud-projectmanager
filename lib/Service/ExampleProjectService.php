<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\Project;

/**
 * Seeds a fully worked example project — modules, points, leaves, day hours,
 * features and tests — so a first-time user can see how the tracker works
 * without having to set anything up.
 */
class ExampleProjectService {
	public function __construct(
		private ProjectService $projectService,
		private TrackerService $trackerService,
		private FeatureService $featureService,
		private TestService $testService,
	) {
	}

	public function create(string $userId): Project {
		$project = $this->projectService->create($userId, 'Example project', 7.0);
		$projectId = $project->getId();

		$today = new \DateTimeImmutable('today');
		$day1 = $today->modify('-4 days');
		$day2 = $today->modify('-2 days');
		$day3 = $today;

		foreach ([$day1, $day2, $day3] as $day) {
			$this->trackerService->setDayHours($projectId, $userId, $day, 7.5);
		}

		$p1 = $this->trackerService->createModule($projectId, $userId, 'P1', 'Structure', true, 0);
		$p11 = $this->trackerService->createPoint($p1->getId(), $userId, 'P1.1', 'Backend multi-company base structure', 20.0, 'in_progress', 0);
		$this->trackerService->createLeaf($p11->getId(), $userId, 'Companies table (migration)', $day1, 0);
		$this->trackerService->createLeaf($p11->getId(), $userId, 'ListAllCompanies endpoint', $day1, 1);
		$this->trackerService->createLeaf($p11->getId(), $userId, 'Multi-company Company aggregate', $day2, 2);
		$this->trackerService->createPoint($p1->getId(), $userId, 'P1.2', 'Frontend company switcher', 8.0, 'todo', 1);

		$p2 = $this->trackerService->createModule($projectId, $userId, 'P2', 'HR', true, 1);
		$p21 = $this->trackerService->createPoint($p2->getId(), $userId, 'P2.1', 'Employee CRUD', 15.0, 'done', 0);
		$this->trackerService->createLeaf($p21->getId(), $userId, 'Employee list + create form', $day2, 0);
		$this->trackerService->createLeaf($p21->getId(), $userId, 'Employee edit/delete', $day3, 1);

		$others = $this->trackerService->createModule($projectId, $userId, 'O', 'OTHERS', false, 2);
		$o1 = $this->trackerService->createPoint($others->getId(), $userId, 'O.1', 'Bug fixes / support', null, 'in_progress', 0);
		$this->trackerService->createLeaf($o1->getId(), $userId, 'Fixed login redirect bug', $day3, 0);

		$this->featureService->create($projectId, $userId, 'ONBOARDING', 'Company switcher', 'P1.1', 'in_progress', 'Lets a user manage several companies from one account', '', 0);
		$this->featureService->create($projectId, $userId, 'HR', 'Employee management', 'P2.1', 'done', 'Core HR record keeping', '', 1);

		$this->testService->create($projectId, $userId, 'Login', 'Regular user', 'User logs in with valid credentials', 'Redirected to the dashboard', 'passed', $day3, '', 0);
		$this->testService->create($projectId, $userId, 'Company switcher', 'Regular user', 'Switch between companies from the header menu', 'The active company changes and data reloads', 'to_test', null, '', 1);

		return $project;
	}
}
