<?php

declare(strict_types=1);

namespace OCA\Helfer\Listener;

use OCA\Helfer\Service\ShareService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;

/**
 * @template-implements IEventListener<GroupDeletedEvent>
 */
class GroupDeletedListener implements IEventListener {
	public function __construct(
		private ShareService $shares,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof GroupDeletedEvent) {
			$this->shares->removeGroup($event->getGroup()->getGID());
		}
	}
}
