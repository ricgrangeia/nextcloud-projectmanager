<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Point> */
class PointMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'pm_points', Point::class);
	}

	/** @return Point[] */
	public function findAllForModule(int $moduleId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('module_id', $qb->createNamedParameter($moduleId, IQueryBuilder::PARAM_INT)))
			->orderBy('sort_order', 'ASC');

		return $this->findEntities($qb);
	}

	/** @return Point[] */
	public function findAllForModules(array $moduleIds): array {
		if ($moduleIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('module_id', $qb->createNamedParameter($moduleIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('sort_order', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function find(int $id): Point {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function deleteAllForModules(array $moduleIds): void {
		if ($moduleIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('module_id', $qb->createNamedParameter($moduleIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}
}
