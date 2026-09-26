<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Controller;

use OCA\ProjectManager\Service\MilestoneService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class MilestoneController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private MilestoneService $milestoneService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/projects/{projectId}/milestones', requirements: ['projectId' => '\d+'])]
	public function index(int $projectId): DataResponse {
		try {
			return new DataResponse($this->milestoneService->findAll($projectId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/projects/{projectId}/milestones', requirements: ['projectId' => '\d+'])]
	public function create(int $projectId, string $name, ?string $targetDate = null, int $sortOrder = 0): DataResponse {
		try {
			$date = $targetDate !== null && $targetDate !== '' ? new \DateTimeImmutable($targetDate) : null;
			return new DataResponse($this->milestoneService->create($projectId, $this->getUserId(), $name, $date, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/milestones/{id}', requirements: ['id' => '\d+'])]
	public function update(
		int $id,
		?string $name = null,
		?string $targetDate = null,
		bool $targetDateProvided = false,
		?string $reachedDate = null,
		bool $reachedDateProvided = false,
		?int $sortOrder = null,
		?string $communicationChannel = null,
		?string $clientStatus = null,
		?int $acceptancePct = null,
	): DataResponse {
		try {
			$fields = [
				'name' => $name,
				'sortOrder' => $sortOrder,
				'communicationChannel' => $communicationChannel,
				'clientStatus' => $clientStatus,
				'acceptancePct' => $acceptancePct,
			];
			if ($targetDateProvided) {
				$fields['targetDate'] = $targetDate !== null && $targetDate !== '' ? new \DateTimeImmutable($targetDate) : null;
			}
			if ($reachedDateProvided) {
				$fields['reachedDate'] = $reachedDate !== null && $reachedDate !== '' ? new \DateTimeImmutable($reachedDate) : null;
			}
			return new DataResponse($this->milestoneService->update($id, $this->getUserId(), $fields));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/milestones/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->milestoneService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
