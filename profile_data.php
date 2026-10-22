<?php
session_start();
require 'config/db.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit();
}

// This endpoint supplies the profile modal with details for the signed-in account only.
$stmt = $conn->prepare('SELECT full_name, email, phone_number, student_number, role, profile_picture FROM users WHERE user_id = ?');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if ($user) {
    $user['csrf_token'] = csrf_token();
}
echo json_encode($user ?: ['error' => 'User not found']);
?>
