<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\Attributes\DropColumn;
use OCP\Migration\Attributes\DropTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Step 2 of 2 of the Feature→Point merge (see Version1000Date20260926090000,
 * which runs first and copies every Feature onto a Point). Only DDL, now that
 * the data has already been copied over.
 */
#[DropTable(table: 'pm_features', description: 'Superseded by Point.businessValue/externalPending')]
#[DropColumn(table: 'pm_points', name: 'client_visible', description: 'Superseded by Point.milestoneId')]
class Version1000Date20260926093000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('pm_features')) {
			$schema->dropTable('pm_features');
		}

		$points = $schema->getTable('pm_points');
		if ($points->hasColumn('client_visible')) {
			$points->dropColumn('client_visible');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
