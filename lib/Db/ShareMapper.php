<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Share>
 */
class ShareMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'helfer_shares', Share::class);
	}

	/** @throws DoesNotExistException */
	public function find(int $id): Share {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return list<Share> */
	public function findByEvent(int $eventId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)))
			->orderBy('share_type')->addOrderBy('share_with');
		return $this->findEntities($qb);
	}

	public function findOne(int $eventId, int $type, string $with): ?Share {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($with)));
		return $this->findEntities($qb)[0] ?? null;
	}

	/**
	 * Ids of events shared with the user directly or with one of the groups.
	 *
	 * @param list<string> $groupIds
	 * @return list<int>
	 */
	public function findEventIdsFor(string $uid, array $groupIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('event_id')->from($this->getTableName());
		$or = [$qb->expr()->andX(
			$qb->expr()->eq('share_type', $qb->createNamedParameter(Share::TYPE_USER, IQueryBuilder::PARAM_INT)),
			$qb->expr()->eq('share_with', $qb->createNamedParameter($uid)),
		)];
		if ($groupIds !== []) {
			$or[] = $qb->expr()->andX(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(Share::TYPE_GROUP, IQueryBuilder::PARAM_INT)),
				$qb->expr()->in('share_with', $qb->createNamedParameter($groupIds, IQueryBuilder::PARAM_STR_ARRAY)),
			);
		}
		$qb->where($qb->expr()->orX(...$or));
		$result = $qb->executeQuery();
		$ids = [];
		while (($row = $result->fetch()) !== false) {
			$ids[] = (int)$row['event_id'];
		}
		$result->closeCursor();
		return $ids;
	}

	public function deleteByEvent(int $eventId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByShareWith(int $type, string $with): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($with)));
		$qb->executeStatement();
	}
}
