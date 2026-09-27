<?php

declare(strict_types=1);

// Prepares in build/ the copy to upload via FTP to the document root, with no-dev dependencies.
//   cd app && composer build
// Does not include app/config/settings.local.php or the contents of app/var/: the existing ones stay on the server.
// Upload build/ without "delete missing remote files", or exclude app/var/ and settings.local.php from synchronization.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__, 2);
$target = $root . '/build';
$composer = getenv('COMPOSER_BINARY') ?: 'composer';

/** Paths (relative to the root) that are never copied. */
$excluded = [
    'build', '.git', '.idea', '.vscode', 'node_modules', '.DS_Store', 'README.md', '.gitignore',
    'app/vendor', 'app/tests', 'app/.phpunit.cache', 'app/.php-cs-fixer.cache',
    'app/config/settings.local.php', 'app/phpunit.xml.dist', 'app/phpstan.neon.dist', 'app/.php-cs-fixer.dist.php',
];

$isExcluded = static function (string $relative) use ($excluded, $root): bool {
    foreach ($excluded as $path) {
        if ($relative === $path || str_starts_with($relative, $path . '/') || basename($relative) === '.DS_Store') {
            return true;
        }
    }
    // From app/var/ only the folders and the .gitignore files are copied, not data, logs, sessions or cache.
    return str_starts_with($relative, 'app/var/') && !is_dir($root . '/' . $relative) && basename($relative) !== '.gitignore';
};

$remove = static function (string $dir) use (&$remove): void {
    foreach (new FilesystemIterator($dir) as $item) {
        assert($item instanceof SplFileInfo);
        $item->isDir() && !$item->isLink() ? $remove($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
};

if (is_dir($target)) {
    $remove($target);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);
$count = 0;
foreach ($iterator as $item) {
    assert($item instanceof SplFileInfo);
    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
    if ($isExcluded($relative)) {
        continue;
    }
    $destination = $target . '/' . $relative;
    if ($item->isDir()) {
        is_dir($destination) || mkdir($destination, 0o755, true);
    } else {
        is_dir(dirname($destination)) || mkdir(dirname($destination), 0o755, true);
        copy($item->getPathname(), $destination);
        $count++;
    }
}

// Arguments as an array: no shell involved.
$process = proc_open(
    [$composer, 'install', '--no-dev', '--classmap-authoritative', '--no-interaction', '--no-progress', '--working-dir=' . $target . '/app'],
    [1 => STDOUT, 2 => STDERR],
    $pipes,
);
$exit = is_resource($process) ? proc_close($process) : 1;

if ($exit !== 0) {
    fwrite(STDERR, 'composer install failed' . PHP_EOL);
    exit(1);
}

echo PHP_EOL . "Build ready in {$target} ({$count} project files + vendor)." . PHP_EOL
    . 'Upload the contents of build/ to the document root; do not overwrite app/var/ and app/config/settings.local.php.' . PHP_EOL;
