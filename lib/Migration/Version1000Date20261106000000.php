<?php

declare(strict_types=1);

namespace OCA\Helfer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial schema: events (Umfragen) with shifts, stations, slots and signups.
 */
class Version1000Date20261106000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('helfer_events')) {
			$table = $schema->createTable('helfer_events');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('owner_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('location', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('contact', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('token', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('timezone', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'UTC']);
			$table->addColumn('end_date', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('close_date', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('close_action', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'close']);
			$table->addColumn('closed_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('export_folder', Types::STRING, ['notnull' => false, 'length' => 4000]);
			$table->addColumn('exported_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('export_path', Types::STRING, ['notnull' => false, 'length' => 4000]);
			$table->addColumn('ask_email', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			$table->addColumn('ask_phone', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('ask_group', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('group_options', Types::TEXT, ['notnull' => false]);
			$table->addColumn('questions', Types::TEXT, ['notnull' => false]);
			$table->addColumn('public_names', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner_uid'], 'helfer_ev_owner');
			$table->addUniqueIndex(['token'], 'helfer_ev_token');
		}

		if (!$schema->hasTable('helfer_shifts')) {
			$table = $schema->createTable('helfer_shifts');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('start_time', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('end_time', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'helfer_sh_event');
		}

		if (!$schema->hasTable('helfer_stations')) {
			$table = $schema->createTable('helfer_stations');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('sort', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'helfer_st_event');
		}

		if (!$schema->hasTable('helfer_slots')) {
			$table = $schema->createTable('helfer_slots');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('shift_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('station_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('needed', Types::INTEGER, ['notnull' => true, 'default' => 1]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'helfer_sl_event');
			$table->addUniqueIndex(['shift_id', 'station_id'], 'helfer_sl_cell');
		}

		if (!$schema->hasTable('helfer_signups')) {
			$table = $schema->createTable('helfer_signups');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('slot_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('phone', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('group_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('role', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('comment', Types::TEXT, ['notnull' => false]);
			$table->addColumn('answers', Types::TEXT, ['notnull' => false]);
			$table->addColumn('edit_token', Types::STRING, ['notnull' => false, 'length' => 128]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'helfer_su_event');
			$table->addIndex(['slot_id'], 'helfer_su_slot');
		}

		return $schema;
	}
}
