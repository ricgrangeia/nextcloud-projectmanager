<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[AddColumn(table: 'pm_clients', name: 'hourly_rate', description: 'Default hourly rate for this client, used by its projects unless they set their own')]
#[AddColumn(table: 'pm_clients', name: 'currency_symbol', description: 'Currency symbol for this client; its projects always use it')]
class Version1000Date20260826150000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('pm_clients');
		if (!$table->hasColumn('hourly_rate')) {
			$table->addColumn('hourly_rate', Types::FLOAT, ['notnull' => false]);
		}
		if (!$table->hasColumn('currency_symbol')) {
			$table->addColumn('currency_symbol', Types::STRING, ['notnull' => true, 'length' => 8, 'default' => '€']);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
