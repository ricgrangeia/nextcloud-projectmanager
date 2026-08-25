<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\Feature;
use OCA\ProjectManager\Db\FeatureMapper;
use OCA\ProjectManager\Db\ProjectMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class FeatureService {
	public function __construct(
		private ProjectMapper $projectMapper,
		private FeatureMapper $featureMapper,
	) {
	}

	/** @return Feature[] */
	public function findAll(int $projectId, string $userId): array {
		$this->projectMapper->find($projectId, $userId);
		return $this->featureMapper->findAllForProject($projectId);
	}

	public function create(int $projectId, string $userId, string $section, string $name, string $pointRef, string $status, string $businessValue, string $externalPending, int $sortOrder = 0): Feature {
		$this->projectMapper->find($projectId, $userId);
		$feature = new Feature();
		$feature->setProjectId($projectId);
		$feature->setSection($section);
		$feature->setName($name);
		$feature->setPointRef($pointRef);
		$feature->setStatus($status);
		$feature->setBusinessValue($businessValue);
		$feature->setExternalPending($externalPending);
		$feature->setSortOrder($sortOrder);
		return $this->featureMapper->insert($feature);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function update(int $id, string $userId, array $fields): Feature {
		$feature = $this->featureMapper->find($id);
		$this->projectMapper->find($feature->getProjectId(), $userId);

		foreach (['section', 'name', 'pointRef', 'status', 'businessValue', 'externalPending', 'sortOrder'] as $field) {
			if (array_key_exists($field, $fields) && $fields[$field] !== null) {
				$setter = 'set' . ucfirst($field);
				$feature->$setter($fields[$field]);
			}
		}

		return $this->featureMapper->update($feature);
	}

	public function delete(int $id, string $userId): void {
		$feature = $this->featureMapper->find($id);
		$this->projectMapper->find($feature->getProjectId(), $userId);
		$this->featureMapper->delete($feature);
	}
}
