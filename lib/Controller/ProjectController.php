<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Controller;

use OCA\ProjectManager\Service\ExampleProjectService;
use OCA\ProjectManager\Service\ProjectService;
use OCA\ProjectManager\Service\TrackerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ProjectController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ProjectService $projectService,
		private TrackerService $trackerService,
		private ExampleProjectService $exampleProjectService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/projects')]
	public function index(): DataResponse {
		return new DataResponse($this->projectService->findAll($this->getUserId()));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/projects')]
	public function create(string $name, float $hoursPerWorkingDay = 7.0): DataResponse {
		return new DataResponse($this->projectService->create($this->getUserId(), $name, $hoursPerWorkingDay), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/projects/example')]
	public function createExample(): DataResponse {
		return new DataResponse($this->exampleProjectService->create($this->getUserId()), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/projects/{id}', requirements: ['id' => '\d+'])]
	public function show(int $id): DataResponse {
		try {
			return new DataResponse($this->trackerService->buildGrid($id, $this->getUserId()));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/projects/{id}', requirements: ['id' => '\d+'])]
	public function update(
		int $id,
		?string $name = null,
		?float $hoursPerWorkingDay = null,
		?float $hourlyRate = null,
		bool $hourlyRateProvided = false,
		?string $currencySymbol = null,
		?bool $showCostInSummary = null,
	): DataResponse {
		try {
			return new DataResponse($this->projectService->update($id, $this->getUserId(), $name, $hoursPerWorkingDay, $hourlyRate, $hourlyRateProvided, $currencySymbol, $showCostInSummary));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/projects/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->projectService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
