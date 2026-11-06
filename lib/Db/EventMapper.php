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

	/**
	 * @param list<int> $ids
	 * @return list<Event>
	 */
	public function findByIds(array $ids): array {
		if ($ids === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
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
	 * Events with an export folder that need an automatic export:
	 * - once after the end date (final list), and
	 * - with periodic export enabled: whenever something changed since the last export,
	 *   at most every $minInterval seconds (each export creates a new file version).
	 *
	 * @return list<Event>
	 */
	public function findPendingExports(int $now, int $minInterval = 900): array {
		$qb = $this->db->getQueryBuilder();
		$expr = $qb->expr();
		$final = $expr->andX(
			$expr->isNotNull('end_date'),
			$expr->lte('end_date', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)),
			$expr->orX($expr->isNull('exported_at'), $expr->lt('exported_at', 'end_date')),
		);
		$periodic = $expr->andX(
			$expr->eq('export_periodic', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)),
			$expr->orX(
				$expr->isNull('exported_at'),
				$expr->andX(
					$expr->gt('updated_at', 'exported_at'),
					$expr->lte('exported_at', $qb->createNamedParameter($now - $minInterval, IQueryBuilder::PARAM_INT)),
				),
			),
		);
		$qb->select('*')->from($this->getTableName())
			->where($expr->isNotNull('export_folder'))
			->andWhere($expr->neq('export_folder', $qb->createNamedParameter('')))
			->andWhere($expr->orX($final, $periodic));
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
