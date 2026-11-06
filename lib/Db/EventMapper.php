<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Event>
 */
class EventMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'helfer_events', Event::class);
	}

	/** @throws DoesNotExistException */
	public function find(int $id): Event {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @throws DoesNotExistException */
	public function findByToken(string $token): Event {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('token', $qb->createNamedParameter($token)));
		return $this->findEntity($qb);
	}

	/** @return list<Event> */
	public function findByOwner(string $uid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('owner_uid', $qb->createNamedParameter($uid)))
			->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Events whose end date passed, which have an export folder and were not exported yet.
	 *
	 * @return list<Event>
	 */
	public function findPendingExports(int $now): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->isNotNull('end_date'))
			->andWhere($qb->expr()->lte('end_date', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('export_folder'))
			->andWhere($qb->expr()->neq('export_folder', $qb->createNamedParameter('')))
			->andWhere($qb->expr()->isNull('exported_at'));
		return $this->findEntities($qb);
	}

	/**
	 * Events whose closing date passed but which were not closed yet.
	 *
	 * @return list<Event>
	 */
	public function findPendingClosures(int $now): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->isNotNull('close_date'))
			->andWhere($qb->expr()->lte('close_date', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('closed_at'));
		return $this->findEntities($qb);
	}

	public function deleteByOwner(string $uid): void {
		foreach ($this->findByOwner($uid) as $event) {
			$this->delete($event);
		}
	}
}
