<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Controller;

use OCA\ProjectManager\Service\BackupService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class BackupController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private BackupService $backupService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/backup/export')]
	public function export(): Http\Response {
		$data = $this->backupService->exportAll($this->getUserId());
		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return new DataDownloadResponse(
			$json,
			'projectmanager-backup-' . (new \DateTimeImmutable())->format('Y-m-d') . '.json',
			'application/json',
		);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/backup/import')]
	public function import(): DataResponse {
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
			$counts = $this->backupService->importAll($this->getUserId(), $data);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($counts);
	}
}
