<?php

declare(strict_types=1);

namespace OCA\Helfer\Service;

use OCA\Helfer\Db\Event;
use OCA\Helfer\Db\Shift;
use OCA\Helfer\Db\Station;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Builds iCalendar (.ics) files so helpers can import their shifts into any calendar app.
 */
class CalendarService {
	public const MIME = 'text/calendar; charset=utf-8';

	public function __construct(
		private EventService $events,
		private ITimeFactory $time,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
	) {
	}

	public function fileName(Event $event): string {
		$title = trim(preg_replace('/[^\p{L}\p{N} _-]+/u', '', $event->getTitle()) ?? '');
		return ($title === '' ? 'helfer' : $title) . '.ics';
	}

	/**
	 * @param list<int>|null $slotIds null = all slots of the event
	 * @param array<int, list<string>> $namesBySlot optional helper names per slot to show in the entry
	 */
	public function build(Event $event, ?array $slotIds = null, array $namesBySlot = []): string {
		$full = $this->events->getFull($event);
		/** @var array<int, Shift> $shifts */
		$shifts = [];
		foreach ($full['shifts'] as $shift) {
			$shifts[$shift->getId()] = $shift;
		}
		/** @var array<int, Station> $stations */
		$stations = [];
		foreach ($full['stations'] as $station) {
			$stations[$station->getId()] = $station;
		}
		$host = parse_url($this->urlGenerator->getAbsoluteURL('/'), PHP_URL_HOST) ?: 'nextcloud';
		$publicUrl = $event->getToken() !== null
			? $this->urlGenerator->linkToRouteAbsolute('helfer.public.show', ['token' => $event->getToken()])
			: null;
		$stamp = gmdate('Ymd\THis\Z', $this->time->getTime());

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Nextcloud//Helfer//DE',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . $this->escape($event->getTitle()),
		];
		foreach ($full['slots'] as $slot) {
			if ($slotIds !== null && !in_array($slot->getId(), $slotIds, true)) {
				continue;
			}
			$shift = $shifts[$slot->getShiftId()] ?? null;
			$station = $stations[$slot->getStationId()] ?? null;
			if ($shift === null || $station === null) {
				continue;
			}
			$names = $namesBySlot[$slot->getId()] ?? [];
			$summary = $station->getTitle() . ' – ' . $event->getTitle();
			if ($names !== []) {
				$summary = implode(', ', $names) . ': ' . $summary;
			}
			$description = [];
			if ($shift->getTitle()) {
				$description[] = $this->l->t('Schicht: %s', [$shift->getTitle()]);
			}
			if ($station->getDescription()) {
				$description[] = $station->getDescription();
			}
			if ($event->getContact()) {
				$description[] = $this->l->t('Kontakt: %s', [$event->getContact()]);
			}
			if ($publicUrl !== null) {
				$description[] = $publicUrl;
			}
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:helfer-' . $event->getId() . '-' . $slot->getId() . ($names !== [] ? '-' . substr(md5(implode('|', $names)), 0, 8) : '') . '@' . $host;
			$lines[] = 'DTSTAMP:' . $stamp;
			$lines[] = 'DTSTART:' . gmdate('Ymd\THis\Z', $shift->getStartTime());
			$lines[] = 'DTEND:' . gmdate('Ymd\THis\Z', $shift->getEndTime());
			$lines[] = 'SUMMARY:' . $this->escape($summary);
			$location = $event->getLocation();
			if ($location !== null && $location !== '') {
				$lines[] = 'LOCATION:' . $this->escape($location);
			}
			if ($description !== []) {
				$lines[] = 'DESCRIPTION:' . $this->escape(implode("\n", $description));
			}
			if ($publicUrl !== null) {
				$lines[] = 'URL:' . $publicUrl;
			}
			$lines[] = 'BEGIN:VALARM';
			$lines[] = 'ACTION:DISPLAY';
			$lines[] = 'DESCRIPTION:' . $this->escape($summary);
			$lines[] = 'TRIGGER:-PT1H';
			$lines[] = 'END:VALARM';
			$lines[] = 'END:VEVENT';
		}
		$lines[] = 'END:VCALENDAR';
		return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
	}

	private function escape(string $value): string {
		return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
	}

	/** Lines longer than 75 octets must be folded (RFC 5545 3.1). */
	private function fold(string $line): string {
		if (strlen($line) <= 75) {
			return $line;
		}
		$result = '';
		$current = '';
		foreach (mb_str_split($line) as $char) {
			if (strlen($current) + strlen($char) > ($result === '' ? 75 : 74)) {
				$result .= ($result === '' ? '' : "\r\n ") . $current;
				$current = '';
			}
			$current .= $char;
		}
		return $result . "\r\n " . $current;
	}
}
