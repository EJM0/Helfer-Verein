<?php

declare(strict_types=1);

namespace OCA\Helfer\Service;

use DateTimeZone;
use OCA\Helfer\Db\Event;
use OCA\Helfer\Db\EventMapper;
use OCA\Helfer\Db\Shift;
use OCA\Helfer\Db\ShiftMapper;
use OCA\Helfer\Db\Signup;
use OCA\Helfer\Db\SignupMapper;
use OCA\Helfer\Db\Slot;
use OCA\Helfer\Db\SlotMapper;
use OCA\Helfer\Db\Station;
use OCA\Helfer\Db\StationMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FileNotFoundException;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Security\ISecureRandom;

class EventService {
	public function __construct(
		private EventMapper $events,
		private ShiftMapper $shifts,
		private StationMapper $stations,
		private SlotMapper $slots,
		private SignupMapper $signups,
		private IRootFolder $rootFolder,
		private ISecureRandom $random,
		private ITimeFactory $time,
		private IDBConnection $db,
		private IL10N $l,
	) {
	}

	/** @return list<Event> */
	public function listForUser(string $uid): array {
		return $this->events->findByOwner($uid);
	}

	/**
	 * Summary numbers per event for the overview list.
	 *
	 * @return array{needed: int, filled: int, signups: int}
	 */
	public function getStats(Event $event): array {
		$slots = $this->slots->findByEvent($event->getId());
		$signups = $this->signups->findByEvent($event->getId());
		$perSlot = [];
		foreach ($signups as $signup) {
			$perSlot[$signup->getSlotId()] = ($perSlot[$signup->getSlotId()] ?? 0) + 1;
		}
		$needed = 0;
		$filled = 0;
		foreach ($slots as $slot) {
			$needed += $slot->getNeeded();
			$filled += min($slot->getNeeded(), $perSlot[$slot->getId()] ?? 0);
		}
		return ['needed' => $needed, 'filled' => $filled, 'signups' => count($signups)];
	}

