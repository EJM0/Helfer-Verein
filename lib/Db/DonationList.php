<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A list of things people can bring, e.g. a cake list.
 *
 * @method int getEventId()
 * @method void setEventId(int $eventId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method int|null getNeeded()
 * @method void setNeeded(?int $needed)
 * @method int getSort()
 * @method void setSort(int $sort)
 */
class DonationList extends Entity implements JsonSerializable {
	protected $eventId = 0;
	protected $title = '';
	protected $description;
	protected $needed;
	protected $sort = 0;

	public function __construct() {
		$this->addType('eventId', 'integer');
		$this->addType('needed', 'integer');
		$this->addType('sort', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'eventId' => $this->eventId,
			'title' => $this->title,
			'description' => $this->description ?? '',
			'needed' => $this->needed,
			'sort' => $this->sort,
		];
	}
}
