<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A booth / task (Stand, Aufgabe) of an event.
 *
 * @method int getEventId()
 * @method void setEventId(int $eventId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method int getSort()
 * @method void setSort(int $sort)
 */
class Station extends Entity implements JsonSerializable {
	protected $eventId = 0;
	protected $title = '';
	protected $description;
	protected $sort = 0;

	public function __construct() {
		$this->addType('eventId', 'integer');
		$this->addType('sort', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'eventId' => $this->eventId,
			'title' => $this->title,
			'description' => $this->description ?? '',
			'sort' => $this->sort,
		];
	}
}
