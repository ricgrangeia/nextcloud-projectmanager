<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Feature> */
class FeatureMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'pm_features', Feature::class);
	}

	/** @return Feature[] */
	public function findAllForProject(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->orderBy('section', 'ASC')
			->addOrderBy('sort_order', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function find(int $id): Feature {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function deleteAllForProject(int $projectId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/** Whether any Feature rows still exist, kept only for the one-off Feature→Point data migration. */
	public function hasAny(): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($this->getTableName())->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row !== false;
	}

	/**
	 * @return array<int, Feature[]> features grouped by project_id, kept only for the
	 *         one-off Feature→Point data migration.
	 */
	public function findAllGroupedByProject(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('project_id', 'ASC')
			->addOrderBy('sort_order', 'ASC');

		$grouped = [];
		foreach ($this->findEntities($qb) as $feature) {
			$grouped[$feature->getProjectId()][] = $feature;
		}
		return $grouped;
	}
}
