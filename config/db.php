<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/csrf.php';

// Local defaults are enabled only when both the request host and client address are local.
$requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\\d+$/', '', $requestHost);
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalRequest = in_array($requestHost, ['localhost', '127.0.0.1', '[::1]'], true)
    && in_array($remoteAddress, ['127.0.0.1', '::1'], true);

$host = $_ENV['DB_HOST'] ?? ($isLocalRequest ? 'localhost' : '');
$username = $_ENV['DB_USER'] ?? ($isLocalRequest ? 'root' : '');
$password = $_ENV['DB_PASS'] ?? '';
$database = $_ENV['DB_NAME'] ?? ($isLocalRequest ? 'student_service_db' : '');

if ($host === '' || $username === '' || $database === '' || (!$isLocalRequest && $password === '')) {
    error_log('Database configuration is incomplete.');
    http_response_code(500);
    exit('The application is temporarily unavailable.');
}

// Use strict mysqli errors so connection and query failures follow the configured error path.
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed.');
    http_response_code(500);
    exit('The application is temporarily unavailable.');
}
