<?php

$publicRoot = realpath(__DIR__);
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$requestedFile = realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim($requestPath, '/\\'));

if (
    $requestPath !== '/'
    && $requestedFile !== false
    && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    return false;
}

require $publicRoot . DIRECTORY_SEPARATOR . 'index.php';