	/** @throws NotFoundException */
	public function getForUser(int $id, string $uid): Event {
		try {
			$event = $this->events->find($id);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		if ($event->getOwnerUid() !== $uid) {
			throw new NotFoundException();
		}
		return $event;
	}

	/** @throws NotFoundException */
	public function getByToken(string $token): Event {
		if ($token === '') {
			throw new NotFoundException();
		}
		try {
			return $this->events->findByToken($token);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
	}

	/**
	 * @return array{event: Event, shifts: list<Shift>, stations: list<Station>, slots: list<Slot>, signups: list<Signup>}
	 */
	public function getFull(Event $event): array {
		$id = $event->getId();
		return [
			'event' => $event,
			'shifts' => $this->shifts->findByEvent($id),
			'stations' => $this->stations->findByEvent($id),
			'slots' => $this->slots->findByEvent($id),
			'signups' => $this->signups->findByEvent($id),
		];
	}

	/** @throws ValidationException */
	public function create(string $uid, array $data): Event {
		$now = $this->time->getTime();
		$event = new Event();
		$event->setOwnerUid($uid);
		$event->setCreatedAt($now);
		$event->setToken($this->generateToken());
		$this->apply($event, $data, true);
		$event->setUpdatedAt($now);
		return $this->events->insert($event);
	}

	/** @throws ValidationException */
	public function update(Event $event, array $data): Event {
		$oldClose = $event->getCloseDate();
		$this->apply($event, $data, false);
		$now = $this->time->getTime();
		// Moving the closing date into the future re-opens the public link
		// (unless it was deleted - then a new one has to be created).
		if ($event->getClosedAt() !== null && $event->getCloseDate() !== $oldClose
			&& ($event->getCloseDate() === null || $event->getCloseDate() > $now)) {
			$event->setClosedAt(null);
		}
		$event->setUpdatedAt($now);
		return $this->events->update($event);
	}

	public function delete(Event $event): void {
		$this->db->beginTransaction();
		try {
			$id = $event->getId();
			$this->signups->deleteByEvent($id);
			$this->slots->deleteByEvent($id);
			$this->shifts->deleteByEvent($id);
			$this->stations->deleteByEvent($id);
			$this->events->delete($event);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function deleteAllForUser(string $uid): void {
		foreach ($this->events->findByOwner($uid) as $event) {
			$this->delete($event);
		}
	}

	/** Create a copy of the event structure (without helpers). */
	public function duplicate(Event $source, string $uid): Event {
		$now = $this->time->getTime();
		$this->db->beginTransaction();
		try {
			$copy = new Event();
			// Dates are not copied: they belong to the old event and would close the copy right away.
			foreach (['title', 'description', 'location', 'contact', 'timezone', 'closeAction',
				'exportFolder', 'askEmail', 'askPhone', 'askGroup', 'groupOptions', 'questions', 'publicNames'] as $field) {
				$copy->{'set' . ucfirst($field)}($source->{'get' . ucfirst($field)}());
			}
			$copy->setTitle($this->l->t('%s (Kopie)', [$source->getTitle()]));
			$copy->setOwnerUid($uid);
			$copy->setToken($this->generateToken());
			$copy->setCreatedAt($now);
			$copy->setUpdatedAt($now);
			$copy = $this->events->insert($copy);

			$shiftMap = [];
			foreach ($this->shifts->findByEvent($source->getId()) as $shift) {
				$new = new Shift();
				$new->setEventId($copy->getId());
				$new->setTitle($shift->getTitle());
				$new->setStartTime($shift->getStartTime());
				$new->setEndTime($shift->getEndTime());
				$shiftMap[$shift->getId()] = $this->shifts->insert($new)->getId();
			}
			$stationMap = [];
			foreach ($this->stations->findByEvent($source->getId()) as $station) {
				$new = new Station();
				$new->setEventId($copy->getId());
				$new->setTitle($station->getTitle());
				$new->setDescription($station->getDescription());
				$new->setSort($station->getSort());
				$stationMap[$station->getId()] = $this->stations->insert($new)->getId();
			}
			foreach ($this->slots->findByEvent($source->getId()) as $slot) {
				if (!isset($shiftMap[$slot->getShiftId()], $stationMap[$slot->getStationId()])) {
					continue;
				}
				$new = new Slot();
				$new->setEventId($copy->getId());
				$new->setShiftId($shiftMap[$slot->getShiftId()]);
				$new->setStationId($stationMap[$slot->getStationId()]);
				$new->setNeeded($slot->getNeeded());
				$this->slots->insert($new);
			}
			$this->db->commit();
			return $copy;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function regenerateToken(Event $event): Event {
		$event->setToken($this->generateToken());
		$now = $this->time->getTime();
		// A fresh link after the closing date only makes sense if the closing date is lifted.
		if ($event->getCloseDate() !== null && $event->getCloseDate() <= $now) {
			$event->setCloseDate(null);
		}
		$event->setClosedAt(null);
		$event->setUpdatedAt($now);
		return $this->events->update($event);
	}

	public function removeToken(Event $event): Event {
		$event->setToken(null);
		$event->setUpdatedAt($this->time->getTime());
		return $this->events->update($event);
	}

	/** Mark the event as closed; deletes the public link if configured. */
	public function close(Event $event): Event {
		$event->setClosedAt($this->time->getTime());
		if ($event->getCloseAction() === Event::CLOSE_ACTION_DELETE) {
			$event->setToken(null);
		}
		return $this->events->update($event);
	}

	public function markExported(Event $event, string $path): Event {
		$event->setExportedAt($this->time->getTime());
		$event->setExportPath($path);
		return $this->events->update($event);
	}

	// ---- shifts -------------------------------------------------------------

	/** @throws ValidationException */
	public function addShift(Event $event, array $data): Shift {
		$shift = new Shift();
		$shift->setEventId($event->getId());
		$this->applyShift($shift, $data);
		$shift = $this->shifts->insert($shift);
		$this->touch($event);
		return $shift;
	}

	/** @throws NotFoundException|ValidationException */
	public function updateShift(Event $event, int $shiftId, array $data): Shift {
		$shift = $this->findShift($event, $shiftId);
		$this->applyShift($shift, $data);
		$shift = $this->shifts->update($shift);
		$this->touch($event);
		return $shift;
	}

	/** @throws NotFoundException */
	public function deleteShift(Event $event, int $shiftId): void {
		$shift = $this->findShift($event, $shiftId);
		$this->deleteSlotsWhere(static fn (Slot $s) => $s->getShiftId() === $shift->getId(), $event);
		$this->shifts->delete($shift);
		$this->touch($event);
	}

	/** @throws NotFoundException */
	private function findShift(Event $event, int $shiftId): Shift {
		try {
			$shift = $this->shifts->find($shiftId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		if ($shift->getEventId() !== $event->getId()) {
			throw new NotFoundException();
		}
		return $shift;
	}

	/** @throws ValidationException */
	private function applyShift(Shift $shift, array $data): void {
		if (array_key_exists('title', $data)) {
			$shift->setTitle($this->cleanString($data['title'], 255));
		}
		if (array_key_exists('startTime', $data)) {
			$shift->setStartTime($this->requireTimestamp($data['startTime'], $this->l->t('Beginn')));
		}
		if (array_key_exists('endTime', $data)) {
			$shift->setEndTime($this->requireTimestamp($data['endTime'], $this->l->t('Ende')));
		}
		if ($shift->getStartTime() <= 0 || $shift->getEndTime() <= 0) {
			throw new ValidationException($this->l->t('Bitte Beginn und Ende der Schicht angeben.'));
		}
		if ($shift->getEndTime() <= $shift->getStartTime()) {
			throw new ValidationException($this->l->t('Das Ende der Schicht muss nach dem Beginn liegen.'));
		}
	}

	// ---- stations -----------------------------------------------------------

	/** @throws ValidationException */
	public function addStation(Event $event, array $data): Station {
		$station = new Station();
		$station->setEventId($event->getId());
		$existing = $this->stations->findByEvent($event->getId());
		$station->setSort(count($existing) > 0 ? max(array_map(static fn (Station $s) => $s->getSort(), $existing)) + 1 : 0);
		$this->applyStation($station, $data);
		$station = $this->stations->insert($station);
		$this->touch($event);
		return $station;
	}

	/** @throws NotFoundException|ValidationException */
	public function updateStation(Event $event, int $stationId, array $data): Station {
		$station = $this->findStation($event, $stationId);
		$this->applyStation($station, $data);
		$station = $this->stations->update($station);
		$this->touch($event);
		return $station;
	}

	/** @throws NotFoundException */
	public function deleteStation(Event $event, int $stationId): void {
		$station = $this->findStation($event, $stationId);
		$this->deleteSlotsWhere(static fn (Slot $s) => $s->getStationId() === $station->getId(), $event);
		$this->stations->delete($station);
		$this->touch($event);
	}

	/** @throws NotFoundException */
	private function findStation(Event $event, int $stationId): Station {
		try {
			$station = $this->stations->find($stationId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		if ($station->getEventId() !== $event->getId()) {
			throw new NotFoundException();
		}
		return $station;
	}

	/** @throws ValidationException */
	private function applyStation(Station $station, array $data): void {
		if (array_key_exists('title', $data)) {
			$station->setTitle($this->cleanString($data['title'], 255) ?? '');
		}
		if (array_key_exists('description', $data)) {
			$station->setDescription($this->cleanString($data['description'], 2000));
		}
		if (array_key_exists('sort', $data) && is_numeric($data['sort'])) {
			$station->setSort((int)$data['sort']);
		}
		if (trim($station->getTitle()) === '') {
			throw new ValidationException($this->l->t('Bitte einen Namen für den Stand / die Aufgabe angeben.'));
		}
	}

	// ---- slots --------------------------------------------------------------

	/**
	 * Set how many helpers a station needs in a shift. 0 removes the slot.
	 *
	 * @throws NotFoundException|ValidationException
	 */
	public function setSlot(Event $event, int $shiftId, int $stationId, int $needed): ?Slot {
		$this->findShift($event, $shiftId);
		$this->findStation($event, $stationId);
		if ($needed < 0 || $needed > 1000) {
			throw new ValidationException($this->l->t('Ungültige Anzahl an Helfern.'));
		}
		$slot = $this->slots->findCell($shiftId, $stationId);
		$this->touch($event);
		if ($needed === 0) {
			if ($slot !== null) {
				if ($this->signups->countBySlot($slot->getId()) > 0) {
					throw new ValidationException($this->l->t('Hier sind bereits Helfer eingetragen. Bitte zuerst die Helfer entfernen.'));
				}
				$this->slots->delete($slot);
			}
			return null;
		}
		if ($slot === null) {
			$slot = new Slot();
			$slot->setEventId($event->getId());
			$slot->setShiftId($shiftId);
			$slot->setStationId($stationId);
			$slot->setNeeded($needed);
			return $this->slots->insert($slot);
		}
		$slot->setNeeded($needed);
		return $this->slots->update($slot);
	}

	/** @param callable(Slot): bool $filter */
	private function deleteSlotsWhere(callable $filter, Event $event): void {
		$ids = [];
		foreach ($this->slots->findByEvent($event->getId()) as $slot) {
			if ($filter($slot)) {
				$ids[] = $slot->getId();
			}
		}
		if ($ids === []) {
			return;
		}
		$this->signups->deleteBySlots($ids);
		foreach ($ids as $id) {
			$this->slots->deleteByColumn('id', $id);
		}
	}

	// ---- helpers ------------------------------------------------------------

	private function touch(Event $event): void {
		$event->setUpdatedAt($this->time->getTime());
		$this->events->update($event);
	}

	private function generateToken(): string {
		return $this->random->generate(16, ISecureRandom::CHAR_HUMAN_READABLE);
	}

	/** @throws ValidationException */
	private function apply(Event $event, array $data, bool $isNew): void {
		if (array_key_exists('title', $data) || $isNew) {
			$title = $this->cleanString($data['title'] ?? '', 255) ?? '';
			if ($title === '') {
				throw new ValidationException($this->l->t('Bitte einen Titel angeben.'));
			}
			$event->setTitle($title);
		}
		foreach (['description' => 10000, 'location' => 255, 'contact' => 255] as $field => $max) {
			if (array_key_exists($field, $data)) {
				$event->{'set' . ucfirst($field)}($this->cleanString($data[$field], $max));
			}
		}
		if (array_key_exists('timezone', $data) && is_string($data['timezone']) && $data['timezone'] !== '') {
			if (!in_array($data['timezone'], DateTimeZone::listIdentifiers(), true) && $data['timezone'] !== 'UTC') {
				throw new ValidationException($this->l->t('Ungültige Zeitzone.'));
			}
			$event->setTimezone($data['timezone']);
		}
		if (array_key_exists('endDate', $data)) {
			$event->setEndDate($this->optionalTimestamp($data['endDate']));
		}
		if (array_key_exists('closeDate', $data)) {
			$event->setCloseDate($this->optionalTimestamp($data['closeDate']));
		}
		if ($event->getEndDate() !== null && $event->getCloseDate() !== null
			&& $event->getCloseDate() < $event->getEndDate()) {
			throw new ValidationException($this->l->t('Das Schließungsdatum darf nicht vor dem Enddatum liegen.'));
		}
		if (array_key_exists('closeAction', $data)) {
			if (!in_array($data['closeAction'], [Event::CLOSE_ACTION_CLOSE, Event::CLOSE_ACTION_DELETE], true)) {
				throw new ValidationException($this->l->t('Ungültige Aktion beim Schließen.'));
			}
			$event->setCloseAction($data['closeAction']);
		}
		if (array_key_exists('exportFolder', $data)) {
			$folder = $this->cleanString($data['exportFolder'], 4000);
			if ($folder !== null) {
				$folder = '/' . trim($folder, '/');
				$this->assertFolderWritable($event->getOwnerUid(), $folder);
			}
			$event->setExportFolder($folder);
		}
		foreach (['askEmail', 'askPhone', 'askGroup', 'publicNames'] as $flag) {
			if (array_key_exists($flag, $data)) {
				$event->{'set' . ucfirst($flag)}(empty($data[$flag]) ? 0 : 1);
			}
		}
		if (array_key_exists('groupOptions', $data)) {
			$options = [];
			if (is_array($data['groupOptions'])) {
				foreach ($data['groupOptions'] as $option) {
					$option = $this->cleanString($option, 255);
					if ($option !== null && !in_array($option, $options, true)) {
						$options[] = $option;
					}
				}
			}
			$event->setGroupOptions($options === [] ? null : json_encode($options));
		}
		if (array_key_exists('questions', $data)) {
			$questions = [];
			if (is_array($data['questions'])) {
				foreach ($data['questions'] as $q) {
					if (!is_array($q)) {
						continue;
					}
					$label = $this->cleanString($q['label'] ?? '', 255);
					if ($label === null) {
						continue;
					}
					$id = is_string($q['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $q['id'])
						? $q['id']
						: $this->random->generate(8, ISecureRandom::CHAR_ALPHANUMERIC);
					$questions[] = ['id' => $id, 'label' => $label, 'required' => !empty($q['required'])];
				}
			}
			$event->setQuestions($questions === [] ? null : json_encode($questions));
		}
	}

	/** @throws ValidationException */
	public function assertFolderWritable(string $uid, string $path): Folder {
		try {
			$node = $this->rootFolder->getUserFolder($uid)->get($path);
		} catch (FileNotFoundException) {
			throw new ValidationException($this->l->t('Der Export-Ordner „%s“ wurde nicht gefunden.', [$path]));
		}
		if (!$node instanceof Folder) {
			throw new ValidationException($this->l->t('„%s“ ist kein Ordner.', [$path]));
		}
		if (!$node->isCreatable()) {
			throw new ValidationException($this->l->t('In den Ordner „%s“ kann nicht geschrieben werden.', [$path]));
		}
		return $node;
	}

	private function cleanString(mixed $value, int $max): ?string {
		if (!is_scalar($value)) {
			return null;
		}
		$value = trim((string)$value);
		if ($value === '') {
			return null;
		}
		return mb_substr($value, 0, $max);
	}

	/** @throws ValidationException */
	private function optionalTimestamp(mixed $value): ?int {
		if ($value === null || $value === '' || $value === 0 || $value === '0') {
			return null;
		}
		if (!is_numeric($value) || (int)$value <= 0) {
			throw new ValidationException($this->l->t('Ungültiges Datum.'));
		}
		return (int)$value;
	}

	/** @throws ValidationException */
	private function requireTimestamp(mixed $value, string $label): int {
		$ts = $this->optionalTimestamp($value);
		if ($ts === null) {
			throw new ValidationException($this->l->t('Bitte %s angeben.', [$label]));
		}
		return $ts;
	}
}
