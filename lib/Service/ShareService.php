<?php

declare(strict_types=1);

namespace OCA\Helfer\Service;

use OCA\Helfer\AppInfo\Application;
use OCA\Helfer\Db\Event;
use OCA\Helfer\Db\Share;
use OCA\Helfer\Db\ShareMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Sharing surveys with other Nextcloud users and groups.
 */
class ShareService {
	public function __construct(
		private ShareMapper $shares,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private ISearch $collaboratorSearch,
		private INotificationManager $notificationManager,
		private ITimeFactory $time,
		private LoggerInterface $logger,
		private IL10N $l,
	) {
	}

	/** @return list<array{id: int, type: string, shareWith: string, displayName: string}> */
	public function list(Event $event): array {
		$result = [];
		foreach ($this->shares->findByEvent($event->getId()) as $share) {
			$result[] = [
				'id' => $share->getId(),
				'type' => $share->getShareType() === Share::TYPE_GROUP ? 'group' : 'user',
				'shareWith' => $share->getShareWith(),
				'displayName' => $this->displayName($share->getShareType(), $share->getShareWith()),
			];
		}
		return $result;
	}

	public function ownerInfo(Event $event): array {
		return [
			'uid' => $event->getOwnerUid(),
			'displayName' => $this->displayName(Share::TYPE_USER, $event->getOwnerUid()),
		];
	}

	/** @throws ValidationException */
	public function add(Event $event, string $type, string $shareWith, string $actorUid): Share {
		$shareType = $type === 'group' ? Share::TYPE_GROUP : Share::TYPE_USER;
		if ($shareType === Share::TYPE_USER) {
			if ($this->userManager->get($shareWith) === null) {
				throw new ValidationException($this->l->t('Benutzer nicht gefunden.'));
			}
			if ($shareWith === $event->getOwnerUid()) {
				throw new ValidationException($this->l->t('Die Umfrage gehört diesem Benutzer bereits.'));
			}
		} elseif (!$this->groupManager->groupExists($shareWith)) {
			throw new ValidationException($this->l->t('Gruppe nicht gefunden.'));
		}
		if ($this->shares->findOne($event->getId(), $shareType, $shareWith) !== null) {
			throw new ValidationException($this->l->t('Die Umfrage ist bereits damit geteilt.'));
		}
		if ($shareType === Share::TYPE_USER) {
			// Sharing directly again brings back somebody who left earlier.
			$left = $this->shares->findOne($event->getId(), Share::TYPE_EXCLUDED_USER, $shareWith);
			if ($left !== null) {
				$this->shares->delete($left);
			}
		}
		$share = new Share();
		$share->setEventId($event->getId());
		$share->setShareType($shareType);
		$share->setShareWith($shareWith);
		$share->setCreatedBy($actorUid);
		$share->setCreatedAt($this->time->getTime());
		$share = $this->shares->insert($share);
		$this->notify($event, $share, $actorUid);
		return $share;
	}

	/** @throws NotFoundException */
	public function remove(Event $event, int $shareId): void {
		try {
			$share = $this->shares->find($shareId);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		if ($share->getEventId() !== $event->getId()) {
			throw new NotFoundException();
		}
		$this->shares->delete($share);
		$this->dismissNotifications($event, $share);
	}

	/**
	 * A shared user removes themselves from a survey: the direct share is deleted and,
	 * if access remains through a group, the user is excluded from it.
	 *
	 * @throws ValidationException
	 */
	public function leave(Event $event, string $uid): void {
		if ($event->getOwnerUid() === $uid) {
			throw new ValidationException($this->l->t('Als Besitzer kannst du die Umfrage nicht verlassen.'));
		}
		$direct = $this->shares->findOne($event->getId(), Share::TYPE_USER, $uid);
		if ($direct !== null) {
			$this->shares->delete($direct);
			$this->dismissNotifications($event, $direct);
		}
		$user = $this->userManager->get($uid);
		$groupIds = $user !== null ? array_values(array_map('strval', $this->groupManager->getUserGroupIds($user))) : [];
		if (in_array($event->getId(), $this->shares->findEventIdsFor($uid, $groupIds), true)) {
			$excluded = new Share();
			$excluded->setEventId($event->getId());
			$excluded->setShareType(Share::TYPE_EXCLUDED_USER);
			$excluded->setShareWith($uid);
			$excluded->setCreatedBy($uid);
			$excluded->setCreatedAt($this->time->getTime());
			$this->shares->insert($excluded);
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)->setUser($uid)->setObject('event', (string)$event->getId());
			$this->notificationManager->markProcessed($notification);
		}
	}

	public function removeGroup(string $gid): void {
		$this->shares->deleteByShareWith(Share::TYPE_GROUP, $gid);
	}

	/**
	 * Search users and groups the current user may share with (respects the sharing settings of the server).
	 *
	 * @return list<array{type: string, shareWith: string, displayName: string}>
	 */
	public function search(string $term, string $uid): array {
		[$result] = $this->collaboratorSearch->search($term, [IShare::TYPE_USER, IShare::TYPE_GROUP], false, 25, 0);
		$found = [];
		foreach (['users' => 'user', 'groups' => 'group'] as $key => $type) {
			$entries = array_merge($result['exact'][$key] ?? [], $result[$key] ?? []);
			foreach ($entries as $entry) {
				$shareWith = (string)($entry['value']['shareWith'] ?? '');
				if ($shareWith === '' || ($type === 'user' && $shareWith === $uid)) {
					continue;
				}
				$found[$type . ':' . $shareWith] = [
					'type' => $type,
					'shareWith' => $shareWith,
					'displayName' => (string)($entry['label'] ?? $shareWith),
				];
			}
		}
		return array_values($found);
	}

	private function displayName(int $type, string $id): string {
		if ($type === Share::TYPE_GROUP) {
			return $this->groupManager->get($id)?->getDisplayName() ?? $id;
		}
		return $this->userManager->getDisplayName($id) ?? $id;
	}

	/** @return list<string> */
	private function recipients(Share $share, string $actorUid): array {
		if ($share->getShareType() === Share::TYPE_USER) {
			return [$share->getShareWith()];
		}
		$group = $this->groupManager->get($share->getShareWith());
		if ($group === null) {
			return [];
		}
		$uids = [];
		foreach ($group->getUsers() as $user) {
			if ($user->getUID() !== $actorUid) {
				$uids[] = $user->getUID();
			}
		}
		return $uids;
	}

	private function notify(Event $event, Share $share, string $actorUid): void {
		try {
			foreach ($this->recipients($share, $actorUid) as $uid) {
				$notification = $this->notificationManager->createNotification();
				$notification->setApp(Application::APP_ID)
					->setUser($uid)
					->setDateTime($this->time->getDateTime())
					->setObject('event', (string)$event->getId())
					->setSubject('shared', ['actor' => $actorUid, 'title' => $event->getTitle()]);
				$this->notificationManager->notify($notification);
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Could not send share notification', ['app' => Application::APP_ID, 'exception' => $e]);
		}
	}

	private function dismissNotifications(Event $event, Share $share): void {
		try {
			foreach ($this->recipients($share, '') as $uid) {
				$notification = $this->notificationManager->createNotification();
				$notification->setApp(Application::APP_ID)
					->setUser($uid)
					->setObject('event', (string)$event->getId());
				$this->notificationManager->markProcessed($notification);
			}
		} catch (\Throwable $e) {
			$this->logger->debug('Could not remove share notification', ['app' => Application::APP_ID, 'exception' => $e]);
		}
	}
}
