<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\ProjectMapper;
use OCA\ProjectManager\Db\TestEntry;
use OCA\ProjectManager\Db\TestEntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class TestService {
	public function __construct(
		private ProjectMapper $projectMapper,
		private TestEntryMapper $testEntryMapper,
	) {
	}

	/** @return TestEntry[] */
	public function findAll(int $projectId, string $userId): array {
		$this->projectMapper->find($projectId, $userId);
		return $this->testEntryMapper->findAllForProject($projectId);
	}

	public function create(int $projectId, string $userId, string $area, string $profile, string $scenario, string $expected, string $status, ?\DateTimeImmutable $testDate, string $notes, int $sortOrder = 0): TestEntry {
		$this->projectMapper->find($projectId, $userId);
		$test = new TestEntry();
		$test->setProjectId($projectId);
		$test->setArea($area);
		$test->setProfile($profile);
		$test->setScenario($scenario);
		$test->setExpected($expected);
		$test->setStatus($status);
		$test->setTestDate($testDate);
		$test->setNotes($notes);
		$test->setSortOrder($sortOrder);
		return $this->testEntryMapper->insert($test);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function update(int $id, string $userId, array $fields): TestEntry {
		$test = $this->testEntryMapper->find($id);
		$this->projectMapper->find($test->getProjectId(), $userId);

		foreach (['area', 'profile', 'scenario', 'expected', 'status', 'testDate', 'notes', 'sortOrder'] as $field) {
			if (array_key_exists($field, $fields) && $fields[$field] !== null) {
				$setter = 'set' . ucfirst($field);
				$test->$setter($fields[$field]);
			}
		}

		return $this->testEntryMapper->update($test);
	}

	public function delete(int $id, string $userId): void {
		$test = $this->testEntryMapper->find($id);
		$this->projectMapper->find($test->getProjectId(), $userId);
		$this->testEntryMapper->delete($test);
	}
}
