<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Controller;

use OCA\ProjectManager\Service\TrackerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class DayHoursController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private TrackerService $trackerService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/projects/{projectId}/day-hours/{date}', requirements: ['projectId' => '\d+', 'date' => '\d{4}-\d{2}-\d{2}'])]
	public function set(int $projectId, string $date, float $hours): DataResponse {
		try {
			return new DataResponse($this->trackerService->setDayHours($projectId, $this->getUserId(), new \DateTimeImmutable($date), $hours));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/projects/{projectId}/day-hours/{date}', requirements: ['projectId' => '\d+', 'date' => '\d{4}-\d{2}-\d{2}'])]
	public function destroy(int $projectId, string $date): DataResponse {
		try {
			$this->trackerService->deleteDayHours($projectId, $this->getUserId(), new \DateTimeImmutable($date));
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
