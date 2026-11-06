<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A helper signed up for a slot.
 *
 * @method int getEventId()
 * @method void setEventId(int $eventId)
 * @method int getSlotId()
 * @method void setSlotId(int $slotId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getEmail()
 * @method void setEmail(?string $email)
 * @method string|null getPhone()
 * @method void setPhone(?string $phone)
 * @method string|null getGroupName()
 * @method void setGroupName(?string $groupName)
 * @method string|null getRole()
 * @method void setRole(?string $role)
 * @method string|null getComment()
 * @method void setComment(?string $comment)
 * @method string|null getAnswers()
 * @method void setAnswers(?string $answers)
 * @method string|null getEditToken()
 * @method void setEditToken(?string $editToken)
 * @method string|null getCreatedBy()
 * @method void setCreatedBy(?string $createdBy)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class Signup extends Entity implements JsonSerializable {
	protected $eventId = 0;
	protected $slotId = 0;
	protected $name = '';
	protected $email;
	protected $phone;
	protected $groupName;
	protected $role;
	protected $comment;
	protected $answers;
	protected $editToken;
	protected $createdBy;
	protected $createdAt = 0;

	public function __construct() {
		$this->addType('eventId', 'integer');
		$this->addType('slotId', 'integer');
		$this->addType('createdAt', 'integer');
	}

	/** @return array<string, string> */
	public function getAnswerMap(): array {
		if ($this->answers === null || $this->answers === '') {
			return [];
		}
		$decoded = json_decode($this->answers, true);
		if (!is_array($decoded)) {
			return [];
		}
		$result = [];
		foreach ($decoded as $key => $value) {
			if (is_scalar($value)) {
				$result[(string)$key] = (string)$value;
			}
		}
		return $result;
	}

	/** Full data for the admin view. */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'eventId' => $this->eventId,
			'slotId' => $this->slotId,
			'name' => $this->name,
			'email' => $this->email ?? '',
			'phone' => $this->phone ?? '',
			'groupName' => $this->groupName ?? '',
			'role' => $this->role ?? '',
			'comment' => $this->comment ?? '',
			'answers' => (object)$this->getAnswerMap(),
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
		];
	}

	/** Reduced data for the public view: no contact details. */
	public function toPublicArray(bool $withName): array {
		return [
			'id' => $this->id,
			'slotId' => $this->slotId,
			'name' => $withName ? $this->name : null,
			'role' => $this->role ?? '',
		];
	}
}
