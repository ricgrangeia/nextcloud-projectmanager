<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\Milestone;
use OCA\ProjectManager\Db\MilestoneMapper;
use OCA\ProjectManager\Db\ProjectMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class MilestoneService {
	public function __construct(
		private ProjectMapper $projectMapper,
		private MilestoneMapper $milestoneMapper,
	) {
	}

	/** @return Milestone[] */
	public function findAll(int $projectId, string $userId): array {
		$this->projectMapper->find($projectId, $userId);
		return $this->milestoneMapper->findAllForProject($projectId);
	}

	public function create(int $projectId, string $userId, string $name, ?\DateTimeImmutable $targetDate, int $sortOrder = 0): Milestone {
		$this->projectMapper->find($projectId, $userId);
		$milestone = new Milestone();
		$milestone->setProjectId($projectId);
		$milestone->setName($name);
		$milestone->setTargetDate($targetDate);
		$milestone->setSortOrder($sortOrder);
		return $this->milestoneMapper->insert($milestone);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function update(int $id, string $userId, array $fields): Milestone {
		$milestone = $this->milestoneMapper->find($id);
		$this->projectMapper->find($milestone->getProjectId(), $userId);

		foreach (['name', 'targetDate', 'reachedDate', 'sortOrder'] as $field) {
			if (array_key_exists($field, $fields) && $fields[$field] !== null) {
				$setter = 'set' . ucfirst($field);
				$milestone->$setter($fields[$field]);
			}
		}

		return $this->milestoneMapper->update($milestone);
	}

	public function delete(int $id, string $userId): void {
		$milestone = $this->milestoneMapper->find($id);
		$this->projectMapper->find($milestone->getProjectId(), $userId);
		$this->milestoneMapper->delete($milestone);
	}
}
