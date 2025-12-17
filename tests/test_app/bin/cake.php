#!/usr/bin/php -q
<?php
declare(strict_types=1);

// Bootstrap the test environment
require __DIR__ . '/../../bootstrap.php';

use Cake\Console\CommandRunner;
use TestApp\Application;

// Build the runner with an application and root executable name.
$runner = new CommandRunner(new Application(ROOT), 'cake');
exit($runner->run($argv));
