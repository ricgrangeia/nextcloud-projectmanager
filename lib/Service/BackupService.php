<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\Client;
use OCA\ProjectManager\Db\ClientMapper;
use OCA\ProjectManager\Db\DayHours;
use OCA\ProjectManager\Db\DayHoursMapper;
use OCA\ProjectManager\Db\Feature;
use OCA\ProjectManager\Db\FeatureMapper;
use OCA\ProjectManager\Db\Leaf;
use OCA\ProjectManager\Db\LeafMapper;
use OCA\ProjectManager\Db\Module;
use OCA\ProjectManager\Db\ModuleMapper;
use OCA\ProjectManager\Db\Point;
use OCA\ProjectManager\Db\PointMapper;
use OCA\ProjectManager\Db\Project;
use OCA\ProjectManager\Db\ProjectMapper;
use OCA\ProjectManager\Db\TestEntry;
use OCA\ProjectManager\Db\TestEntryMapper;

/**
 * Full export/import of every project owned by a user — a portable JSON
 * snapshot meant for backing up before a server migration and restoring
 * afterwards. Import always creates new projects; it never overwrites or
 * merges with existing ones.
 */
class BackupService {
	private const EXPORT_VERSION = 1;

	public function __construct(
		private ProjectMapper $projectMapper,
		private ClientMapper $clientMapper,
		private ModuleMapper $moduleMapper,
		private PointMapper $pointMapper,
		private LeafMapper $leafMapper,
		private DayHoursMapper $dayHoursMapper,
		private FeatureMapper $featureMapper,
		private TestEntryMapper $testEntryMapper,
	) {
	}

	public function exportAll(string $userId): array {
		$clients = $this->clientMapper->findAllForUser($userId);
		$clientNamesById = [];
		foreach ($clients as $client) {
			$clientNamesById[$client->getId()] = $client->getName();
		}

		$projects = array_map(
			fn (Project $project) => $this->exportProject($project, $clientNamesById),
			$this->projectMapper->findAllForUser($userId, true),
		);

		return [
			'exportVersion' => self::EXPORT_VERSION,
			'exportedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
			'clients' => array_map(static fn (Client $c) => [
				'name' => $c->getName(),
				'hourlyRate' => $c->getHourlyRate(),
				'currencySymbol' => $c->getCurrencySymbol(),
			], $clients),
			'projects' => $projects,
		];
	}

