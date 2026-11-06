<?php

declare(strict_types=1);

namespace OCA\Helfer\Controller;

use OCA\Helfer\AppInfo\Application;
use OCA\Helfer\Db\Event;
use OCA\Helfer\Service\CalendarService;
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
		private CalendarService $calendar,
		private IInitialState $initialState,
		private ITimeFactory $time,
	) {
		parent::__construct(Application::APP_ID, $request);
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
		$response->setFooterVisible(false);
		return $response;
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
		return new JSONResponse($this->publicData($event));
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
		return $this->handle(function () use ($event, $slotIds) {
			$result = $this->signups->publicSignup($event, $slotIds, $this->request->getParams());
			return array_merge($this->publicData($event), [
				'editToken' => $result['editToken'],
				'created' => array_map(static fn ($s) => $s->getId(), $result['signups']),
			]);
		});
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
			$this->signups->publicCancel($event, $signupId, $editToken);
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
				'timezone' => $event->getTimezone(),
				'endDate' => $event->getEndDate(),
				'closeDate' => $event->getCloseDate(),
				'ended' => $event->isEnded($now),
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
			'signups' => array_map(static fn ($s) => $s->toPublicArray($withNames), $full['signups']),
		];
	}
}
