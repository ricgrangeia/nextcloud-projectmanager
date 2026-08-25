<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Controller;

use OCA\ProjectManager\Service\FeatureService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class FeatureController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private FeatureService $featureService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/projects/{projectId}/features', requirements: ['projectId' => '\d+'])]
	public function index(int $projectId): DataResponse {
		try {
			return new DataResponse($this->featureService->findAll($projectId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/projects/{projectId}/features', requirements: ['projectId' => '\d+'])]
	public function create(int $projectId, string $section, string $name, string $pointRef = '', string $status = 'not_started', string $businessValue = '', string $externalPending = '', int $sortOrder = 0): DataResponse {
		try {
			return new DataResponse($this->featureService->create($projectId, $this->getUserId(), $section, $name, $pointRef, $status, $businessValue, $externalPending, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/features/{id}', requirements: ['id' => '\d+'])]
	public function update(int $id, ?string $section = null, ?string $name = null, ?string $pointRef = null, ?string $status = null, ?string $businessValue = null, ?string $externalPending = null, ?int $sortOrder = null): DataResponse {
		try {
			return new DataResponse($this->featureService->update($id, $this->getUserId(), compact('section', 'name', 'pointRef', 'status', 'businessValue', 'externalPending', 'sortOrder')));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/features/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->featureService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