	/** @param array<int, string> $clientNamesById */
	private function exportProject(Project $project, array $clientNamesById): array {
		$modules = $this->moduleMapper->findAllForProject($project->getId());
		$moduleIds = array_map(static fn (Module $m) => $m->getId(), $modules);
		$points = $this->pointMapper->findAllForModules($moduleIds);
		$pointIds = array_map(static fn (Point $p) => $p->getId(), $points);
		$leaves = $this->leafMapper->findAllForPoints($pointIds);

		$pointsByModule = [];
		foreach ($points as $point) {
			$pointsByModule[$point->getModuleId()][] = $point;
		}
		$leavesByPoint = [];
		foreach ($leaves as $leaf) {
			$leavesByPoint[$leaf->getPointId()][] = $leaf;
		}

		$moduleExports = [];
		foreach ($modules as $module) {
			$pointExports = [];
			foreach ($pointsByModule[$module->getId()] ?? [] as $point) {
				$pointExports[] = [
					'code' => $point->getCode(),
					'description' => $point->getDescription(),
					'estimateH' => $point->getEstimateH(),
					'status' => $point->getStatus(),
					'sortOrder' => $point->getSortOrder(),
					'leaves' => array_map(static fn (Leaf $leaf) => [
						'description' => $leaf->getDescription(),
						'workDate' => $leaf->getWorkDate()->format('Y-m-d'),
						'sortOrder' => $leaf->getSortOrder(),
					], $leavesByPoint[$point->getId()] ?? []),
				];
			}
			$moduleExports[] = [
				'code' => $module->getCode(),
				'name' => $module->getName(),
				'inEstimate' => $module->getInEstimate(),
				'sortOrder' => $module->getSortOrder(),
				'points' => $pointExports,
			];
		}

		return [
			'name' => $project->getName(),
			'hoursPerWorkingDay' => $project->getHoursPerWorkingDay(),
			'hourlyRate' => $project->getHourlyRate(),
			'currencySymbol' => $project->getCurrencySymbol(),
			'showCostInSummary' => $project->getShowCostInSummary(),
			'archived' => $project->getArchived(),
			'clientName' => $project->getClientId() !== null ? ($clientNamesById[$project->getClientId()] ?? null) : null,
			'modules' => $moduleExports,
			'dayHours' => array_map(static fn (DayHours $d) => [
				'workDate' => $d->getWorkDate()->format('Y-m-d'),
				'hours' => $d->getHours(),
			], $this->dayHoursMapper->findAllForProject($project->getId())),
			'features' => array_map(static fn (Feature $f) => [
				'section' => $f->getSection(),
				'name' => $f->getName(),
				'pointRef' => $f->getPointRef(),
				'status' => $f->getStatus(),
				'businessValue' => $f->getBusinessValue(),
				'externalPending' => $f->getExternalPending(),
				'sortOrder' => $f->getSortOrder(),
			], $this->featureMapper->findAllForProject($project->getId())),
			'tests' => array_map(static fn (TestEntry $t) => [
				'area' => $t->getArea(),
				'profile' => $t->getProfile(),
				'scenario' => $t->getScenario(),
				'expected' => $t->getExpected(),
				'status' => $t->getStatus(),
				'testDate' => $t->getTestDate()?->format('Y-m-d'),
				'notes' => $t->getNotes(),
				'sortOrder' => $t->getSortOrder(),
			], $this->testEntryMapper->findAllForProject($project->getId())),
		];
	}

