<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[CreateTable(table: 'pm_clients', description: 'Clients owned by a user, grouping projects')]
#[AddColumn(table: 'pm_projects', name: 'client_id', description: 'Optional client this project belongs to')]
class Version1000Date20260826120000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('pm_clients')) {
			$table = $schema->createTable('pm_clients');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'pm_clients_uid_idx');
		}

		$projects = $schema->getTable('pm_projects');
		if (!$projects->hasColumn('client_id')) {
			$projects->addColumn('client_id', Types::BIGINT, ['notnull' => false, 'length' => 20, 'unsigned' => true]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
