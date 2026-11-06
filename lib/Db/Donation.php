<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Something a person brings (e.g. "Muffins" by "Chiara").
 *
 * @method int getEventId()
 * @method void setEventId(int $eventId)
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method string getItem()
 * @method void setItem(string $item)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getEmail()
 * @method void setEmail(?string $email)
 * @method string|null getComment()
 * @method void setComment(?string $comment)
 * @method string|null getEditToken()
 * @method void setEditToken(?string $editToken)
 * @method string|null getCreatedBy()
 * @method void setCreatedBy(?string $createdBy)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class Donation extends Entity implements JsonSerializable {
	protected $eventId = 0;
	protected $listId = 0;
	protected $item = '';
	protected $name = '';
	protected $email;
	protected $comment;
	protected $editToken;
	protected $createdBy;
	protected $createdAt = 0;

	public function __construct() {
		$this->addType('eventId', 'integer');
		$this->addType('listId', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'eventId' => $this->eventId,
			'listId' => $this->listId,
			'item' => $this->item,
			'name' => $this->name,
			'email' => $this->email ?? '',
			'comment' => $this->comment ?? '',
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
		];
	}

	public function toPublicArray(bool $withName): array {
		return [
			'id' => $this->id,
			'listId' => $this->listId,
			'item' => $this->item,
			'name' => $withName ? $this->name : null,
		];
	}
}
