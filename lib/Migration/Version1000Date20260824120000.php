<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[CreateTable(table: 'pm_projects', description: 'Projects owned by a user')]
#[CreateTable(table: 'pm_modules', description: 'Grouping modules within a project (e.g. P1, P2, OTHERS)')]
#[CreateTable(table: 'pm_points', description: 'Estimable work points within a module')]
#[CreateTable(table: 'pm_leaves', description: 'Dated work log entries for a point')]
#[CreateTable(table: 'pm_day_hours', description: 'Actual hours worked on a given day of a project')]
#[CreateTable(table: 'pm_features', description: 'Sold features tracked against status')]
#[CreateTable(table: 'pm_tests', description: 'Validation test log entries')]
class Version1000Date20260824120000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('pm_projects')) {
			$table = $schema->createTable('pm_projects');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('hours_per_working_day', Types::FLOAT, ['notnull' => true, 'default' => 7.0]);
			$table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'pm_projects_uid_idx');
		}

		if (!$schema->hasTable('pm_modules')) {
			$table = $schema->createTable('pm_modules');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('project_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('code', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('in_estimate', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['project_id'], 'pm_modules_pid_idx');
		}

		if (!$schema->hasTable('pm_points')) {
			$table = $schema->createTable('pm_points');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('module_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('code', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('description', Types::TEXT, ['notnull' => true]);
			$table->addColumn('estimate_h', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'todo']);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['module_id'], 'pm_points_mid_idx');
		}

		if (!$schema->hasTable('pm_leaves')) {
			$table = $schema->createTable('pm_leaves');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('point_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('description', Types::TEXT, ['notnull' => true]);
			$table->addColumn('work_date', Types::DATE_IMMUTABLE, ['notnull' => true]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['point_id'], 'pm_leaves_pid_idx');
			$table->addIndex(['work_date'], 'pm_leaves_date_idx');
		}

		if (!$schema->hasTable('pm_day_hours')) {
			$table = $schema->createTable('pm_day_hours');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('project_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('work_date', Types::DATE_IMMUTABLE, ['notnull' => true]);
			$table->addColumn('hours', Types::FLOAT, ['notnull' => true, 'default' => 0.0]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['project_id', 'work_date'], 'pm_dayhours_pid_date_idx');
		}

		if (!$schema->hasTable('pm_features')) {
			$table = $schema->createTable('pm_features');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('project_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('section', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('point_ref', Types::STRING, ['notnull' => false, 'length' => 32, 'default' => '']);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'not_started']);
			$table->addColumn('business_value', Types::TEXT, ['notnull' => false]);
			$table->addColumn('external_pending', Types::TEXT, ['notnull' => false]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['project_id'], 'pm_features_pid_idx');
		}

		if (!$schema->hasTable('pm_tests')) {
			$table = $schema->createTable('pm_tests');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('project_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('area', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('profile', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
			$table->addColumn('scenario', Types::TEXT, ['notnull' => true]);
			$table->addColumn('expected', Types::TEXT, ['notnull' => false]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'to_test']);
			$table->addColumn('test_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['project_id'], 'pm_tests_pid_idx');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
