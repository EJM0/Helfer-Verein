<?php

declare(strict_types=1);

namespace OCA\Helfer\Controller;

use OCA\Helfer\AppInfo\Application;
use OCA\Helfer\Service\CalendarService;
use OCA\Helfer\Service\EventService;
use OCA\Helfer\Service\ExportService;
use OCA\Helfer\Service\SignupService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * Management API for the logged-in owner of the surveys.
 *
 * @psalm-suppress UnusedClass
 */
class EventController extends Controller {
	use Errors;

	public function __construct(
		IRequest $request,
		private EventService $events,
		private SignupService $signups,
		private ExportService $export,
		private CalendarService $calendar,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	private function uid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new \RuntimeException('Not logged in');
		}
		return $user->getUID();
	}

	private function full(\OCA\Helfer\Db\Event $event): array {
		$data = $this->events->getFull($event);
		$data['publicUrl'] = $this->publicUrl($event->getToken());
		return $data;
	}

	private function publicUrl(?string $token): ?string {
		if ($token === null) {
			return null;
		}
		return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.public.show', ['token' => $token]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/events')]
	public function index(): JSONResponse {
		return $this->handle(function () {
			$result = [];
			foreach ($this->events->listForUser($this->uid()) as $event) {
				$result[] = array_merge($event->jsonSerialize(), [
					'stats' => $this->events->getStats($event),
					'publicUrl' => $this->publicUrl($event->getToken()),
				]);
			}
			return $result;
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/events')]
	public function create(): JSONResponse {
		return $this->handle(fn () => $this->full($this->events->create($this->uid(), $this->request->getParams())));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/events/{id}', requirements: ['id' => '\d+'])]
	public function show(int $id): JSONResponse {
		return $this->handle(fn () => $this->full($this->events->getForUser($id, $this->uid())));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/events/{id}', requirements: ['id' => '\d+'])]
	public function update(int $id): JSONResponse {
		return $this->handle(function () use ($id) {
			$event = $this->events->getForUser($id, $this->uid());
			return $this->full($this->events->update($event, $this->request->getParams()));
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/events/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): JSONResponse {
		return $this->handle(function () use ($id) {
			$this->events->delete($this->events->getForUser($id, $this->uid()));
			return ['status' => 'ok'];
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/events/{id}/duplicate', requirements: ['id' => '\d+'])]
	public function duplicate(int $id): JSONResponse {
		return $this->handle(function () use ($id) {
			$event = $this->events->getForUser($id, $this->uid());
			return $this->full($this->events->duplicate($event, $this->uid()));
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/events/{id}/token', requirements: ['id' => '\d+'])]
	public function regenerateToken(int $id): JSONResponse {
		return $this->handle(fn () => $this->full($this->events->regenerateToken($this->events->getForUser($id, $this->uid()))));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/events/{id}/token', requirements: ['id' => '\d+'])]
	public function removeToken(int $id): JSONResponse {
		return $this->handle(fn () => $this->full($this->events->removeToken($this->events->getForUser($id, $this->uid()))));
	}

	// ---- structure ----------------------------------------------------------

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/events/{id}/shifts', requirements: ['id' => '\d+'])]
	public function addShift(int $id): JSONResponse {
		return $this->handle(function () use ($id) {
			$event = $this->events->getForUser($id, $this->uid());
			$this->events->addShift($event, $this->request->getParams());
			return $this->full($event);
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/events/{id}/shifts/{shiftId}', requirements: ['id' => '\d+', 'shiftId' => '\d+'])]
	public function updateShift(int $id, int $shiftId): JSONResponse {
		return $this->handle(function () use ($id, $shiftId) {
			$event = $this->events->getForUser($id, $this->uid());
			$this->events->updateShift($event, $shiftId, $this->request->getParams());
			return $this->full($event);
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/events/{id}/shifts/{shiftId}', requirements: ['id' => '\d+', 'shiftId' => '\d+'])]
	public function deleteShift(int $id, int $shiftId): JSONResponse {
		return $this->handle(function () use ($id, $shiftId) {
			$event = $this->events->getForUser($id, $this->uid());
			$this->events->deleteShift($event, $shiftId);
			return $this->full($event);
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/events/{id}/stations', requirements: ['id' => '\d+'])]
	public function addStation(int $id): JSONResponse {
		return $this->handle(function () use ($id) {
			$event = $this->events->getForUser($id, $this->uid());
			$this->events->addStation($event, $this->request->getParams());
			return $this->full($event);
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/events/{id}/stations/{stationId}', requirements: ['id' => '\d+', 'stationId' => '\d+'])]
	public function updateStation(int $id, int $stationId): JSONResponse {
		return $this->handle(function () use ($id, $stationId) {
			$event = $this->events->getForUser($id, $this->uid());
			$this->events->updateStation($event, $stationId, $this->request->getParams());
			return $this->full($event);
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/events/{id}/stations/{stationId}', requirements: ['id' => '\d+', 'stationId' => '\d+'])]
	public function deleteStation(int $id, int $stationId): JSONResponse {
		return $this->handle(function () use ($id, $stationId) {
			$event = $this->events->getForUser($id, $this->uid());
			$this->events->deleteStation($event, $stationId);
			return $this->full($event);
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/events/{id}/slots', requirements: ['id' => '\d+'])]
	public function setSlot(int $id, int $shiftId, int $stationId, int $needed): JSONResponse {
		return $this->handle(function () use ($id, $shiftId, $stationId, $needed) {
			$event = $this->events->getForUser($id, $this->uid());
			$this->events->setSlot($event, $shiftId, $stationId, $needed);
			return $this->full($event);
		});
	}

	// ---- helpers (admin, no date restrictions) ------------------------------

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/events/{id}/signups', requirements: ['id' => '\d+'])]
	public function addSignup(int $id, int $slotId): JSONResponse {
		return $this->handle(function () use ($id, $slotId) {
			$event = $this->events->getForUser($id, $this->uid());
			$this->signups->adminAdd($event, $slotId, $this->request->getParams(), $this->uid());
			return $this->full($event);
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/events/{id}/signups/{signupId}', requirements: ['id' => '\d+', 'signupId' => '\d+'])]
	public function updateSignup(int $id, int $signupId): JSONResponse {
		return $this->handle(function () use ($id, $signupId) {
			$event = $this->events->getForUser($id, $this->uid());
			$params = $this->request->getParams();
			unset($params['id'], $params['signupId']);
			$this->signups->adminUpdate($event, $signupId, $params);
			return $this->full($event);
		});
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/events/{id}/signups/{signupId}', requirements: ['id' => '\d+', 'signupId' => '\d+'])]
	public function deleteSignup(int $id, int $signupId): JSONResponse {
		return $this->handle(function () use ($id, $signupId) {
			$event = $this->events->getForUser($id, $this->uid());
			$this->signups->adminDelete($event, $signupId);
			return $this->full($event);
		});
	}

	// ---- export -------------------------------------------------------------

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/events/{id}/export', requirements: ['id' => '\d+'])]
	public function exportToFolder(int $id, ?string $folder = null): JSONResponse {
		return $this->handle(function () use ($id, $folder) {
			$event = $this->events->getForUser($id, $this->uid());
			$path = $this->export->exportToFolder($event, $folder);
			$event = $this->events->getForUser($id, $this->uid());
			return array_merge($this->full($event), ['path' => $path]);
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/events/{id}/export.xlsx', requirements: ['id' => '\d+'])]
	public function download(int $id): Response {
		try {
			$event = $this->events->getForUser($id, $this->uid());
		} catch (\OCA\Helfer\Service\NotFoundException) {
			return new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
		}
		return new DataDownloadResponse($this->export->build($event), $this->export->fileName($event), ExportService::MIME);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/events/{id}/calendar.ics', requirements: ['id' => '\d+'])]
	public function calendar(int $id): Response {
		try {
			$event = $this->events->getForUser($id, $this->uid());
		} catch (\OCA\Helfer\Service\NotFoundException) {
			return new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
		}
		return new DataDownloadResponse($this->calendar->build($event), $this->calendar->fileName($event), CalendarService::MIME);
	}
}
