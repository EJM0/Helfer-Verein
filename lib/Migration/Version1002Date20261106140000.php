<?php

declare(strict_types=1);

namespace OCA\Helfer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Start date and organiser info for events, donation lists (e.g. a cake list).
 */
class Version1002Date20261106140000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$events = $schema->getTable('helfer_events');
		if (!$events->hasColumn('start_date')) {
			$events->addColumn('start_date', Types::BIGINT, ['notnull' => false]);
		}
		if (!$events->hasColumn('organizer')) {
			$events->addColumn('organizer', Types::STRING, ['notnull' => false, 'length' => 255]);
		}
		if (!$events->hasColumn('contact_email')) {
			$events->addColumn('contact_email', Types::STRING, ['notnull' => false, 'length' => 255]);
		}

		if (!$schema->hasTable('helfer_donation_lists')) {
			$table = $schema->createTable('helfer_donation_lists');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('needed', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('sort', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'helfer_dl_event');
		}

		if (!$schema->hasTable('helfer_donations')) {
			$table = $schema->createTable('helfer_donations');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('event_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('item', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('comment', Types::TEXT, ['notnull' => false]);
			$table->addColumn('edit_token', Types::STRING, ['notnull' => false, 'length' => 128]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['event_id'], 'helfer_do_event');
			$table->addIndex(['list_id'], 'helfer_do_list');
		}

		return $schema;
	}
}
