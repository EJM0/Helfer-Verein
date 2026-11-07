<?php

declare(strict_types=1);

namespace OCA\Helfer\Service;

use OCA\Helfer\Db\Event;
use OCA\Helfer\Db\EventMapper;
use OCA\Helfer\Db\Signup;
use OCA\Helfer\Db\SignupMapper;
use OCA\Helfer\Db\Slot;
use OCA\Helfer\Db\SlotMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;

class SignupService {
	public function __construct(
		private SignupMapper $signups,
		private SlotMapper $slots,
		private EventMapper $events,
		private ITimeFactory $time,
		private ISecureRandom $random,
		private IMailer $mailer,
		private IDBConnection $db,
		private IL10N $l,
		private ILockingProvider $locking,
	) {
	}

	/**
	 * Run $callback while holding an exclusive lock for the event, so that two people
	 * signing up at the same moment cannot both take the last free place.
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 * @throws ValidationException
	 */
	private function withEventLock(Event $event, callable $callback): mixed {
		$key = 'helfer/event/' . $event->getId();
		$acquired = false;
		for ($i = 0; $i < 50 && !$acquired; $i++) {
			try {
				$this->locking->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
				$acquired = true;
			} catch (LockedException) {
				usleep(100000);
			}
		}
		if (!$acquired) {
			throw new ValidationException($this->l->t('Gerade tragen sich viele gleichzeitig ein. Bitte versuche es gleich noch einmal.'));
		}
		try {
			return $callback();
		} finally {
			$this->locking->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/**
	 * Sign up from the public link. Several slots can be booked at once.
	 *
	 * @param array<array-key, mixed> $slotIds
	 * @return array{signups: list<Signup>, editToken: string}
	 * @throws ValidationException
	 */
	public function publicSignup(Event $event, array $slotIds, array $data, ?string $deviceHash = null): array {
		$this->assertPublicWritable($event);
		$slotIds = array_values(array_unique(array_map(static fn (mixed $v): int => is_numeric($v) ? (int)$v : 0, $slotIds)));
		if ($slotIds === []) {
			throw new ValidationException($this->l->t('Bitte mindestens eine Schicht auswählen.'));
		}
		if (count($slotIds) > 10) {
			throw new ValidationException($this->l->t('Zu viele Schichten auf einmal ausgewählt.'));
		}
		$fields = $this->validateFields($event, $data, false);

		$plainToken = $this->random->generate(32, ISecureRandom::CHAR_ALPHANUMERIC);
		$hashed = hash('sha256', $plainToken);
		$now = $this->time->getTime();

		$created = $this->withEventLock($event, function () use ($event, $slotIds, $fields, $hashed, $deviceHash, $now): array {
			$this->db->beginTransaction();
			try {
				$created = [];
				$existing = $this->signups->findByEvent($event->getId());
				foreach ($slotIds as $slotId) {
					$slot = $this->findSlot($event, $slotId);
					$taken = 0;
					foreach ($existing as $signup) {
						if ($signup->getSlotId() !== $slot->getId()) {
							continue;
						}
						$taken++;
						if (mb_strtolower($signup->getName()) === mb_strtolower($fields['name'])) {
							throw new ValidationException($this->l->t('%s ist in dieser Schicht bereits eingetragen.', [$fields['name']]));
						}
					}
					if ($taken >= $slot->getNeeded()) {
						throw new ValidationException($this->l->t('Eine der ausgewählten Schichten ist leider schon voll. Bitte Seite neu laden.'));
					}
					$signup = new Signup();
					$signup->setEventId($event->getId());
					$signup->setSlotId($slot->getId());
					$this->applyFields($signup, $fields);
					$signup->setEditToken($hashed);
					$signup->setDeviceHash($deviceHash);
					$signup->setCreatedAt($now);
					$created[] = $this->signups->insert($signup);
				}
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
			return $created;
		});
		$this->touch($event);
		return ['signups' => $created, 'editToken' => $plainToken];
	}

	/**
	 * Cancel an own signup from the public link.
	 *
	 * @throws ValidationException|NotFoundException
	 */
	public function publicCancel(Event $event, int $signupId, string $editToken, ?string $deviceHash = null): void {
		$this->assertPublicWritable($event);
		$signup = $this->findSignup($event, $signupId);
		$stored = $signup->getEditToken();
		$byToken = $stored !== null && $editToken !== '' && hash_equals($stored, hash('sha256', $editToken));
		if (!$byToken && !$signup->isFromDevice($deviceHash)) {
			throw new NotFoundException();
		}
		$this->signups->delete($signup);
		$this->touch($event);
	}

	/**
	 * Admin: add a helper. Capacity and dates are not enforced here.
	 *
	 * @throws ValidationException|NotFoundException
	 */
	public function adminAdd(Event $event, int $slotId, array $data, string $uid): Signup {
		$slot = $this->findSlot($event, $slotId);
		$fields = $this->validateFields($event, $data, true);
		$signup = new Signup();
		$signup->setEventId($event->getId());
		$signup->setSlotId($slot->getId());
		$this->applyFields($signup, $fields);
		$signup->setCreatedBy($uid);
		$signup->setCreatedAt($this->time->getTime());
		$signup = $this->signups->insert($signup);
		$this->touch($event);
		return $signup;
	}

	/** @throws ValidationException|NotFoundException */
	public function adminUpdate(Event $event, int $signupId, array $data): Signup {
		$signup = $this->findSignup($event, $signupId);
		if (array_key_exists('slotId', $data)) {
			$signup->setSlotId($this->findSlot($event, (int)$data['slotId'])->getId());
		}
		$merged = array_merge([
			'name' => $signup->getName(),
			'email' => $signup->getEmail(),
			'phone' => $signup->getPhone(),
			'groupName' => $signup->getGroupName(),
			'role' => $signup->getRole(),
			'comment' => $signup->getComment(),
			'answers' => $signup->getAnswerMap(),
		], $data);
		$this->applyFields($signup, $this->validateFields($event, $merged, true));
		$signup = $this->signups->update($signup);
		$this->touch($event);
		return $signup;
	}

	/** @throws NotFoundException */
	public function adminDelete(Event $event, int $signupId): void {
		$this->signups->delete($this->findSignup($event, $signupId));
		$this->touch($event);
	}

	/**
	 * Public changes are only allowed while the link is open and the event has not ended.
	 *
	 * @throws ValidationException
	 */
	public function assertPublicWritable(Event $event): void {
		$now = $this->time->getTime();
		if ($event->isClosed($now)) {
			throw new ValidationException($this->l->t('Diese Umfrage ist geschlossen.'));
		}
		if ($event->isEnded($now)) {
			throw new ValidationException($this->l->t('Die Veranstaltung ist beendet. Eintragungen und Änderungen sind nicht mehr möglich.'));
		}
		if ($event->isSignupClosed($now)) {
			throw new ValidationException($this->l->t('Der Anmeldeschluss ist vorbei. Eintragungen und Änderungen sind nicht mehr möglich.'));
		}
	}

	/** @throws NotFoundException */
	private function findSlot(Event $event, int $slotId): Slot {
		try {
			$slot = $this->slots->find($slotId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		if ($slot->getEventId() !== $event->getId()) {
			throw new NotFoundException();
		}
		return $slot;
	}

	/** @throws NotFoundException */
	private function findSignup(Event $event, int $signupId): Signup {
		try {
			$signup = $this->signups->find($signupId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		if ($signup->getEventId() !== $event->getId()) {
			throw new NotFoundException();
		}
		return $signup;
	}

	/**
	 * @return array{name: string, email: ?string, phone: ?string, groupName: ?string, role: ?string, comment: ?string, answers: array<string, string>}
	 * @throws ValidationException
	 */
	private function validateFields(Event $event, array $data, bool $isAdmin): array {
		$name = $this->clean($data['name'] ?? null, 255);
		if ($name === null) {
			throw new ValidationException($this->l->t('Bitte einen Namen angeben.'));
		}
		$email = $this->clean($data['email'] ?? null, 255);
		if ($email !== null && !$this->mailer->validateMailAddress($email)) {
			throw new ValidationException($this->l->t('Die E-Mail-Adresse ist ungültig.'));
		}
		$phone = $this->clean($data['phone'] ?? null, 64);
		$group = $this->clean($data['groupName'] ?? null, 255);
		if (!$isAdmin) {
			if ($event->getAskEmail() && $event->getEmailRequired() && $email === null) {
				throw new ValidationException($this->l->t('Bitte eine E-Mail-Adresse angeben.'));
			}
			if ($event->getAskGroup()) {
				$options = $event->getGroupOptionList();
				if ($group === null) {
					throw new ValidationException($this->l->t('Bitte eine Gruppe / einen Verein angeben.'));
				}
				if ($options !== [] && !in_array($group, $options, true)) {
					throw new ValidationException($this->l->t('Bitte eine Gruppe aus der Liste auswählen.'));
				}
			}
		}
		if (!$event->getAskEmail() && !$isAdmin) {
			$email = null;
		}
		if (!$event->getAskPhone() && !$isAdmin) {
			$phone = null;
		}
		if (!$event->getAskGroup() && !$isAdmin) {
			$group = null;
		}

		$answers = [];
		$given = is_array($data['answers'] ?? null) ? $data['answers'] : [];
		foreach ($event->getQuestionList() as $question) {
			$value = $this->clean($given[$question['id']] ?? null, 2000);
			if ($value === null) {
				if ($question['required'] && !$isAdmin) {
					throw new ValidationException($this->l->t('Bitte „%s“ beantworten.', [$question['label']]));
				}
				continue;
			}
			$answers[$question['id']] = $value;
		}

		return [
			'name' => $name,
			'email' => $email,
			'phone' => $phone,
			'groupName' => $group,
			'role' => $isAdmin ? $this->clean($data['role'] ?? null, 255) : null,
			'comment' => $this->clean($data['comment'] ?? null, 2000),
			'answers' => $answers,
		];
	}

	private function applyFields(Signup $signup, array $fields): void {
		$signup->setName($fields['name']);
		$signup->setEmail($fields['email']);
		$signup->setPhone($fields['phone']);
		$signup->setGroupName($fields['groupName']);
		$signup->setRole($fields['role']);
		$signup->setComment($fields['comment']);
		$signup->setAnswers($fields['answers'] === [] ? null : json_encode($fields['answers']));
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
