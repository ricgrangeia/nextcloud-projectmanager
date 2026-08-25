<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Controller;

use OCA\ProjectManager\Service\ExportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ExportService $exportService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/projects/{id}/export', requirements: ['id' => '\d+'])]
	public function export(int $id): Http\Response {
		$userId = $this->userSession->getUser()->getUID();
		try {
			$spreadsheet = $this->exportService->build($id, $userId);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$writer = new Xlsx($spreadsheet);
		ob_start();
		$writer->save('php://output');
		$content = ob_get_clean();

		return new DataDownloadResponse(
			$content,
			'project-' . $id . '.xlsx',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		);
	}
}
