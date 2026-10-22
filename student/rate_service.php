<?php
session_start();
require '../config/db.php';
require_once __DIR__ . '/../config/rate_limit.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: browse.php');
    exit();
}

if (!csrf_validate_request()) {
    csrf_reject_request();
}

// Apply the configured per-client rating limit before processing the submitted review.
$rateLimitAction = 'rating';
$rateLimitMaximum = 5;
$rateLimitWindow = 60;
if (!rate_limit_check($conn, $rateLimitAction, $rateLimitMaximum, $rateLimitWindow)) {
    header('Location: browse.php?rating=limited');
    exit();
}
rate_limit_record($conn, $rateLimitAction, $rateLimitMaximum, $rateLimitWindow);

$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
if (!$serviceId || !$rating || $rating < 1 || $rating > 5) {
    header('Location: browse.php?rating=invalid');
    exit();
}

// Only accept ratings for services that students can currently browse.
$service = $conn->prepare("SELECT service_id, provider_id, title FROM services WHERE service_id = ? AND status = 'approved' AND availability_status = 'available'");
$service->bind_param('i', $serviceId);
$service->execute();
$serviceInfo = $service->get_result()->fetch_assoc();
if (!$serviceInfo) {
    header('Location: browse.php?rating=unavailable');
    exit();
}

$studentId = $_SESSION['user_id'];
// The unique student/service pair means another submission updates that student's rating.
$review = $conn->prepare('INSERT INTO reviews (student_id, service_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), created_at = CURRENT_TIMESTAMP');
$review->bind_param('iii', $studentId, $serviceId, $rating);

$saved = $review->execute();

if ($saved) {
    $studentName = $_SESSION['full_name'] ?? 'A student';
    $providerMessage = $studentName . ' rated your service "' . $serviceInfo['title'] . '" ' . $rating . '/5.';
    $providerNotice = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $providerNotice->bind_param('is', $serviceInfo['provider_id'], $providerMessage);
    $providerNotice->execute();

    $adminMessage = $studentName . ' rated the service "' . $serviceInfo['title'] . '" ' . $rating . '/5.';
    $adminIds = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
    $adminNotice = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    while ($admin = $adminIds->fetch_assoc()) {
        $adminNotice->bind_param('is', $admin['user_id'], $adminMessage);
        $adminNotice->execute();
    }
}

header('Location: browse.php?rating=' . ($saved ? 'saved' : 'error'));
exit();
?>
