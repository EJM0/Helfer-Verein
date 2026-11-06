<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A survey shared with a Nextcloud user or group.
 *
 * @method int getEventId()
 * @method void setEventId(int $eventId)
 * @method int getShareType()
 * @method void setShareType(int $shareType)
 * @method string getShareWith()
 * @method void setShareWith(string $shareWith)
 * @method string|null getCreatedBy()
 * @method void setCreatedBy(?string $createdBy)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class Share extends Entity {
	public const TYPE_USER = 0;
	public const TYPE_GROUP = 1;

	protected $eventId = 0;
	protected $shareType = self::TYPE_USER;
	protected $shareWith = '';
	protected $createdBy;
	protected $createdAt = 0;

	public function __construct() {
		$this->addType('eventId', 'integer');
		$this->addType('shareType', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
