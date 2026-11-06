<?php

declare(strict_types=1);

namespace OCA\Helfer\Notification;

use OCA\Helfer\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * "X hat die Umfrage Y mit dir geteilt"
 */
class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Helfer');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID || $notification->getSubject() !== 'shared') {
			throw new UnknownNotificationException();
		}
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();
		$actor = (string)($params['actor'] ?? '');
		$title = (string)($params['title'] ?? '');
		$actorName = $this->userManager->getDisplayName($actor) ?? $actor;

		$notification->setRichSubject($l->t('{user} hat die Umfrage „{survey}“ mit dir geteilt'), [
			'user' => ['type' => 'user', 'id' => $actor, 'name' => $actorName],
			'survey' => ['type' => 'highlight', 'id' => $notification->getObjectId(), 'name' => $title],
		]);
		$notification->setParsedSubject($l->t('%1$s hat die Umfrage „%2$s“ mit dir geteilt', [$actorName, $title]));
		$notification->setLink($this->urlGenerator->linkToRouteAbsolute('helfer.page.index') . '#/umfrage/' . $notification->getObjectId());
		$notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')));
		return $notification;
	}
}
