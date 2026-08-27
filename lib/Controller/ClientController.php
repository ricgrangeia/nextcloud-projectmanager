<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Controller;

use OCA\ProjectManager\Service\ClientService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ClientController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ClientService $clientService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/clients')]
	public function index(): DataResponse {
		return new DataResponse($this->clientService->findAll($this->getUserId()));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/clients')]
	public function create(string $name): DataResponse {
		return new DataResponse($this->clientService->create($this->getUserId(), $name), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/clients/{id}', requirements: ['id' => '\d+'])]
	public function show(int $id): DataResponse {
		try {
			return new DataResponse($this->clientService->buildSummary($id, $this->getUserId()));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/clients/{id}', requirements: ['id' => '\d+'])]
	public function update(
		int $id,
		?string $name = null,
		?float $hourlyRate = null,
		bool $hourlyRateProvided = false,
		?string $currencySymbol = null,
	): DataResponse {
		try {
			return new DataResponse($this->clientService->update($id, $this->getUserId(), $name, $hourlyRate, $hourlyRateProvided, $currencySymbol));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/clients/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->clientService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