	/**
	 * @throws \InvalidArgumentException if the payload isn't a recognised backup
	 */
	public function importAll(string $userId, array $data): array {
		if (!isset($data['projects']) || !is_array($data['projects'])) {
			throw new \InvalidArgumentException('Not a valid Project Manager backup file');
		}

		$counts = ['clients' => 0, 'projects' => 0, 'modules' => 0, 'points' => 0, 'leaves' => 0, 'dayHours' => 0, 'features' => 0, 'tests' => 0];

		/** @var array<string, int> $clientIdsByName */
		$clientIdsByName = [];
		foreach ($this->clientMapper->findAllForUser($userId) as $client) {
			$clientIdsByName[$client->getName()] = $client->getId();
		}

		foreach ($data['clients'] ?? [] as $clientData) {
			$name = (string) ($clientData['name'] ?? '');
			if ($name === '' || isset($clientIdsByName[$name])) {
				continue;
			}
			$client = new Client();
			$client->setUserId($userId);
			$client->setName($name);
			$client->setHourlyRate(isset($clientData['hourlyRate']) && $clientData['hourlyRate'] !== null ? (float) $clientData['hourlyRate'] : null);
			$client->setCurrencySymbol((string) ($clientData['currencySymbol'] ?? '€'));
			$client->setCreatedAt(new \DateTimeImmutable());
			$client = $this->clientMapper->insert($client);
			$clientIdsByName[$name] = $client->getId();
			$counts['clients']++;
		}

		foreach ($data['projects'] as $projectData) {
			$clientId = null;
			$clientName = $projectData['clientName'] ?? null;
			if (is_string($clientName) && $clientName !== '') {
				$clientId = $clientIdsByName[$clientName] ?? null;
			}

			$project = new Project();
			$project->setUserId($userId);
			$project->setName((string) ($projectData['name'] ?? 'Imported project'));
			$hpd = (float) ($projectData['hoursPerWorkingDay'] ?? 7.0);
			$project->setHoursPerWorkingDay($hpd > 0 ? $hpd : 7.0);
			$project->setHourlyRate(isset($projectData['hourlyRate']) && $projectData['hourlyRate'] !== null ? (float) $projectData['hourlyRate'] : null);
			$project->setCurrencySymbol((string) ($projectData['currencySymbol'] ?? '€'));
			$project->setShowCostInSummary((bool) ($projectData['showCostInSummary'] ?? false));
			$project->setArchived((bool) ($projectData['archived'] ?? false));
			$project->setClientId($clientId);
			$project->setCreatedAt(new \DateTimeImmutable());
			$project = $this->projectMapper->insert($project);
			$counts['projects']++;

			foreach ($projectData['modules'] ?? [] as $moduleData) {
				$module = new Module();
				$module->setProjectId($project->getId());
				$module->setCode((string) ($moduleData['code'] ?? ''));
				$module->setName((string) ($moduleData['name'] ?? ''));
				$module->setInEstimate((bool) ($moduleData['inEstimate'] ?? true));
				$module->setSortOrder((int) ($moduleData['sortOrder'] ?? 0));
				$module = $this->moduleMapper->insert($module);
				$counts['modules']++;

				foreach ($moduleData['points'] ?? [] as $pointData) {
					$point = new Point();
					$point->setModuleId($module->getId());
					$point->setCode((string) ($pointData['code'] ?? ''));
					$point->setDescription((string) ($pointData['description'] ?? ''));
					$point->setEstimateH(isset($pointData['estimateH']) && $pointData['estimateH'] !== null ? (float) $pointData['estimateH'] : null);
					$point->setStatus((string) ($pointData['status'] ?? 'todo'));
					$point->setSortOrder((int) ($pointData['sortOrder'] ?? 0));
					$point = $this->pointMapper->insert($point);
					$counts['points']++;

					foreach ($pointData['leaves'] ?? [] as $leafData) {
						$leaf = new Leaf();
						$leaf->setPointId($point->getId());
						$leaf->setDescription((string) ($leafData['description'] ?? ''));
						$leaf->setWorkDate(new \DateTimeImmutable((string) ($leafData['workDate'] ?? 'today')));
						$leaf->setSortOrder((int) ($leafData['sortOrder'] ?? 0));
						$this->leafMapper->insert($leaf);
						$counts['leaves']++;
					}
				}
			}

			foreach ($projectData['dayHours'] ?? [] as $dayHoursData) {
				$dayHours = new DayHours();
				$dayHours->setProjectId($project->getId());
				$dayHours->setWorkDate(new \DateTimeImmutable((string) $dayHoursData['workDate']));
				$dayHours->setHours((float) ($dayHoursData['hours'] ?? 0));
				$this->dayHoursMapper->insert($dayHours);
				$counts['dayHours']++;
			}

			foreach ($projectData['features'] ?? [] as $featureData) {
				$feature = new Feature();
				$feature->setProjectId($project->getId());
				$feature->setSection((string) ($featureData['section'] ?? ''));
				$feature->setName((string) ($featureData['name'] ?? ''));
				$feature->setPointRef((string) ($featureData['pointRef'] ?? ''));
				$feature->setStatus((string) ($featureData['status'] ?? 'not_started'));
				$feature->setBusinessValue((string) ($featureData['businessValue'] ?? ''));
				$feature->setExternalPending((string) ($featureData['externalPending'] ?? ''));
				$feature->setSortOrder((int) ($featureData['sortOrder'] ?? 0));
				$this->featureMapper->insert($feature);
				$counts['features']++;
			}

			foreach ($projectData['tests'] ?? [] as $testData) {
				$test = new TestEntry();
				$test->setProjectId($project->getId());
				$test->setArea((string) ($testData['area'] ?? ''));
				$test->setProfile((string) ($testData['profile'] ?? ''));
				$test->setScenario((string) ($testData['scenario'] ?? ''));
				$test->setExpected((string) ($testData['expected'] ?? ''));
				$test->setStatus((string) ($testData['status'] ?? 'to_test'));
				$test->setTestDate(isset($testData['testDate']) && $testData['testDate'] !== null ? new \DateTimeImmutable((string) $testData['testDate']) : null);
				$test->setNotes((string) ($testData['notes'] ?? ''));
				$test->setSortOrder((int) ($testData['sortOrder'] ?? 0));
				$this->testEntryMapper->insert($test);
				$counts['tests']++;
			}
		}

		return $counts;
	}
}
