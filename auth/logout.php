<?php
    // End the current session before returning the user to the login page.
    session_start();
    require_once __DIR__ . '/../config/csrf.php';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    if (!csrf_validate_request()) {
        csrf_reject_request();
    }
    session_destroy();
    header("Location: login.php");
    exit();
?>
