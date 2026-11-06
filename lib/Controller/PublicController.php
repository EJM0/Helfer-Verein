<?php

declare(strict_types=1);

namespace OCA\Helfer\Controller;

use OCA\Helfer\AppInfo\Application;
use OCA\Helfer\Db\Event;
use OCA\Helfer\Service\CalendarService;
use OCA\Helfer\Service\DonationService;
use OCA\Helfer\Service\EventService;
use OCA\Helfer\Service\NotFoundException;
use OCA\Helfer\Service\SignupService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\Security\ISecureRandom;

/**
 * The public page behind the shared link (normal helper view).
 *
 * @psalm-suppress UnusedClass
 */
class PublicController extends Controller {
	use Errors;

	public function __construct(
		IRequest $request,
		private EventService $events,
		private SignupService $signups,
		private DonationService $donations,
		private CalendarService $calendar,
		private IInitialState $initialState,
		private ITimeFactory $time,
		private ISecureRandom $random,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/** Cookie that identifies this browser, so own entries stay manageable after a reload. */
	private const DEVICE_COOKIE = 'helfer_device';

	private ?string $deviceToken = null;

	private function deviceToken(): string {
		if ($this->deviceToken === null) {
			$cookie = $this->request->getCookie(self::DEVICE_COOKIE);
			if (is_string($cookie) && preg_match('/^[A-Za-z0-9]{40}$/', $cookie) === 1) {
				$this->deviceToken = $cookie;
			} else {
				$this->deviceToken = $this->random->generate(40, ISecureRandom::CHAR_ALPHANUMERIC);
			}
		}
		return $this->deviceToken;
	}

	private function deviceHash(): string {
		return hash('sha256', $this->deviceToken());
	}

	/**
	 * Set (or renew) the device cookie: it is valid for one year after the last visit.
	 *
	 * @template T of Response
	 * @param T $response
	 * @return T
	 */
	private function withDeviceCookie(Response $response): Response {
		$expires = $this->time->getDateTime('+1 year');
		$response->addCookie(self::DEVICE_COOKIE, $this->deviceToken(), $expires, 'Lax');
		return $response;
	}

	/**
	 * Resolve a token. When the link was set to be deleted on closing, a closed event is gone for the public.
	 *
	 * @throws NotFoundException
	 */
	private function resolve(string $token): Event {
		$event = $this->events->getByToken($token);
		if ($event->getCloseAction() === Event::CLOSE_ACTION_DELETE && $event->isClosed($this->time->getTime())) {
			throw new NotFoundException();
		}
		return $event;
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'helfer_public')]
	#[FrontpageRoute(verb: 'GET', url: '/s/{token}')]
	public function show(string $token): TemplateResponse {
		try {
			$event = $this->resolve($token);
		} catch (NotFoundException) {
			$response = new TemplateResponse(Application::APP_ID, 'notfound', [], TemplateResponse::RENDER_AS_GUEST);
			$response->setStatus(Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}
		$this->initialState->provideInitialState('token', $token);
		$response = new PublicTemplateResponse(Application::APP_ID, 'public');
		$response->setHeaderTitle($event->getTitle());
		return $this->withDeviceCookie($response);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'helfer_public')]
	#[FrontpageRoute(verb: 'GET', url: '/api/public/{token}')]
	public function data(string $token): JSONResponse {
		try {
			$event = $this->resolve($token);
		} catch (NotFoundException) {
			$response = new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}
		return $this->withDeviceCookie(new JSONResponse($this->publicData($event)));
	}

