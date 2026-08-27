<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Controller;

use OCA\ProjectManager\Service\BackupService;
use OCA\ProjectManager\Service\ClientService;
use OCA\ProjectManager\Service\ExampleProjectService;
use OCA\ProjectManager\Service\FeatureService;
use OCA\ProjectManager\Service\ProjectService;
use OCA\ProjectManager\Service\TestService;
use OCA\ProjectManager\Service\TrackerService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Token-friendly OCS API, mirroring the SPA's endpoints one-for-one.
 *
 * Meant for external/automated clients (e.g. an AI assistant pairing with the
 * user) authenticated via a Nextcloud "app password" (Basic Auth), not a
 * browser session — see Settings > Security > "Create new app password".
 * Every request must carry the `OCS-APIRequest: true` header, per the
 * standard OCS API convention.
 */
class ApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ProjectService $projectService,
		private TrackerService $trackerService,
		private FeatureService $featureService,
		private TestService $testService,
		private ExampleProjectService $exampleProjectService,
		private BackupService $backupService,
		private ClientService $clientService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	private function notFound(): DataResponse {
		return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
	}

	// --- Discovery ------------------------------------------------------

	#[PublicPage]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/help')]
	public function help(): DataResponse {
		$host = $this->request->getServerProtocol() . '://' . $this->request->getServerHost();

		return new DataResponse([
			'description' => 'Project Manager tracks project hours and progress across Points, Leaves (dated work log entries) and days. This API mirrors the web UI one-for-one and is meant for external/automated clients, e.g. an AI assistant pairing with the user.',
			'authentication' => [
				'method' => 'HTTP Basic Auth using a Nextcloud "app password" (NOT the account password)',
				'howToGet' => 'In Nextcloud: Settings > Security > Devices & sessions > "Create new app password". The value is shown only once.',
				'requiredHeader' => 'OCS-APIRequest: true (on every request, standard Nextcloud OCS API requirement)',
			],
			'fullSpec' => $host . '/custom_apps/projectmanager/openapi.json',
			'fullSpecNote' => 'OpenAPI 3.0 document with every endpoint, parameter and response shape. Read this before guessing endpoint names.',
			'quickReference' => [
				['method' => 'GET', 'path' => '/api/v1/projects', 'summary' => 'List your projects (archived ones excluded unless ?includeArchived=true)'],
				['method' => 'POST', 'path' => '/api/v1/projects', 'summary' => 'Create a project (name, hoursPerWorkingDay, clientId optional)'],
				['method' => 'POST', 'path' => '/api/v1/projects/example', 'summary' => 'Seed a fully worked example project'],
				['method' => 'GET', 'path' => '/api/v1/projects/{id}', 'summary' => 'Get the fully computed grid: modules, points, leaves, done/remaining hours, summary'],
				['method' => 'PUT', 'path' => '/api/v1/projects/{id}', 'summary' => 'Rename / change hoursPerWorkingDay, hourlyRate, currencySymbol, showCostInSummary, archived (true to archive, false to restore), clientId (pass clientIdProvided=true to change/clear it)'],
				['method' => 'DELETE', 'path' => '/api/v1/projects/{id}', 'summary' => 'Delete a project and everything under it'],
				['method' => 'GET', 'path' => '/api/v1/clients', 'summary' => 'List your clients'],
				['method' => 'POST', 'path' => '/api/v1/clients', 'summary' => 'Create a client (name)'],
				['method' => 'GET', 'path' => '/api/v1/clients/{id}', 'summary' => 'Get a client with its aggregated summary (estimated/done/remaining hours and cost, summed across all its projects) and a per-project breakdown'],
				['method' => 'PUT', 'path' => '/api/v1/clients/{id}', 'summary' => 'Rename a client, or set its default hourlyRate (pass hourlyRateProvided=true to change/clear it) and currencySymbol — projects under it inherit these unless they set their own hourlyRate, and always use the client\'s currencySymbol'],
				['method' => 'DELETE', 'path' => '/api/v1/clients/{id}', 'summary' => 'Delete a client (its projects are kept, unassigned)'],
				['method' => 'POST', 'path' => '/api/v1/projects/{projectId}/modules', 'summary' => 'Create a module (code, name, inEstimate)'],
				['method' => 'PUT', 'path' => '/api/v1/modules/{id}', 'summary' => 'Update a module'],
				['method' => 'DELETE', 'path' => '/api/v1/modules/{id}', 'summary' => 'Delete a module and its points/leaves'],
				['method' => 'POST', 'path' => '/api/v1/modules/{moduleId}/points', 'summary' => 'Create a point (code, description, estimateH, status)'],
				['method' => 'PUT', 'path' => '/api/v1/points/{id}', 'summary' => 'Update a point (pass estimateHProvided=true to change/clear estimateH)'],
				['method' => 'DELETE', 'path' => '/api/v1/points/{id}', 'summary' => 'Delete a point and its leaves'],
				['method' => 'POST', 'path' => '/api/v1/points/{pointId}/leaves', 'summary' => 'Log work done on a point on a given day (description, workDate)'],
				['method' => 'PUT', 'path' => '/api/v1/leaves/{id}', 'summary' => 'Update a leaf'],
				['method' => 'DELETE', 'path' => '/api/v1/leaves/{id}', 'summary' => 'Delete a leaf'],
				['method' => 'PUT', 'path' => '/api/v1/projects/{projectId}/day-hours/{date}', 'summary' => 'Set actual hours worked on a day (date in the URL as YYYY-MM-DD, body param "hours")'],
				['method' => 'DELETE', 'path' => '/api/v1/projects/{projectId}/day-hours/{date}', 'summary' => 'Remove the hours entry for a day'],
				['method' => 'GET', 'path' => '/api/v1/projects/{projectId}/features', 'summary' => 'List features'],
				['method' => 'POST', 'path' => '/api/v1/projects/{projectId}/features', 'summary' => 'Create a feature (section, name, pointRef, status, businessValue, externalPending)'],
				['method' => 'PUT', 'path' => '/api/v1/features/{id}', 'summary' => 'Update a feature'],
				['method' => 'DELETE', 'path' => '/api/v1/features/{id}', 'summary' => 'Delete a feature'],
				['method' => 'GET', 'path' => '/api/v1/projects/{projectId}/tests', 'summary' => 'List test log entries'],
				['method' => 'POST', 'path' => '/api/v1/projects/{projectId}/tests', 'summary' => 'Create a test entry (area, profile, scenario, expected, status, testDate, notes)'],
				['method' => 'PUT', 'path' => '/api/v1/tests/{id}', 'summary' => 'Update a test entry'],
				['method' => 'DELETE', 'path' => '/api/v1/tests/{id}', 'summary' => 'Delete a test entry'],
				['method' => 'GET', 'path' => '/api/v1/backup/export', 'summary' => 'Download a JSON snapshot of every project you own (for backup / server migration)'],
				['method' => 'POST', 'path' => '/api/v1/backup/import', 'summary' => 'Restore from a backup JSON file (multipart/form-data, field name "file"). Always creates new projects.'],
			],
		]);
	}

	// --- Backup ---------------------------------------------------------

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/backup/export')]
	public function backupExport(): DataResponse {
		return new DataResponse($this->backupService->exportAll($this->getUserId()));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/backup/import')]
	public function backupImport(): DataResponse {
		$uploaded = $this->request->getUploadedFile('file');
		if ($uploaded === null || !is_string($uploaded['tmp_name'] ?? null) || !is_uploaded_file($uploaded['tmp_name'])) {
			return new DataResponse(['message' => 'No file uploaded'], Http::STATUS_BAD_REQUEST);
		}

		$contents = file_get_contents($uploaded['tmp_name']);
		$data = $contents !== false ? json_decode($contents, true) : null;
		if (!is_array($data)) {
			return new DataResponse(['message' => 'Invalid JSON file'], Http::STATUS_BAD_REQUEST);
		}

		try {
			return new DataResponse($this->backupService->importAll($this->getUserId(), $data));
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	// --- Projects -----------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/projects')]
	public function listProjects(bool $includeArchived = false): DataResponse {
		return new DataResponse($this->projectService->findAll($this->getUserId(), $includeArchived));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/projects')]
	public function createProject(string $name, float $hoursPerWorkingDay = 7.0, ?int $clientId = null): DataResponse {
		return new DataResponse($this->projectService->create($this->getUserId(), $name, $hoursPerWorkingDay, $clientId), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/projects/example')]
	public function createExampleProject(): DataResponse {
		return new DataResponse($this->exampleProjectService->create($this->getUserId()), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/projects/{id}', requirements: ['id' => '\d+'])]
	public function getProject(int $id): DataResponse {
		try {
			return new DataResponse($this->trackerService->buildGrid($id, $this->getUserId()));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/projects/{id}', requirements: ['id' => '\d+'])]
	public function updateProject(
		int $id,
		?string $name = null,
		?float $hoursPerWorkingDay = null,
		?float $hourlyRate = null,
		bool $hourlyRateProvided = false,
		?string $currencySymbol = null,
		?bool $showCostInSummary = null,
		?bool $archived = null,
		?int $clientId = null,
		bool $clientIdProvided = false,
	): DataResponse {
		try {
			return new DataResponse($this->projectService->update($id, $this->getUserId(), $name, $hoursPerWorkingDay, $hourlyRate, $hourlyRateProvided, $currencySymbol, $showCostInSummary, $archived, $clientId, $clientIdProvided));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/projects/{id}', requirements: ['id' => '\d+'])]
	public function deleteProject(int $id): DataResponse {
		try {
			$this->projectService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Clients ----------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/clients')]
	public function listClients(): DataResponse {
		return new DataResponse($this->clientService->findAll($this->getUserId()));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/clients')]
	public function createClient(string $name): DataResponse {
		return new DataResponse($this->clientService->create($this->getUserId(), $name), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/clients/{id}', requirements: ['id' => '\d+'])]
	public function getClient(int $id): DataResponse {
		try {
			return new DataResponse($this->clientService->buildSummary($id, $this->getUserId()));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/clients/{id}', requirements: ['id' => '\d+'])]
	public function updateClient(
		int $id,
		?string $name = null,
		?float $hourlyRate = null,
		bool $hourlyRateProvided = false,
		?string $currencySymbol = null,
	): DataResponse {
		try {
			return new DataResponse($this->clientService->update($id, $this->getUserId(), $name, $hourlyRate, $hourlyRateProvided, $currencySymbol));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/clients/{id}', requirements: ['id' => '\d+'])]
	public function deleteClient(int $id): DataResponse {
		try {
			$this->clientService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Modules --------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/projects/{projectId}/modules', requirements: ['projectId' => '\d+'])]
	public function createModule(int $projectId, string $code, string $name, bool $inEstimate = true, int $sortOrder = 0): DataResponse {
		try {
			return new DataResponse($this->trackerService->createModule($projectId, $this->getUserId(), $code, $name, $inEstimate, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/modules/{id}', requirements: ['id' => '\d+'])]
	public function updateModule(int $id, ?string $code = null, ?string $name = null, ?bool $inEstimate = null, ?int $sortOrder = null): DataResponse {
		try {
			return new DataResponse($this->trackerService->updateModule($id, $this->getUserId(), $code, $name, $inEstimate, $sortOrder));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/modules/{id}', requirements: ['id' => '\d+'])]
	public function deleteModule(int $id): DataResponse {
		try {
			$this->trackerService->deleteModule($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Points -----------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/modules/{moduleId}/points', requirements: ['moduleId' => '\d+'])]
	public function createPoint(int $moduleId, string $code, string $description, ?float $estimateH = null, string $status = 'todo', int $sortOrder = 0): DataResponse {
		try {
			return new DataResponse($this->trackerService->createPoint($moduleId, $this->getUserId(), $code, $description, $estimateH, $status, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/points/{id}', requirements: ['id' => '\d+'])]
	public function updatePoint(int $id, ?string $code = null, ?string $description = null, ?float $estimateH = null, bool $estimateHProvided = false, ?string $status = null, ?int $sortOrder = null): DataResponse {
		try {
			return new DataResponse($this->trackerService->updatePoint($id, $this->getUserId(), $code, $description, $estimateH, $estimateHProvided, $status, $sortOrder));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/points/{id}', requirements: ['id' => '\d+'])]
	public function deletePoint(int $id): DataResponse {
		try {
			$this->trackerService->deletePoint($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Leaves -------------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/points/{pointId}/leaves', requirements: ['pointId' => '\d+'])]
	public function createLeaf(int $pointId, string $description, string $workDate, int $sortOrder = 0): DataResponse {
		try {
			return new DataResponse($this->trackerService->createLeaf($pointId, $this->getUserId(), $description, new \DateTimeImmutable($workDate), $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/leaves/{id}', requirements: ['id' => '\d+'])]
	public function updateLeaf(int $id, ?string $description = null, ?string $workDate = null, ?int $sortOrder = null): DataResponse {
		try {
			$date = $workDate !== null ? new \DateTimeImmutable($workDate) : null;
			return new DataResponse($this->trackerService->updateLeaf($id, $this->getUserId(), $description, $date, $sortOrder));
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/leaves/{id}', requirements: ['id' => '\d+'])]
	public function deleteLeaf(int $id): DataResponse {
		try {
			$this->trackerService->deleteLeaf($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Day hours ------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/projects/{projectId}/day-hours/{date}', requirements: ['projectId' => '\d+', 'date' => '\d{4}-\d{2}-\d{2}'])]
	public function setDayHours(int $projectId, string $date, float $hours): DataResponse {
		try {
			return new DataResponse($this->trackerService->setDayHours($projectId, $this->getUserId(), new \DateTimeImmutable($date), $hours));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/projects/{projectId}/day-hours/{date}', requirements: ['projectId' => '\d+', 'date' => '\d{4}-\d{2}-\d{2}'])]
	public function deleteDayHours(int $projectId, string $date): DataResponse {
		try {
			$this->trackerService->deleteDayHours($projectId, $this->getUserId(), new \DateTimeImmutable($date));
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Features -----------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/projects/{projectId}/features', requirements: ['projectId' => '\d+'])]
	public function listFeatures(int $projectId): DataResponse {
		try {
			return new DataResponse($this->featureService->findAll($projectId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/projects/{projectId}/features', requirements: ['projectId' => '\d+'])]
	public function createFeature(int $projectId, string $section, string $name, string $pointRef = '', string $status = 'not_started', string $businessValue = '', string $externalPending = '', int $sortOrder = 0): DataResponse {
		try {
			return new DataResponse($this->featureService->create($projectId, $this->getUserId(), $section, $name, $pointRef, $status, $businessValue, $externalPending, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/features/{id}', requirements: ['id' => '\d+'])]
	public function updateFeature(int $id, ?string $section = null, ?string $name = null, ?string $pointRef = null, ?string $status = null, ?string $businessValue = null, ?string $externalPending = null, ?int $sortOrder = null): DataResponse {
		try {
			return new DataResponse($this->featureService->update($id, $this->getUserId(), compact('section', 'name', 'pointRef', 'status', 'businessValue', 'externalPending', 'sortOrder')));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/features/{id}', requirements: ['id' => '\d+'])]
	public function deleteFeature(int $id): DataResponse {
		try {
			$this->featureService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Tests --------------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/projects/{projectId}/tests', requirements: ['projectId' => '\d+'])]
	public function listTests(int $projectId): DataResponse {
		try {
			return new DataResponse($this->testService->findAll($projectId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/projects/{projectId}/tests', requirements: ['projectId' => '\d+'])]
	public function createTest(int $projectId, string $area, string $profile = '', string $scenario = '', string $expected = '', string $status = 'to_test', ?string $testDate = null, string $notes = '', int $sortOrder = 0): DataResponse {
		try {
			$date = $testDate !== null ? new \DateTimeImmutable($testDate) : null;
			return new DataResponse($this->testService->create($projectId, $this->getUserId(), $area, $profile, $scenario, $expected, $status, $date, $notes, $sortOrder), Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/tests/{id}', requirements: ['id' => '\d+'])]
	public function updateTest(int $id, ?string $area = null, ?string $profile = null, ?string $scenario = null, ?string $expected = null, ?string $status = null, ?string $testDate = null, ?string $notes = null, ?int $sortOrder = null): DataResponse {
		try {
			$testDateValue = $testDate !== null ? new \DateTimeImmutable($testDate) : null;
			$fields = compact('area', 'profile', 'scenario', 'expected', 'status', 'notes', 'sortOrder');
			$fields['testDate'] = $testDateValue;
			return new DataResponse($this->testService->update($id, $this->getUserId(), $fields));
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/tests/{id}', requirements: ['id' => '\d+'])]
	public function deleteTest(int $id): DataResponse {
		try {
			$this->testService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}
}
