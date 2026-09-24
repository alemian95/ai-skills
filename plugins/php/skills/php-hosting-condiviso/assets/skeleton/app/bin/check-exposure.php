<?php

declare(strict_types=1);

// Verifica, dal tuo computer, che il sito pubblicato non esponga file privati.
//   php app/bin/check-exposure.php https://www.example.com
// Da eseguire dopo ogni modifica a .htaccess o alla struttura e dopo il primo deploy.
// Non usa codice dell'applicazione: funziona anche prima di "composer install".

if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
    fwrite(STDERR, 'Uso: php check-exposure.php <url-base>' . PHP_EOL);
    exit(2);
}

$base = rtrim($argv[1], '/');

/** Percorsi che devono rispondere 403 o 404. */
$private = [
    'app/', 'app/.htaccess', 'app/bootstrap.php', 'app/web.php', 'app/composer.json', 'app/composer.lock',
    'app/config/settings.php', 'app/config/settings.local.php', 'app/config/settings.local.php.dist',
    'app/var/database.sqlite', 'app/var/log/', 'app/var/log/app-' . date('Y-m-d') . '.log', 'app/var/log/php-errors.log',
    'app/var/sessions/', 'app/var/migrate.lock',
    'app/vendor/autoload.php', 'app/vendor/composer/installed.json', 'app/bin/migrate.php',
    'app/templates/layout.html.twig', 'app/migrations/', 'app/src/Config/Settings.php',
    '.htaccess', '.user.ini', '.env', '.git/config', '.git/HEAD', 'assets/.htaccess',
];

$context = stream_context_create(['http' => [
    'method' => 'GET',
    'ignore_errors' => true,
    'follow_location' => 0,
    'timeout' => 10,
    'header' => "User-Agent: check-exposure\r\n",
]]);

$status = static function (string $url) use ($context): array {
    $body = @file_get_contents($url, false, $context);
    $headers = http_get_last_response_headers() ?? [];
    preg_match('#^HTTP/\S+\s+(\d{3})#', $headers[0] ?? '', $m);

    return [(int) ($m[1] ?? 0), is_string($body) ? $body : ''];
};

$failures = 0;

[$code] = $status($base . '/');
printf("%-50s %s %s\n", '/', $code, $code === 200 ? 'ok' : 'ATTENZIONE: la home non risponde 200');
$failures += $code === 200 ? 0 : 1;

foreach ($private as $path) {
    [$code, $body] = $status($base . '/' . $path);
    $ok = in_array($code, [403, 404], true);
    printf("%-50s %s %s\n", '/' . $path, $code, $ok ? 'ok' : 'ESPOSTO');
    $failures += $ok ? 0 : 1;
}

[$code, $body] = $status($base . '/assets/');
$listing = $code === 200 && str_contains($body, 'Index of');
printf("%-50s %s %s\n", '/assets/ (elenco file)', $code, $listing ? 'ESPOSTO' : 'ok');
$failures += $listing ? 1 : 0;

echo PHP_EOL . ($failures === 0 ? 'Nessun file privato esposto.' : "{$failures} problemi trovati.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
