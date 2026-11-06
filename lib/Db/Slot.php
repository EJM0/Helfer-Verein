<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * How many helpers a station needs during a shift.
 *
 * @method int getEventId()
 * @method void setEventId(int $eventId)
 * @method int getShiftId()
 * @method void setShiftId(int $shiftId)
 * @method int getStationId()
 * @method void setStationId(int $stationId)
 * @method int getNeeded()
 * @method void setNeeded(int $needed)
 */
class Slot extends Entity implements JsonSerializable {
	protected $eventId = 0;
	protected $shiftId = 0;
	protected $stationId = 0;
	protected $needed = 1;

	public function __construct() {
		$this->addType('eventId', 'integer');
		$this->addType('shiftId', 'integer');
		$this->addType('stationId', 'integer');
		$this->addType('needed', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'eventId' => $this->eventId,
			'shiftId' => $this->shiftId,
			'stationId' => $this->stationId,
			'needed' => $this->needed,
		];
	}
}
