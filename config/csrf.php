<?php

/** Return the session's reusable CSRF token, creating one when needed. */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || !preg_match('/^[a-f0-9]{64}$/', $_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Validate a submitted POST body or same-origin request header using a constant-time comparison. */
function csrf_validate_request(): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    return is_string($sessionToken)
        && preg_match('/^[a-f0-9]{64}$/', $sessionToken) === 1
        && is_string($submittedToken)
        && hash_equals($sessionToken, $submittedToken);
}

/** Render the standard hidden field used by HTML POST forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Stop an unsafe POST request before its handler can change data. */
function csrf_reject_request(bool $json = false): void
{
    http_response_code(403);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['error' => 'Your request could not be verified. Please refresh the page and try again.']));
    }
    exit('Your request could not be verified. Please refresh the page and try again.');
}
