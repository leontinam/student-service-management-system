<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require '../config/db.php';

$keyword = trim($_GET['keyword'] ?? '');
$search_term = '%' . $keyword . '%';

$sql = "SELECT services.service_id, services.title, services.description,
               categories.category_name, users.full_name, users.phone_number, users.email
        FROM services
        JOIN categories ON services.category_id = categories.category_id
        JOIN users ON services.provider_id = users.user_id
        WHERE services.availability_status = 'available'
          AND services.status = 'approved'
          AND (services.title LIKE ? OR services.description LIKE ?)
        ORDER BY services.title ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to search services']);
    exit();
}

$stmt->bind_param('ss', $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();

$services = [];
while ($service = $result->fetch_assoc()) {
    $services[] = $service;
}

echo json_encode($services);
