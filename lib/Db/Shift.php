<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A time slot (Schicht) of an event.
 *
 * @method int getEventId()
 * @method void setEventId(int $eventId)
 * @method string|null getTitle()
 * @method void setTitle(?string $title)
 * @method int getStartTime()
 * @method void setStartTime(int $startTime)
 * @method int getEndTime()
 * @method void setEndTime(int $endTime)
 */
class Shift extends Entity implements JsonSerializable {
	protected $eventId = 0;
	protected $title;
	protected $startTime = 0;
	protected $endTime = 0;

	public function __construct() {
		$this->addType('eventId', 'integer');
		$this->addType('startTime', 'integer');
		$this->addType('endTime', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'eventId' => $this->eventId,
			'title' => $this->title ?? '',
			'startTime' => $this->startTime,
			'endTime' => $this->endTime,
		];
	}
}
