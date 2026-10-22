<?php
session_start();
require 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be logged in to update your profile.']);
    exit();
}

if (!csrf_validate_request()) {
    csrf_reject_request(true);
}

// Profile edits apply only to the authenticated account represented by this session.
$userId = (int) $_SESSION['user_id'];
$fullName = trim($_POST['full_name'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');

if ($fullName === '' || mb_strlen($fullName) > 100) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a name of up to 100 characters.']);
    exit();
}

if (!preg_match('/^\d{10}$/', $phoneNumber)) {
    http_response_code(422);
    echo json_encode(['error' => 'Phone number must be exactly 10 digits.']);
    exit();
}

$userQuery = $conn->prepare('SELECT profile_picture FROM users WHERE user_id = ?');
$userQuery->bind_param('i', $userId);
$userQuery->execute();
$currentUser = $userQuery->get_result()->fetch_assoc();

if (!$currentUser) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found.']);
    exit();
}

$newPicturePath = null;
$uploadedPicturePath = null;
// Validate the uploaded image's size and detected content type before saving it.
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['profile_picture'];
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 2 * 1024 * 1024) {
        http_response_code(422);
        echo json_encode(['error' => 'Upload a JPG, PNG, or WebP image up to 2 MB.']);
        exit();
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowedTypes[$mimeType]) || !@getimagesize($file['tmp_name'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Upload a valid JPG, PNG, or WebP image.']);
        exit();
    }

    $uploadDirectory = __DIR__ . '/assets/uploads/profile_pictures';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        http_response_code(500);
        echo json_encode(['error' => 'The profile picture folder could not be created.']);
        exit();
    }

    $newPicturePath = 'assets/uploads/profile_pictures/' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $uploadedPicturePath = __DIR__ . '/' . $newPicturePath;
    if (!move_uploaded_file($file['tmp_name'], $uploadedPicturePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'The profile picture could not be saved.']);
        exit();
    }
}

// Update text fields and the picture path together; remove a newly saved file if the DB update fails.
try {
    if ($newPicturePath !== null) {
        $update = $conn->prepare('UPDATE users SET full_name = ?, phone_number = ?, profile_picture = ? WHERE user_id = ?');
        $update->bind_param('sssi', $fullName, $phoneNumber, $newPicturePath, $userId);
    } else {
        $update = $conn->prepare('UPDATE users SET full_name = ?, phone_number = ? WHERE user_id = ?');
        $update->bind_param('ssi', $fullName, $phoneNumber, $userId);
    }
    $update->execute();
} catch (Throwable $exception) {
    if ($uploadedPicturePath !== null && is_file($uploadedPicturePath)) {
        unlink($uploadedPicturePath);
    }
    error_log('Profile update failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Your profile could not be updated. Please try again.']);
    exit();
}

// Remove the previous image only after the replacement path has been stored successfully.
if ($newPicturePath !== null && !empty($currentUser['profile_picture'])) {
    $oldPicturePath = __DIR__ . '/' . $currentUser['profile_picture'];
    if (is_file($oldPicturePath)) {
        unlink($oldPicturePath);
    }
}

$profileQuery = $conn->prepare('SELECT full_name, email, phone_number, student_number, role, profile_picture FROM users WHERE user_id = ?');
$profileQuery->bind_param('i', $userId);
$profileQuery->execute();
$updatedUser = $profileQuery->get_result()->fetch_assoc();

$_SESSION['full_name'] = $updatedUser['full_name'];
$_SESSION['phone_number'] = $updatedUser['phone_number'];
$_SESSION['profile_picture'] = $updatedUser['profile_picture'];

echo json_encode([
    'message' => 'Your profile has been updated.',
    'user' => $updatedUser
]);
