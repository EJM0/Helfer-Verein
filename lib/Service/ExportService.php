<?php

declare(strict_types=1);

namespace OCA\Helfer\Service;

use DateTimeZone;
use OCA\Helfer\Db\Event;
use OCA\Helfer\Db\Shift;
use OCA\Helfer\Db\Station;
use OCA\Helfer\Export\Cell;
use OCA\Helfer\Export\XlsxWriter;
use OCP\Files\File;
use OCP\IL10N;
use OCP\ITempManager;

/**
 * Builds the helper list workbook and stores it in the owner's files.
 */
class ExportService {
	public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

	public function __construct(
		private EventService $eventService,
		private ITempManager $tempManager,
		private IL10N $l,
	) {
	}

	public function fileName(Event $event): string {
		$title = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/', ' ', $event->getTitle()) ?? '';
		$title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
		if ($title === '' || $title === '.' || $title === '..') {
			$title = (string)$event->getId();
		}
		return mb_substr($this->l->t('Helferliste %s', [$title]), 0, 200) . '.xlsx';
	}

	/** Returns the xlsx file content. */
	public function build(Event $event): string {
		$full = $this->eventService->getFull($event);
		try {
			$tz = new DateTimeZone($event->getTimezone() ?: 'UTC');
		} catch (\Exception) {
			$tz = new DateTimeZone('UTC');
		}

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
		$slots = [];
		foreach ($full['slots'] as $slot) {
			if (isset($shifts[$slot->getShiftId()], $stations[$slot->getStationId()])) {
				$slots[$slot->getId()] = $slot;
			}
		}
		$questions = $event->getQuestionList();

		// One entry per signup, sorted by time, station and name.
		$entries = [];
		foreach ($full['signups'] as $signup) {
			$slot = $slots[$signup->getSlotId()] ?? null;
			if ($slot === null) {
				continue;
			}
			$entries[] = [
				'signup' => $signup,
				'shift' => $shifts[$slot->getShiftId()],
				'station' => $stations[$slot->getStationId()],
			];
		}
		usort($entries, static function (array $a, array $b): int {
			return [$a['shift']->getStartTime(), $a['station']->getSort(), $a['station']->getId(), mb_strtolower($a['signup']->getName())]
				<=> [$b['shift']->getStartTime(), $b['station']->getSort(), $b['station']->getId(), mb_strtolower($b['signup']->getName())];
		});

		// Sheet 2: name and time of every assignment.
		$header = [
			$this->l->t('Name'),
			$this->l->t('Datum'),
			$this->l->t('Von'),
			$this->l->t('Bis'),
			$this->l->t('Stunden'),
			$this->l->t('Schicht'),
			$this->l->t('Stand / Aufgabe'),
			$this->l->t('Rolle'),
			$this->l->t('E-Mail'),
			$this->l->t('Telefon'),
			$this->l->t('Gruppe / Verein'),
			$this->l->t('Kommentar'),
		];
		foreach ($questions as $question) {
			$header[] = $question['label'];
		}
		$assignments = [$header];

		// Sheet 1: who helped (one row per person).
		$people = [];
		foreach ($entries as $entry) {
			$signup = $entry['signup'];
			/** @var Shift $shift */
			$shift = $entry['shift'];
			$hours = round(($shift->getEndTime() - $shift->getStartTime()) / 3600, 2);
			$answers = $signup->getAnswerMap();
			$row = [
				$signup->getName(),
				new Cell(floor(XlsxWriter::excelDate($shift->getStartTime(), $tz)), XlsxWriter::STYLE_DATE),
				new Cell(XlsxWriter::excelDate($shift->getStartTime(), $tz), XlsxWriter::STYLE_TIME),
				new Cell(XlsxWriter::excelDate($shift->getEndTime(), $tz), XlsxWriter::STYLE_TIME),
				new Cell($hours, XlsxWriter::STYLE_DECIMAL),
				$shift->getTitle() ?? '',
				$entry['station']->getTitle(),
				$signup->getRole() ?? '',
				$signup->getEmail() ?? '',
				$signup->getPhone() ?? '',
				$signup->getGroupName() ?? '',
				$signup->getComment() ?? '',
			];
			foreach ($questions as $question) {
				$row[] = $answers[$question['id']] ?? '';
			}
			$assignments[] = $row;

			$key = mb_strtolower(trim($signup->getName())) . '|' . mb_strtolower(trim($signup->getEmail() ?? ''));
			if (!isset($people[$key])) {
				$people[$key] = [
					'name' => $signup->getName(),
					'email' => $signup->getEmail() ?? '',
					'phone' => $signup->getPhone() ?? '',
					'group' => $signup->getGroupName() ?? '',
					'count' => 0,
					'hours' => 0.0,
					'stations' => [],
				];
			}
			$people[$key]['count']++;
			$people[$key]['hours'] += $hours;
			$people[$key]['stations'][$entry['station']->getTitle()] = true;
			if ($people[$key]['phone'] === '' && $signup->getPhone()) {
				$people[$key]['phone'] = $signup->getPhone();
			}
			if ($people[$key]['group'] === '' && $signup->getGroupName()) {
				$people[$key]['group'] = $signup->getGroupName();
			}
		}
		uasort($people, static fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

		$helpers = [[
			$this->l->t('Name'),
			$this->l->t('E-Mail'),
			$this->l->t('Telefon'),
			$this->l->t('Gruppe / Verein'),
			$this->l->t('Einsätze'),
			$this->l->t('Stunden gesamt'),
			$this->l->t('Stände / Aufgaben'),
		]];
		foreach ($people as $person) {
			$helpers[] = [
				$person['name'],
				$person['email'],
				$person['phone'],
				$person['group'],
				$person['count'],
				new Cell(round($person['hours'], 2), XlsxWriter::STYLE_DECIMAL),
				implode(', ', array_keys($person['stations'])),
			];
		}

		$writer = new XlsxWriter();
		$writer->addSheet($this->l->t('Helfer'), $helpers);
		$writer->addSheet($this->l->t('Einsätze'), $assignments);
		if ($full['donationLists'] !== []) {
			$listTitles = [];
			foreach ($full['donationLists'] as $list) {
				$listTitles[$list->getId()] = $list->getTitle();
			}
			$donations = [[
				$this->l->t('Liste'),
				$this->l->t('Spende'),
				$this->l->t('Name'),
				$this->l->t('E-Mail'),
				$this->l->t('Kommentar'),
			]];
			foreach ($full['donations'] as $donation) {
				if (!isset($listTitles[$donation->getListId()])) {
					continue;
				}
				$donations[] = [
					$listTitles[$donation->getListId()],
					$donation->getItem(),
					$donation->getName(),
					$donation->getEmail() ?? '',
					$donation->getComment() ?? '',
				];
			}
			$writer->addSheet($this->l->t('Spenden'), $donations);
		}
		$temp = $this->tempManager->getTemporaryFile('.xlsx');
		if ($temp === false) {
			throw new \RuntimeException('Could not create temporary file');
		}
		try {
			return $writer->toString($temp);
		} finally {
			@unlink($temp);
		}
	}

	/**
	 * Write the export into a folder of the event owner.
	 *
	 * @return string path of the written file relative to the user's home
	 * @throws ValidationException
	 */
	public function exportToFolder(Event $event, ?string $folderPath = null): string {
		$folderPath ??= $event->getExportFolder();
		if ($folderPath === null || $folderPath === '') {
			throw new ValidationException($this->l->t('Bitte einen Export-Ordner auswählen.'));
		}
		$folderPath = '/' . trim($folderPath, '/');
		$folder = $this->eventService->assertFolderWritable($event->getOwnerUid(), $folderPath);
		$content = $this->build($event);
		$name = $this->fileName($event);
		if ($folder->nodeExists($name)) {
			$file = $folder->get($name);
			if (!$file instanceof File) {
				throw new ValidationException($this->l->t('„%s“ existiert bereits und ist keine Datei.', [$name]));
			}
			$file->putContent($content);
		} else {
			$file = $folder->newFile($name, $content);
		}
		$path = rtrim($folderPath, '/') . '/' . $name;
		$this->eventService->markExported($event, $path);
		return $path;
	}
}
