<?php

declare(strict_types=1);

namespace OCA\Helfer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Remember which device (cookie) created a signup or donation, so it can be managed after a reload.
 */
class Version1004Date20261106180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;
		foreach (['helfer_signups' => 'helfer_su_device', 'helfer_donations' => 'helfer_do_device'] as $tableName => $index) {
			$table = $schema->getTable($tableName);
			if (!$table->hasColumn('device_hash')) {
				$table->addColumn('device_hash', Types::STRING, ['notnull' => false, 'length' => 64]);
				$table->addIndex(['device_hash'], $index);
				$changed = true;
			}
		}
		return $changed ? $schema : null;
	}
}
