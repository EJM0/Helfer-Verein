<?php

declare(strict_types=1);

namespace OCA\Helfer\BackgroundJob;

use OCA\Helfer\Db\EventMapper;
use OCA\Helfer\Service\EventService;
use OCA\Helfer\Service\ExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Exports the helper list after the end date and closes the public link after the closing date.
 *
 * @psalm-suppress UnusedClass
 */
class LifecycleJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private EventMapper $events,
		private EventService $eventService,
		private ExportService $export,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	protected function run($argument): void {
		$this->runOnce($this->time->getTime());
	}

	public function runOnce(int $now): void {
		foreach ($this->events->findPendingExports($now) as $event) {
			try {
				$path = $this->export->exportToFolder($event);
				$this->logger->info('Exported helper list of survey ' . $event->getId() . ' to ' . $path, ['app' => 'helfer']);
			} catch (\Throwable $e) {
				// Remember the attempt so a broken folder does not cause an export every few minutes.
				$this->eventService->markExported($event, '');
				$this->logger->warning('Automatic export of survey ' . $event->getId() . ' failed: ' . $e->getMessage(), [
					'app' => 'helfer',
					'exception' => $e,
				]);
			}
		}
		foreach ($this->events->findPendingClosures($now) as $event) {
			try {
				$this->eventService->close($event);
			} catch (\Throwable $e) {
				$this->logger->error('Closing survey ' . $event->getId() . ' failed', ['app' => 'helfer', 'exception' => $e]);
			}
		}
	}
}
