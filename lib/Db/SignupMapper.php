<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Signup>
 */
class SignupMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'helfer_signups', Signup::class);
	}

	/** @throws DoesNotExistException */
	public function find(int $id): Signup {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return list<Signup> */
	public function findByEvent(int $eventId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at')->addOrderBy('id');
		return $this->findEntities($qb);
	}

	public function deleteByEvent(int $eventId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function countBySlot(int $slotId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'cnt'))->from($this->getTableName())
			->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	/** @param list<int> $slotIds */
	public function deleteBySlots(array $slotIds): void {
		if ($slotIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->in('slot_id', $qb->createNamedParameter($slotIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}
}
