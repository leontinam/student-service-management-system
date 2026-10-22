<?php

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
} catch (Throwable $exception) {
    error_log('Environment configuration could not be loaded.');
    http_response_code(500);
    exit('The application is temporarily unavailable.');
}
