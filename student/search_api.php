<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Search results are available only to authenticated student accounts.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require '../config/db.php';

$keyword = trim($_GET['keyword'] ?? '');
$search_term = '%' . $keyword . '%';

$student_id = (int) $_SESSION['user_id'];

// Return matching available listings together with rating and current-user favorite data.
$sql = "SELECT services.service_id, services.title, services.description,
               categories.category_name, users.full_name, users.phone_number, users.email,
               COALESCE(AVG(reviews.rating), 0) AS average_rating,
               COUNT(reviews.review_id) AS rating_count,
               MAX(CASE WHEN reviews.student_id = ? THEN reviews.rating END) AS user_rating,
               MAX(favorites.favorite_id) IS NOT NULL AS is_favorite
        FROM services
        JOIN categories ON services.category_id = categories.category_id
        JOIN users ON services.provider_id = users.user_id
        LEFT JOIN reviews ON reviews.service_id = services.service_id
        LEFT JOIN favorites ON favorites.service_id = services.service_id AND favorites.student_id = ?
        WHERE services.availability_status = 'available'
          AND services.status = 'approved'
          AND (services.title LIKE ? OR services.description LIKE ?)
        GROUP BY services.service_id, services.title, services.description, categories.category_name, users.full_name, users.phone_number, users.email
        ORDER BY services.title ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to search services']);
    exit();
}

$stmt->bind_param('iiss', $student_id, $student_id, $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();

$services = [];
while ($service = $result->fetch_assoc()) {
    $services[] = $service;
}

echo json_encode($services);
