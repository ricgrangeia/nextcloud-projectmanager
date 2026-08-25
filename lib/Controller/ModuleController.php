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

class ModuleController extends Controller {
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
	#[FrontpageRoute(verb: 'POST', url: '/api/projects/{projectId}/modules', requirements: ['projectId' => '\d+'])]
	public function create(int $projectId, string $code, string $name, bool $inEstimate = true, int $sortOrder = 0): DataResponse {
		try {
			return new DataResponse($this->trackerService->createModule($projectId, $this->getUserId(), $code, $name, $inEstimate, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/modules/{id}', requirements: ['id' => '\d+'])]
	public function update(int $id, ?string $code = null, ?string $name = null, ?bool $inEstimate = null, ?int $sortOrder = null): DataResponse {
		try {
			return new DataResponse($this->trackerService->updateModule($id, $this->getUserId(), $code, $name, $inEstimate, $sortOrder));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/modules/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->trackerService->deleteModule($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
