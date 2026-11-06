<?php

declare(strict_types=1);

namespace OCA\Helfer\Listener;

use OCA\Helfer\Service\EventService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private EventService $events,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$this->events->deleteAllForUser($event->getUser()->getUID());
		}
	}
}
