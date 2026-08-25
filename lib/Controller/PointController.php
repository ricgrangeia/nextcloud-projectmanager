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

class PointController extends Controller {
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
	#[FrontpageRoute(verb: 'POST', url: '/api/modules/{moduleId}/points', requirements: ['moduleId' => '\d+'])]
	public function create(int $moduleId, string $code, string $description, ?float $estimateH = null, string $status = 'todo', int $sortOrder = 0): DataResponse {
		try {
			return new DataResponse($this->trackerService->createPoint($moduleId, $this->getUserId(), $code, $description, $estimateH, $status, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/points/{id}', requirements: ['id' => '\d+'])]
	public function update(int $id, ?string $code = null, ?string $description = null, ?float $estimateH = null, bool $estimateHProvided = false, ?string $status = null, ?int $sortOrder = null): DataResponse {
		try {
			return new DataResponse($this->trackerService->updatePoint($id, $this->getUserId(), $code, $description, $estimateH, $estimateHProvided, $status, $sortOrder));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/points/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->trackerService->deletePoint($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
