<?php
session_start();
require '../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!csrf_validate_request()) {
    csrf_reject_request(true);
}

// Validate the selected notification ID before changing a record owned by this user.
$notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
if (!$notificationId) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid notification']);
    exit();
}

$markRead = $conn->prepare('UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ? AND is_read = FALSE');
$markRead->bind_param('ii', $notificationId, $_SESSION['user_id']);
$markRead->execute();

$count = $conn->prepare('SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = FALSE');
$count->bind_param('i', $_SESSION['user_id']);
$count->execute();

echo json_encode(['count' => (int) $count->get_result()->fetch_assoc()['count']]);
