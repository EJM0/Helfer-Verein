<?php

declare(strict_types=1);

namespace OCA\Helfer\Service;

use OCA\Helfer\AppInfo\Application;
use OCA\Helfer\Db\Event;
use OCP\IL10N;
use OCP\Mail\Provider\Address;
use OCP\Mail\Provider\IManager as IMailProviderManager;
use OCP\Mail\Provider\IMessageSend;
use OCP\Mail\Provider\IService;
use Psr\Log\LoggerInterface;

/**
 * Sends e-mails to the helpers of a survey through the user's own mail account
 * of the Nextcloud Mail app (mail provider API, Nextcloud 30+).
 */
class HelperMailService {
	public const MAX_RECIPIENTS = 1000;

	public function __construct(
		private EventService $events,
		private IMailProviderManager $mailProviders,
		private LoggerInterface $logger,
		private IL10N $l,
	) {
	}

	/**
	 * Mail accounts of the user that can send messages (e.g. accounts set up in the Mail app).
	 *
	 * @return list<array{providerId: string, serviceId: string, label: string, address: string}>
	 */
	public function accounts(string $uid): array {
		try {
			if (!$this->mailProviders->has()) {
				return [];
			}
			$result = [];
			foreach ($this->mailProviders->services($uid) as $providerId => $services) {
				foreach ($services as $service) {
					if (!$service instanceof IService || !$service instanceof IMessageSend || !$service->capable('MessageSend')) {
						continue;
					}
					$address = (string)$service->getPrimaryAddress()->getAddress();
					if ($address === '') {
						continue;
					}
					$result[] = [
						'providerId' => (string)$providerId,
						'serviceId' => $service->id(),
						'label' => $service->getLabel(),
						'address' => $address,
					];
				}
			}
			return $result;
		} catch (\Throwable $e) {
			$this->logger->warning('Could not list mail accounts', ['app' => Application::APP_ID, 'exception' => $e]);
			return [];
		}
	}

	/**
	 * E-mail addresses of everybody in the survey (helpers and donors), lower-cased => name.
	 *
	 * @return array<string, string>
	 */
	public function recipientsOf(Event $event): array {
		$full = $this->events->getFull($event);
		$result = [];
		foreach ([...$full['signups'], ...$full['donations']] as $entry) {
			$email = trim((string)$entry->getEmail());
			if ($email !== '' && !isset($result[mb_strtolower($email)])) {
				$result[mb_strtolower($email)] = $entry->getName();
			}
		}
		return $result;
	}

	/**
	 * Send one e-mail: the sender is in "To", all helpers in "Bcc" so they do not see each other's address.
	 *
	 * @param list<string> $emails only addresses of this survey are accepted
	 * @return int number of recipients
	 * @throws ValidationException
	 */
	public function send(Event $event, string $uid, string $providerId, string $serviceId, array $emails, string $subject, string $body): int {
		// No line breaks in the subject (header injection).
		$subject = trim(preg_replace('/[\r\n\t]+/', ' ', $subject) ?? '');
		$body = trim($body);
		if ($subject === '' || mb_strlen($subject) > 255) {
			throw new ValidationException($this->l->t('Bitte einen Betreff angeben (höchstens 255 Zeichen).'));
		}
		if ($body === '' || mb_strlen($body) > 50000) {
			throw new ValidationException($this->l->t('Bitte einen Text eingeben.'));
		}

		$known = $this->recipientsOf($event);
		$recipients = [];
		foreach ($emails as $email) {
			$key = mb_strtolower(trim((string)$email));
			if (isset($known[$key])) {
				$recipients[$key] = new Address($key, $known[$key]);
			}
		}
		if ($recipients === []) {
			throw new ValidationException($this->l->t('Bitte mindestens einen Empfänger auswählen.'));
		}
		if (count($recipients) > self::MAX_RECIPIENTS) {
			throw new ValidationException($this->l->t('Zu viele Empfänger auf einmal.'));
		}

		$service = $this->mailProviders->findServiceById($uid, $serviceId, $providerId);
		if (!$service instanceof IMessageSend || !$service->capable('MessageSend')) {
			throw new ValidationException($this->l->t('Das E-Mail-Konto wurde nicht gefunden.'));
		}
		$from = $service->getPrimaryAddress();
		$message = $service->initiateMessage();
		$message->setFrom($from);
		$message->setTo($from);
		$message->setBcc(...array_values($recipients));
		$message->setSubject($subject);
		$message->setBodyPlain($body);
		try {
			$service->sendMessage($message);
		} catch (\Throwable $e) {
			$this->logger->error('Sending helper e-mail failed', ['app' => Application::APP_ID, 'exception' => $e]);
			throw new ValidationException($this->l->t('Die E-Mail konnte nicht gesendet werden. Bitte prüfe dein Konto in der Mail-App.'));
		}
		return count($recipients);
	}
}
