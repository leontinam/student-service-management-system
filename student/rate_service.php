<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: browse.php');
    exit();
}

$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
if (!$serviceId || !$rating || $rating < 1 || $rating > 5) {
    header('Location: browse.php?rating=invalid');
    exit();
}

$service = $conn->prepare("SELECT service_id FROM services WHERE service_id = ? AND status = 'approved' AND availability_status = 'available'");
$service->bind_param('i', $serviceId);
$service->execute();
if (!$service->get_result()->fetch_assoc()) {
    header('Location: browse.php?rating=unavailable');
    exit();
}

$studentId = $_SESSION['user_id'];
$review = $conn->prepare('INSERT INTO reviews (student_id, service_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), created_at = CURRENT_TIMESTAMP');
$review->bind_param('iii', $studentId, $serviceId, $rating);

header('Location: browse.php?rating=' . ($review->execute() ? 'saved' : 'error'));
exit();
?>
