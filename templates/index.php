<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Helfer\AppInfo\Application::APP_ID, OCA\Helfer\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\Helfer\AppInfo\Application::APP_ID, OCA\Helfer\AppInfo\Application::APP_ID . '-main');

?>

<div id="helfer"></div>
