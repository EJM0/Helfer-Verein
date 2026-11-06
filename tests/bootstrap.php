<?php

declare(strict_types=1);

// Inside a Nextcloud server checkout (apps/helfer) use the server bootstrap,
// otherwise the OCP stubs from composer are enough for the unit tests.
$serverBootstrap = __DIR__ . '/../../../tests/bootstrap.php';
if (is_file($serverBootstrap)) {
	require_once $serverBootstrap;
}
require_once __DIR__ . '/../vendor/autoload.php';

// Make the OCP stubs loadable when running without a server.
spl_autoload_register(static function (string $class): void {
	if (str_starts_with($class, 'OCP\\')) {
		$file = __DIR__ . '/../vendor/nextcloud/ocp/' . str_replace('\\', '/', $class) . '.php';
		if (is_file($file)) {
			require_once $file;
		}
	}
});
