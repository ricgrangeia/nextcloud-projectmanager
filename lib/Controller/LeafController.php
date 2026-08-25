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

class LeafController extends Controller {
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
	#[FrontpageRoute(verb: 'POST', url: '/api/points/{pointId}/leaves', requirements: ['pointId' => '\d+'])]
	public function create(int $pointId, string $description, string $workDate, int $sortOrder = 0): DataResponse {
		try {
			$date = new \DateTimeImmutable($workDate);
			return new DataResponse($this->trackerService->createLeaf($pointId, $this->getUserId(), $description, $date, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/leaves/{id}', requirements: ['id' => '\d+'])]
	public function update(int $id, ?string $description = null, ?string $workDate = null, ?int $sortOrder = null): DataResponse {
		try {
			$date = $workDate !== null ? new \DateTimeImmutable($workDate) : null;
			return new DataResponse($this->trackerService->updateLeaf($id, $this->getUserId(), $description, $date, $sortOrder));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/leaves/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->trackerService->deleteLeaf($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
