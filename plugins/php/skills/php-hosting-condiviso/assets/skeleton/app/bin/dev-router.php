<?php

declare(strict_types=1);

// Router per il server integrato di PHP, solo per lo sviluppo. Il server integrato ignora .htaccess:
// questo file ne riproduce le regole (app/ e file nascosti non raggiungibili, file statici serviti,
// tutto il resto al front controller).  Avvio: cd app && composer serve

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
    return false; // file statico: lo serve il server integrato
}

if (str_starts_with($path, '/assets/')) {
    http_response_code(404); // asset mancante: come in .htaccess, non passa dall'applicazione
    echo 'Not Found';

    return true;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $docroot . '/index.php';

require $docroot . '/index.php';
