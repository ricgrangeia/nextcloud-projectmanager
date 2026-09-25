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

#[AddColumn(table: 'pm_projects', name: 'phase', description: 'Where the project currently stands, e.g. development or client_review')]
#[AddColumn(table: 'pm_projects', name: 'brief', description: 'Short project brief: objective, scope, acceptance criteria')]
#[AddColumn(table: 'pm_projects', name: 'waiting_on_client', description: 'What is currently blocked on the client\'s side')]
#[AddColumn(table: 'pm_projects', name: 'update_every_days', description: 'How often the client should be sent a status update, in days (0 disables the reminder)')]
#[AddColumn(table: 'pm_points', name: 'client_visible', description: 'Whether this point is something the client sees/cares about')]
#[AddColumn(table: 'pm_clients', name: 'email', description: 'Client contact email, used to draft status update emails')]
#[CreateTable(table: 'pm_milestones', description: 'Project milestones with a target date and, once reached, a reached date')]
class Version1000Date20260925120000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$projects = $schema->getTable('pm_projects');
		if (!$projects->hasColumn('phase')) {
			$projects->addColumn('phase', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'development']);
		}
		if (!$projects->hasColumn('brief')) {
			$projects->addColumn('brief', Types::TEXT, ['notnull' => false]);
		}
		if (!$projects->hasColumn('waiting_on_client')) {
			$projects->addColumn('waiting_on_client', Types::TEXT, ['notnull' => false]);
		}
		if (!$projects->hasColumn('update_every_days')) {
			$projects->addColumn('update_every_days', Types::INTEGER, ['notnull' => true, 'default' => 7]);
		}

		$points = $schema->getTable('pm_points');
		if (!$points->hasColumn('client_visible')) {
			$points->addColumn('client_visible', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		}

		$clients = $schema->getTable('pm_clients');
		if (!$clients->hasColumn('email')) {
			$clients->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255]);
		}

		if (!$schema->hasTable('pm_milestones')) {
			$table = $schema->createTable('pm_milestones');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('project_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('target_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
			$table->addColumn('reached_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['project_id'], 'pm_milestones_pid_idx');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
