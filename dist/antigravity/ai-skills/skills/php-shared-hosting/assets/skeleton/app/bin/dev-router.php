<?php

declare(strict_types=1);

// Router for PHP's built-in server, for development only. The built-in server ignores .htaccess:
// this file reproduces its rules (app/ and hidden files unreachable, static files served,
// everything else to the front controller).  Start: cd app && composer serve

$docroot = rtrim(is_string($_SERVER['DOCUMENT_ROOT'] ?? null) ? $_SERVER['DOCUMENT_ROOT'] : '', '/');
$uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
$path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));

if (preg_match('#^/app(/|$)#', $path) === 1 || preg_match('#/\.(?!well-known/)#', $path) === 1) {
    http_response_code(404);
    echo 'Not Found';

    return true;
}

$file = realpath($docroot . $path);
if ($file !== false && is_file($file) && str_starts_with($file, $docroot . '/') && !str_ends_with($file, '.php')) {
    return false; // static file: the built-in server serves it
}

if (str_starts_with($path, '/assets/')) {
    http_response_code(404); // missing asset: as in .htaccess, it does not go through the application
    echo 'Not Found';

    return true;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $docroot . '/index.php';

require $docroot . '/index.php';
