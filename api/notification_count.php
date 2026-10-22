<?php
session_start();
require '../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit();
}

// Return the unread count for the current account to refresh navigation badges.
$stmt = $conn->prepare('SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = FALSE');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$notificationCount = (int) $stmt->get_result()->fetch_assoc()['count'];

echo json_encode(['count' => $notificationCount, 'csrf_token' => csrf_token()]);
