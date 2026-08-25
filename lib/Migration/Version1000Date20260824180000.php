<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[AddColumn(table: 'pm_projects', name: 'hourly_rate', description: 'Optional hourly rate used to compute cost in the summary')]
#[AddColumn(table: 'pm_projects', name: 'currency_symbol', description: 'Currency symbol shown after cost values')]
#[AddColumn(table: 'pm_projects', name: 'show_cost_in_summary', description: 'Whether the summary shows a cost column computed from hourly_rate')]
class Version1000Date20260824180000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('pm_projects');
		if (!$table->hasColumn('hourly_rate')) {
			$table->addColumn('hourly_rate', Types::FLOAT, ['notnull' => false]);
		}
		if (!$table->hasColumn('currency_symbol')) {
			$table->addColumn('currency_symbol', Types::STRING, ['notnull' => true, 'length' => 8, 'default' => '€']);
		}
		if (!$table->hasColumn('show_cost_in_summary')) {
			$table->addColumn('show_cost_in_summary', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
