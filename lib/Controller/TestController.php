<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Controller;

use OCA\ProjectManager\Service\TestService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class TestController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private TestService $testService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/projects/{projectId}/tests', requirements: ['projectId' => '\d+'])]
	public function index(int $projectId): DataResponse {
		try {
			return new DataResponse($this->testService->findAll($projectId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/projects/{projectId}/tests', requirements: ['projectId' => '\d+'])]
	public function create(int $projectId, string $area, string $profile = '', string $scenario = '', string $expected = '', string $status = 'to_test', ?string $testDate = null, string $notes = '', int $sortOrder = 0): DataResponse {
		try {
			$date = $testDate !== null ? new \DateTimeImmutable($testDate) : null;
			return new DataResponse($this->testService->create($projectId, $this->getUserId(), $area, $profile, $scenario, $expected, $status, $date, $notes, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/tests/{id}', requirements: ['id' => '\d+'])]
	public function update(int $id, ?string $area = null, ?string $profile = null, ?string $scenario = null, ?string $expected = null, ?string $status = null, ?string $testDate = null, ?string $notes = null, ?int $sortOrder = null): DataResponse {
		try {
			$testDateValue = $testDate !== null ? new \DateTimeImmutable($testDate) : null;
			$fields = compact('area', 'profile', 'scenario', 'expected', 'status', 'notes', 'sortOrder');
			$fields['testDate'] = $testDateValue;
			return new DataResponse($this->testService->update($id, $this->getUserId(), $fields));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/tests/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->testService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
