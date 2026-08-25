<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Leaf> */
class LeafMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'pm_leaves', Leaf::class);
	}

	/** @return Leaf[] */
	public function findAllForPoint(int $pointId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('point_id', $qb->createNamedParameter($pointId, IQueryBuilder::PARAM_INT)))
			->orderBy('work_date', 'ASC')
			->addOrderBy('sort_order', 'ASC');

		return $this->findEntities($qb);
	}

	/** @return Leaf[] */
	public function findAllForPoints(array $pointIds): array {
		if ($pointIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('point_id', $qb->createNamedParameter($pointIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('work_date', 'ASC')
			->addOrderBy('sort_order', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function find(int $id): Leaf {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function deleteAllForPoints(array $pointIds): void {
		if ($pointIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('point_id', $qb->createNamedParameter($pointIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}
}
