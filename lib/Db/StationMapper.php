<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Station>
 */
class StationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'helfer_stations', Station::class);
	}

	/** @throws DoesNotExistException */
	public function find(int $id): Station {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return list<Station> */
	public function findByEvent(int $eventId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)))
			->orderBy('sort')->addOrderBy('id');
		return $this->findEntities($qb);
	}

	public function deleteByEvent(int $eventId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
