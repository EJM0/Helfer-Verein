<?php

declare(strict_types=1);

namespace OCA\Helfer\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A helper survey ("Umfrage") for one event.
 *
 * @method string getOwnerUid()
 * @method void setOwnerUid(string $uid)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string|null getLocation()
 * @method void setLocation(?string $location)
 * @method string|null getContact()
 * @method void setContact(?string $contact)
 * @method string|null getToken()
 * @method void setToken(?string $token)
 * @method string getTimezone()
 * @method void setTimezone(string $timezone)
 * @method int|null getEndDate()
 * @method void setEndDate(?int $endDate)
 * @method int|null getCloseDate()
 * @method void setCloseDate(?int $closeDate)
 * @method string getCloseAction()
 * @method void setCloseAction(string $closeAction)
 * @method int|null getClosedAt()
 * @method void setClosedAt(?int $closedAt)
 * @method string|null getExportFolder()
 * @method void setExportFolder(?string $exportFolder)
 * @method int|null getExportedAt()
 * @method void setExportedAt(?int $exportedAt)
 * @method string|null getExportPath()
 * @method void setExportPath(?string $exportPath)
 * @method int getAskEmail()
 * @method void setAskEmail(int $askEmail)
 * @method int getAskPhone()
 * @method void setAskPhone(int $askPhone)
 * @method int getAskGroup()
 * @method void setAskGroup(int $askGroup)
 * @method string|null getGroupOptions()
 * @method void setGroupOptions(?string $groupOptions)
 * @method string|null getQuestions()
 * @method void setQuestions(?string $questions)
 * @method int getPublicNames()
 * @method void setPublicNames(int $publicNames)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Event extends Entity implements JsonSerializable {
	public const CLOSE_ACTION_CLOSE = 'close';
	public const CLOSE_ACTION_DELETE = 'delete';

	protected $ownerUid = '';
	protected $title = '';
	protected $description;
	protected $location;
	protected $contact;
	protected $token;
	protected $timezone = 'UTC';
	protected $endDate;
	protected $closeDate;
	protected $closeAction = self::CLOSE_ACTION_CLOSE;
	protected $closedAt;
	protected $exportFolder;
	protected $exportedAt;
	protected $exportPath;
	protected $askEmail = 1;
	protected $askPhone = 0;
	protected $askGroup = 0;
	protected $groupOptions;
	protected $questions;
	protected $publicNames = 1;
	protected $createdAt = 0;
	protected $updatedAt = 0;

	public function __construct() {
		$this->addType('endDate', 'integer');
		$this->addType('closeDate', 'integer');
		$this->addType('closedAt', 'integer');
		$this->addType('exportedAt', 'integer');
		$this->addType('askEmail', 'integer');
		$this->addType('askPhone', 'integer');
		$this->addType('askGroup', 'integer');
		$this->addType('publicNames', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	/** After the end date the public can no longer sign up or change anything. */
	public function isEnded(?int $now = null): bool {
		$now ??= time();
		return $this->endDate !== null && $now >= $this->endDate;
	}

	/** After the closing date the public link is closed (or deleted). */
	public function isClosed(?int $now = null): bool {
		$now ??= time();
		return $this->closedAt !== null || ($this->closeDate !== null && $now >= $this->closeDate);
	}

	/** @return list<string> */
	public function getGroupOptionList(): array {
		return self::decodeList($this->groupOptions);
	}

	/** @return list<array{id: string, label: string, required: bool}> */
	public function getQuestionList(): array {
		if ($this->questions === null || $this->questions === '') {
			return [];
		}
		$decoded = json_decode($this->questions, true);
		if (!is_array($decoded)) {
			return [];
		}
		$result = [];
		foreach ($decoded as $q) {
			if (is_array($q) && isset($q['id'], $q['label'])) {
				$result[] = [
					'id' => (string)$q['id'],
					'label' => (string)$q['label'],
					'required' => !empty($q['required']),
				];
			}
		}
		return $result;
	}

	/** @return list<string> */
	private static function decodeList(?string $json): array {
		if ($json === null || $json === '') {
			return [];
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return [];
		}
		return array_values(array_map('strval', array_filter($decoded, 'is_scalar')));
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'ownerUid' => $this->ownerUid,
			'title' => $this->title,
			'description' => $this->description ?? '',
			'location' => $this->location ?? '',
			'contact' => $this->contact ?? '',
			'token' => $this->token,
			'timezone' => $this->timezone,
			'endDate' => $this->endDate,
			'closeDate' => $this->closeDate,
			'closeAction' => $this->closeAction,
			'closedAt' => $this->closedAt,
			'exportFolder' => $this->exportFolder,
			'exportedAt' => $this->exportedAt,
			'exportPath' => $this->exportPath,
			'askEmail' => (bool)$this->askEmail,
			'askPhone' => (bool)$this->askPhone,
			'askGroup' => (bool)$this->askGroup,
			'groupOptions' => $this->getGroupOptionList(),
			'questions' => $this->getQuestionList(),
			'publicNames' => (bool)$this->publicNames,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
			'ended' => $this->isEnded(),
			'closed' => $this->isClosed(),
		];
	}
}
