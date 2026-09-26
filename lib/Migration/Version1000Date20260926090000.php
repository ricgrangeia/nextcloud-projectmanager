<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Migration;

use Closure;
use OCA\ProjectManager\Db\FeatureMapper;
use OCA\ProjectManager\Db\Module;
use OCA\ProjectManager\Db\ModuleMapper;
use OCA\ProjectManager\Db\Point;
use OCA\ProjectManager\Db\PointMapper;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Step 1 of 2 of the Feature→Point merge: adds the new columns (schema only)
 * and, once they exist, copies every Feature's business data onto a Point.
 * The old `pm_features` table and `pm_points.client_visible` column are only
 * dropped in the next migration, once this data is safely copied over.
 */
#[AddColumn(table: 'pm_points', name: 'business_value', description: 'Business value, merged in from the former Feature concept')]
#[AddColumn(table: 'pm_points', name: 'external_pending', description: 'External pending dependency, merged in from the former Feature concept')]
#[AddColumn(table: 'pm_points', name: 'milestone_id', description: 'Milestone this point was presented to the client in, replacing the old client_visible flag')]
#[AddColumn(table: 'pm_milestones', name: 'communication_channel', description: 'How the client was told about this milestone: email or session')]
#[AddColumn(table: 'pm_milestones', name: 'client_status', description: 'pending, presented or validated by the client')]
#[AddColumn(table: 'pm_milestones', name: 'acceptance_pct', description: 'Perceived client acceptance, 0-100')]
#[AddColumn(table: 'pm_tests', name: 'point_id', description: 'Point this test validates; null means a general, unscoped test')]
class Version1000Date20260926090000 extends SimpleMigrationStep {
	public function __construct(
		private ModuleMapper $moduleMapper,
		private PointMapper $pointMapper,
		private FeatureMapper $featureMapper,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$points = $schema->getTable('pm_points');
		if (!$points->hasColumn('business_value')) {
			$points->addColumn('business_value', Types::TEXT, ['notnull' => false]);
		}
		if (!$points->hasColumn('external_pending')) {
			$points->addColumn('external_pending', Types::TEXT, ['notnull' => false]);
		}
		if (!$points->hasColumn('milestone_id')) {
			$points->addColumn('milestone_id', Types::BIGINT, ['notnull' => false, 'length' => 20, 'unsigned' => true]);
		}

		$milestones = $schema->getTable('pm_milestones');
		if (!$milestones->hasColumn('communication_channel')) {
			$milestones->addColumn('communication_channel', Types::STRING, ['notnull' => false, 'length' => 16]);
		}
		if (!$milestones->hasColumn('client_status')) {
			$milestones->addColumn('client_status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'pending']);
		}
		if (!$milestones->hasColumn('acceptance_pct')) {
			$milestones->addColumn('acceptance_pct', Types::INTEGER, ['notnull' => false]);
		}

		$tests = $schema->getTable('pm_tests');
		if (!$tests->hasColumn('point_id')) {
			$tests->addColumn('point_id', Types::BIGINT, ['notnull' => false, 'length' => 20, 'unsigned' => true]);
		}

		return $schema;
	}

	/**
	 * Merges every Feature into a Point: matched by `pointRef` when possible
	 * (filling in blanks only, never overwriting), otherwise a new Module +
	 * Point are created for it. Runs once the new columns above exist.
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->featureMapper->hasAny()) {
			return;
		}

		$featuresByProject = $this->featureMapper->findAllGroupedByProject();
		foreach ($featuresByProject as $projectId => $features) {
			$modules = $this->moduleMapper->findAllForProject($projectId);
			$moduleIds = array_map(static fn (Module $m) => $m->getId(), $modules);
			$points = $this->pointMapper->findAllForModules($moduleIds);

			$pointsByCode = [];
			foreach ($points as $point) {
				$pointsByCode[$point->getCode()] = $point;
			}
			$modulesByName = [];
			foreach ($modules as $module) {
				$modulesByName[mb_strtolower(trim($module->getName()))] = $module;
			}

			$moduleSortOrder = count($modules);
			$pointSortOrderByModule = [];
			foreach ($points as $point) {
				$pointSortOrderByModule[$point->getModuleId()] = max($pointSortOrderByModule[$point->getModuleId()] ?? 0, $point->getSortOrder() + 1);
			}

			foreach ($features as $feature) {
				$pointRef = trim($feature->getPointRef());
				$matched = $pointRef !== '' ? ($pointsByCode[$pointRef] ?? null) : null;

				if ($matched !== null) {
					$changed = false;
					if (trim((string) $matched->getBusinessValue()) === '' && trim((string) $feature->getBusinessValue()) !== '') {
						$matched->setBusinessValue($feature->getBusinessValue());
						$changed = true;
					}
					if (trim((string) $matched->getExternalPending()) === '' && trim((string) $feature->getExternalPending()) !== '') {
						$matched->setExternalPending($feature->getExternalPending());
						$changed = true;
					}
					if ($changed) {
						$this->pointMapper->update($matched);
					}
					continue;
				}

				$sectionName = trim($feature->getSection()) !== '' ? trim($feature->getSection()) : 'OTHERS';
				$moduleKey = mb_strtolower($sectionName);
				$module = $modulesByName[$moduleKey] ?? null;
				if ($module === null) {
					$module = new Module();
					$module->setProjectId($projectId);
					$module->setCode(mb_strtoupper(mb_substr($sectionName, 0, 3)));
					$module->setName($sectionName);
					$module->setInEstimate(true);
					$module->setSortOrder($moduleSortOrder++);
					$module = $this->moduleMapper->insert($module);
					$modulesByName[$moduleKey] = $module;
					$pointSortOrderByModule[$module->getId()] = 0;
				}

				$pointIndex = ($pointSortOrderByModule[$module->getId()] ?? 0) + 1;

				$point = new Point();
				$point->setModuleId($module->getId());
				$point->setCode($module->getCode() . '.' . $pointIndex);
				$point->setDescription($feature->getName());
				$point->setEstimateH(null);
				$point->setStatus($feature->getStatus() === 'not_started' ? 'todo' : $feature->getStatus());
				$point->setSortOrder($pointSortOrderByModule[$module->getId()] ?? 0);
				$point->setBusinessValue($feature->getBusinessValue());
				$point->setExternalPending($feature->getExternalPending());
				$this->pointMapper->insert($point);
				$pointSortOrderByModule[$module->getId()] = ($pointSortOrderByModule[$module->getId()] ?? 0) + 1;
				$pointsByCode[$point->getCode()] = $point;
			}
		}
	}
}
