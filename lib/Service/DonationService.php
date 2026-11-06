<?php

declare(strict_types=1);

namespace OCA\Helfer\Service;

use OCA\Helfer\Db\Donation;
use OCA\Helfer\Db\DonationList;
use OCA\Helfer\Db\DonationListMapper;
use OCA\Helfer\Db\DonationMapper;
use OCA\Helfer\Db\Event;
use OCA\Helfer\Db\EventMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;

/**
 * Donation lists ("Kuchenliste"): people add what they bring.
 */
class DonationService {
	public function __construct(
		private DonationListMapper $lists,
		private DonationMapper $donations,
		private EventMapper $events,
		private SignupService $signups,
		private ITimeFactory $time,
		private ISecureRandom $random,
		private IMailer $mailer,
		private IL10N $l,
	) {
	}

	// ---- lists (admin) ------------------------------------------------------

	/** @throws ValidationException */
	public function addList(Event $event, array $data): DonationList {
		$list = new DonationList();
		$list->setEventId($event->getId());
		$existing = $this->lists->findByEvent($event->getId());
		$list->setSort(count($existing));
		$this->applyList($list, $data);
		$list = $this->lists->insert($list);
		$this->touch($event);
		return $list;
	}

	/** @throws NotFoundException|ValidationException */
	public function updateList(Event $event, int $listId, array $data): DonationList {
		$list = $this->findList($event, $listId);
		$this->applyList($list, $data);
		$list = $this->lists->update($list);
		$this->touch($event);
		return $list;
	}

	/** @throws NotFoundException */
	public function deleteList(Event $event, int $listId): void {
		$list = $this->findList($event, $listId);
		$this->donations->deleteByColumn('list_id', $list->getId());
		$this->lists->delete($list);
		$this->touch($event);
	}

	/** @throws ValidationException */
	private function applyList(DonationList $list, array $data): void {
		if (array_key_exists('title', $data)) {
			$list->setTitle($this->clean($data['title'], 255) ?? '');
		}
		if (array_key_exists('description', $data)) {
			$list->setDescription($this->clean($data['description'], 2000));
		}
		if (array_key_exists('needed', $data)) {
			$needed = $data['needed'];
			if ($needed === null || $needed === '' || $needed === 0 || $needed === '0') {
				$list->setNeeded(null);
			} elseif (is_numeric($needed) && (int)$needed > 0 && (int)$needed <= 10000) {
				$list->setNeeded((int)$needed);
			} else {
				throw new ValidationException($this->l->t('Ungültige Anzahl.'));
			}
		}
		if (trim($list->getTitle()) === '') {
			throw new ValidationException($this->l->t('Bitte einen Namen für die Liste angeben (z. B. Kuchenliste).'));
		}
	}

	// ---- donations ----------------------------------------------------------

	/**
	 * @return array{donation: Donation, editToken: string}
	 * @throws ValidationException|NotFoundException
	 */
	public function publicAdd(Event $event, int $listId, array $data): array {
		$this->signups->assertPublicWritable($event);
		$list = $this->findList($event, $listId);
		$plainToken = $this->random->generate(32, ISecureRandom::CHAR_ALPHANUMERIC);
		$donation = new Donation();
		$donation->setEventId($event->getId());
		$donation->setListId($list->getId());
		$this->applyDonation($event, $donation, $data, false);
		$donation->setEditToken(hash('sha256', $plainToken));
		$donation->setCreatedAt($this->time->getTime());
		return ['donation' => $this->donations->insert($donation), 'editToken' => $plainToken];
	}

	/** @throws ValidationException|NotFoundException */
	public function publicCancel(Event $event, int $donationId, string $editToken): void {
		$this->signups->assertPublicWritable($event);
		$donation = $this->findDonation($event, $donationId);
		$stored = $donation->getEditToken();
		if ($stored === null || $editToken === '' || !hash_equals($stored, hash('sha256', $editToken))) {
			throw new NotFoundException();
		}
		$this->donations->delete($donation);
	}

	/** @throws ValidationException|NotFoundException */
	public function adminAdd(Event $event, int $listId, array $data, string $uid): Donation {
		$list = $this->findList($event, $listId);
		$donation = new Donation();
		$donation->setEventId($event->getId());
		$donation->setListId($list->getId());
		$this->applyDonation($event, $donation, $data, true);
		$donation->setCreatedBy($uid);
		$donation->setCreatedAt($this->time->getTime());
		$donation = $this->donations->insert($donation);
		$this->touch($event);
		return $donation;
	}

	/** @throws ValidationException|NotFoundException */
	public function adminUpdate(Event $event, int $donationId, array $data): Donation {
		$donation = $this->findDonation($event, $donationId);
		if (array_key_exists('listId', $data) && is_numeric($data['listId'])) {
			$donation->setListId($this->findList($event, (int)$data['listId'])->getId());
		}
		$merged = array_merge([
			'item' => $donation->getItem(),
			'name' => $donation->getName(),
			'email' => $donation->getEmail(),
			'comment' => $donation->getComment(),
		], $data);
		$this->applyDonation($event, $donation, $merged, true);
		$donation = $this->donations->update($donation);
		$this->touch($event);
		return $donation;
	}

	/** @throws NotFoundException */
	public function adminDelete(Event $event, int $donationId): void {
		$this->donations->delete($this->findDonation($event, $donationId));
		$this->touch($event);
	}

	/** @throws ValidationException */
	private function applyDonation(Event $event, Donation $donation, array $data, bool $isAdmin): void {
		$item = $this->clean($data['item'] ?? null, 255);
		if ($item === null) {
			throw new ValidationException($this->l->t('Bitte angeben, was du mitbringst.'));
		}
		$name = $this->clean($data['name'] ?? null, 255);
		if ($name === null) {
			throw new ValidationException($this->l->t('Bitte einen Namen angeben.'));
		}
		$email = $this->clean($data['email'] ?? null, 255);
		if ($email !== null && !$this->mailer->validateMailAddress($email)) {
			throw new ValidationException($this->l->t('Die E-Mail-Adresse ist ungültig.'));
		}
		if (!$isAdmin) {
			if (!$event->getAskEmail()) {
				$email = null;
			} elseif ($event->getEmailRequired() && $email === null) {
				throw new ValidationException($this->l->t('Bitte eine E-Mail-Adresse angeben.'));
			}
		}
		$donation->setItem($item);
		$donation->setName($name);
		$donation->setEmail($email);
		$donation->setComment($this->clean($data['comment'] ?? null, 2000));
	}

	/** @throws NotFoundException */
	private function findList(Event $event, int $listId): DonationList {
		try {
			$list = $this->lists->find($listId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		if ($list->getEventId() !== $event->getId()) {
			throw new NotFoundException();
		}
		return $list;
	}

	/** @throws NotFoundException */
	private function findDonation(Event $event, int $donationId): Donation {
		try {
			$donation = $this->donations->find($donationId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		if ($donation->getEventId() !== $event->getId()) {
			throw new NotFoundException();
		}
		return $donation;
	}

	private function touch(Event $event): void {
		$event->setUpdatedAt($this->time->getTime());
		$this->events->update($event);
	}

	private function clean(mixed $value, int $max): ?string {
		if (!is_scalar($value)) {
			return null;
		}
		$value = trim((string)$value);
		return $value === '' ? null : mb_substr($value, 0, $max);
	}
}
