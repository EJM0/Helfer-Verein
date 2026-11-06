<?php

declare(strict_types=1);

namespace OCA\Helfer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Internal sharing of surveys with Nextcloud users and groups.
 */
class Version1003Date20261106160000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('helfer_shares')) {
			return null;
		}
		$table = $schema->createTable('helfer_shares');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('event_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
		$table->addColumn('share_type', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('share_with', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('created_by', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['event_id'], 'helfer_sha_event');
		$table->addIndex(['share_type', 'share_with'], 'helfer_sha_with');
		$table->addUniqueIndex(['event_id', 'share_type', 'share_with'], 'helfer_sha_unique');
		return $schema;
	}
}