	/**
	 * Calendar file with the given slots, e.g. the shifts somebody signed up for.
	 *
	 * @param string $slots comma separated slot ids, empty = all
	 * @param string $names optional "slotId:Name" pairs separated by "|" to show who helps
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'helfer_public')]
	#[FrontpageRoute(verb: 'GET', url: '/s/{token}/kalender.ics')]
	public function calendar(string $token, string $slots = '', string $names = ''): Response {
		try {
			$event = $this->resolve($token);
		} catch (NotFoundException) {
			$response = new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}
		$slotIds = null;
		if ($slots !== '') {
			$slotIds = array_values(array_unique(array_map('intval', array_filter(explode(',', $slots), 'is_numeric'))));
		}
		$namesBySlot = [];
		foreach (array_slice(explode('|', $names), 0, 200) as $pair) {
			$parts = explode(':', $pair, 2);
			if (count($parts) === 2 && is_numeric($parts[0]) && trim($parts[1]) !== '') {
				$namesBySlot[(int)$parts[0]][] = mb_substr(trim($parts[1]), 0, 100);
			}
		}
		return new DataDownloadResponse(
			$this->calendar->build($event, $slotIds, $namesBySlot),
			$this->calendar->fileName($event),
			CalendarService::MIME,
		);
	}

	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 600)]
	#[BruteForceProtection(action: 'helfer_public')]
	#[FrontpageRoute(verb: 'POST', url: '/api/public/{token}/signups')]
	public function signup(string $token, array $slotIds = []): JSONResponse {
		try {
			$event = $this->resolve($token);
		} catch (NotFoundException) {
			$response = new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}
		return $this->withDeviceCookie($this->handle(function () use ($event, $slotIds) {
			$result = $this->signups->publicSignup($event, $slotIds, $this->request->getParams(), $this->deviceHash());
			return array_merge($this->publicData($event), [
				'editToken' => $result['editToken'],
				'created' => array_map(static fn ($s) => $s->getId(), $result['signups']),
			]);
		}));
	}

	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 600)]
	#[BruteForceProtection(action: 'helfer_public')]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/public/{token}/signups/{signupId}', requirements: ['signupId' => '\d+'])]
	public function cancel(string $token, int $signupId, string $editToken = ''): JSONResponse {
		try {
			$event = $this->resolve($token);
		} catch (NotFoundException) {
			$response = new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}
		$response = $this->handle(function () use ($event, $signupId, $editToken) {
			$this->signups->publicCancel($event, $signupId, $editToken, $this->deviceHash());
			return $this->publicData($event);
		});
		if ($response->getStatus() === Http::STATUS_NOT_FOUND) {
			$response->throttle();
		}
		return $response;
	}

	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 600)]
	#[BruteForceProtection(action: 'helfer_public')]
	#[FrontpageRoute(verb: 'POST', url: '/api/public/{token}/donations')]
	public function donate(string $token, int $listId = 0): JSONResponse {
		try {
			$event = $this->resolve($token);
		} catch (NotFoundException) {
			$response = new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}
		return $this->withDeviceCookie($this->handle(function () use ($event, $listId) {
			$result = $this->donations->publicAdd($event, $listId, $this->request->getParams(), $this->deviceHash());
			return array_merge($this->publicData($event), [
				'editToken' => $result['editToken'],
				'created' => [$result['donation']->getId()],
			]);
		}));
	}

	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 600)]
	#[BruteForceProtection(action: 'helfer_public')]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/public/{token}/donations/{donationId}', requirements: ['donationId' => '\d+'])]
	public function cancelDonation(string $token, int $donationId, string $editToken = ''): JSONResponse {
		try {
			$event = $this->resolve($token);
		} catch (NotFoundException) {
			$response = new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}
		$response = $this->handle(function () use ($event, $donationId, $editToken) {
			$this->donations->publicCancel($event, $donationId, $editToken, $this->deviceHash());
			return $this->publicData($event);
		});
		if ($response->getStatus() === Http::STATUS_NOT_FOUND) {
			$response->throttle();
		}
		return $response;
	}

	private function publicData(Event $event): array {
		$full = $this->events->getFull($event);
		$now = $this->time->getTime();
		$withNames = (bool)$event->getPublicNames();
		return [
			'event' => [
				'title' => $event->getTitle(),
				'description' => $event->getDescription() ?? '',
				'location' => $event->getLocation() ?? '',
				'contact' => $event->getContact() ?? '',
				'contactEmail' => $event->getContactEmail() ?? '',
				'organizer' => $event->getOrganizer() ?? '',
				'startDate' => $event->getStartDate(),
				'timezone' => $event->getTimezone(),
				'endDate' => $event->getEndDate(),
				'closeDate' => $event->getCloseDate(),
				'ended' => $event->isEnded($now),
				'signupDeadline' => $event->getSignupDeadline(),
				'signupClosed' => $event->isSignupClosed($now),
				'closed' => $event->isClosed($now),
				'askEmail' => (bool)$event->getAskEmail(),
				'emailRequired' => (bool)$event->getAskEmail() && (bool)$event->getEmailRequired(),
				'askPhone' => (bool)$event->getAskPhone(),
				'askGroup' => (bool)$event->getAskGroup(),
				'groupOptions' => $event->getGroupOptionList(),
				'questions' => $event->getQuestionList(),
				'publicNames' => $withNames,
			],
			'shifts' => $full['shifts'],
			'stations' => $full['stations'],
			'slots' => $full['slots'],
			'signups' => array_map(fn ($s) => $s->toPublicArray($withNames, $this->deviceHash()), $full['signups']),
			'donationLists' => $full['donationLists'],
			'donations' => array_map(fn ($d) => $d->toPublicArray($withNames, $this->deviceHash()), $full['donations']),
		];
	}
}
