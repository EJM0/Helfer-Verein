<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Slot>
 */
class SlotMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'helfer_slots', Slot::class);
	}

	/** @throws DoesNotExistException */
	public function find(int $id): Slot {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return list<Slot> */
	public function findByEvent(int $eventId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)))
			->orderBy('id');
		return $this->findEntities($qb);
	}

	public function deleteByEvent(int $eventId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function findCell(int $shiftId, int $stationId): ?Slot {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('shift_id', $qb->createNamedParameter($shiftId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('station_id', $qb->createNamedParameter($stationId, IQueryBuilder::PARAM_INT)));
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	public function deleteByColumn(string $column, int $value): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
