<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\DayHoursMapper;
use OCA\ProjectManager\Db\FeatureMapper;
use OCA\ProjectManager\Db\LeafMapper;
use OCA\ProjectManager\Db\ModuleMapper;
use OCA\ProjectManager\Db\PointMapper;
use OCA\ProjectManager\Db\Project;
use OCA\ProjectManager\Db\ProjectMapper;
use OCA\ProjectManager\Db\TestEntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class ProjectService {
	public function __construct(
		private ProjectMapper $projectMapper,
		private ModuleMapper $moduleMapper,
		private PointMapper $pointMapper,
		private LeafMapper $leafMapper,
		private DayHoursMapper $dayHoursMapper,
		private FeatureMapper $featureMapper,
		private TestEntryMapper $testEntryMapper,
	) {
	}

	/** @return Project[] */
	public function findAll(string $userId): array {
		return $this->projectMapper->findAllForUser($userId);
	}

	/**
	 * @throws DoesNotExistException if the project does not exist or is not owned by $userId
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id, string $userId): Project {
		return $this->projectMapper->find($id, $userId);
	}

	public function create(string $userId, string $name, float $hoursPerWorkingDay = 7.0): Project {
		$project = new Project();
		$project->setUserId($userId);
		$project->setName($name);
		$project->setHoursPerWorkingDay($hoursPerWorkingDay > 0 ? $hoursPerWorkingDay : 7.0);
		$project->setCreatedAt(new \DateTimeImmutable());
		return $this->projectMapper->insert($project);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function update(
		int $id,
		string $userId,
		?string $name,
		?float $hoursPerWorkingDay,
		?float $hourlyRate = null,
		bool $hourlyRateProvided = false,
		?string $currencySymbol = null,
		?bool $showCostInSummary = null,
	): Project {
		$project = $this->find($id, $userId);
		if ($name !== null) {
			$project->setName($name);
		}
		if ($hoursPerWorkingDay !== null && $hoursPerWorkingDay > 0) {
			$project->setHoursPerWorkingDay($hoursPerWorkingDay);
		}
		if ($hourlyRateProvided) {
			$project->setHourlyRate($hourlyRate);
		}
		if ($currencySymbol !== null) {
			$project->setCurrencySymbol($currencySymbol);
		}
		if ($showCostInSummary !== null) {
			$project->setShowCostInSummary($showCostInSummary);
		}
		return $this->projectMapper->update($project);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function delete(int $id, string $userId): void {
		$project = $this->find($id, $userId);

		$moduleIds = array_map(static fn ($m) => $m->getId(), $this->moduleMapper->findAllForProject($id));
		$pointIds = array_map(static fn ($p) => $p->getId(), $this->pointMapper->findAllForModules($moduleIds));

		$this->leafMapper->deleteAllForPoints($pointIds);
		$this->pointMapper->deleteAllForModules($moduleIds);
		$this->moduleMapper->deleteAllForProject($id);
		$this->dayHoursMapper->deleteAllForProject($id);
		$this->featureMapper->deleteAllForProject($id);
		$this->testEntryMapper->deleteAllForProject($id);

		$this->projectMapper->delete($project);
	}
}
