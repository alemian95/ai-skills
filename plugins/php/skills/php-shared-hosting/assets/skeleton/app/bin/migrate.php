<?php

declare(strict_types=1);

// Applies pending migrations. Designed for the panel's cron (no SSH access):
//   /path/php84 /home/user/public_html/app/bin/migrate.php
// Options: --status (lists without applying).
// Warning: the cron's "php" is often NOT the same version as the site's. Use the full path
// of the 8.4 executable shown by the panel; with an old version Composer stops with a clear message.

use App\Database\Migrator;
use Psr\Container\ContainerInterface;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

try {
    /** @var ContainerInterface $container */
    $container = require dirname(__DIR__) . '/bootstrap.php';
    $migrator = $container->get(Migrator::class);
    assert($migrator instanceof Migrator);

    if (in_array('--status', $argv, true)) {
        foreach ($migrator->status() as $version => $applied) {
            echo ($applied ? '[x] ' : '[ ] ') . $version . PHP_EOL;
        }
        exit(0);
    }

    $applied = $migrator->migrate();
    // No output when there is nothing to do: cron does not send empty emails.
    foreach ($applied as $version) {
        echo 'Applied: ' . $version . PHP_EOL;
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
