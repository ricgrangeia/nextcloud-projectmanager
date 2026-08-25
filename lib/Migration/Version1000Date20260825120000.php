<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[AddColumn(table: 'pm_projects', name: 'archived', description: 'Whether the project is archived (hidden from the default project list, data kept)')]
class Version1000Date20260825120000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('pm_projects');
		if (!$table->hasColumn('archived')) {
			$table->addColumn('archived', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
