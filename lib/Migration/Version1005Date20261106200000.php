<?php

declare(strict_types=1);

namespace OCA\Helfer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Separate signup deadline and periodic automatic export.
 */
class Version1005Date20261106200000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('helfer_events');
		$changed = false;
		if (!$table->hasColumn('signup_deadline')) {
			$table->addColumn('signup_deadline', Types::BIGINT, ['notnull' => false]);
			$changed = true;
		}
		if (!$table->hasColumn('export_periodic')) {
			$table->addColumn('export_periodic', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			$changed = true;
		}
		return $changed ? $schema : null;
	}
}
