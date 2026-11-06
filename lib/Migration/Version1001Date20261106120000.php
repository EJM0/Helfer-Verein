<?php

declare(strict_types=1);

namespace OCA\Helfer\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * E-mail is optional by default and can be made mandatory per survey.
 */
class Version1001Date20261106120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('helfer_events');
		if (!$table->hasColumn('email_required')) {
			$table->addColumn('email_required', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			return $schema;
		}
		return null;
	}
}
