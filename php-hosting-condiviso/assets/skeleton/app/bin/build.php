<?php

declare(strict_types=1);

// Prepara in build/ la copia da caricare via FTP nella document root, con dipendenze senza dev.
//   cd app && composer build
// Non include app/config/settings.local.php né il contenuto di app/var/: sul server restano quelli esistenti.
// Carica build/ senza "elimina file remoti mancanti", oppure escludi app/var/ e settings.local.php dalla sincronizzazione.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__, 2);
$target = $root . '/build';
$composer = getenv('COMPOSER_BINARY') ?: 'composer';

/** Percorsi (relativi alla radice) mai copiati. */
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
    // Di app/var/ si copiano solo le cartelle e i .gitignore, non dati, log, sessioni o cache.
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

// Argomenti come array: nessuna shell coinvolta.
$process = proc_open(
    [$composer, 'install', '--no-dev', '--classmap-authoritative', '--no-interaction', '--no-progress', '--working-dir=' . $target . '/app'],
    [1 => STDOUT, 2 => STDERR],
    $pipes,
);
$exit = is_resource($process) ? proc_close($process) : 1;

if ($exit !== 0) {
    fwrite(STDERR, 'composer install non riuscito' . PHP_EOL);
    exit(1);
}

echo PHP_EOL . "Build pronta in {$target} ({$count} file del progetto + vendor)." . PHP_EOL
    . 'Carica il contenuto di build/ nella document root; non sovrascrivere app/var/ e app/config/settings.local.php.' . PHP_EOL;
