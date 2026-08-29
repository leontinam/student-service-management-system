<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT full_name, email, phone_number, profile_picture FROM users WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_picture'])) {
        if (!empty($user['profile_picture'])) {
            $oldPath = __DIR__ . '/' . $user['profile_picture'];
            if (is_file($oldPath)) unlink($oldPath);
        }
        $update = $conn->prepare('UPDATE users SET profile_picture = NULL WHERE user_id = ?');
        $update->bind_param('i', $userId);
        $update->execute();
        $message = 'Profile picture removed.';
    } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($file['size'] > 2 * 1024 * 1024 || !isset($allowed[$mime]) || !@getimagesize($file['tmp_name'])) {
            $error = 'Upload a JPG, PNG, or WebP image up to 2 MB.';
        } else {
            $directory = __DIR__ . '/assets/uploads/profile_pictures';
            if (!is_dir($directory)) mkdir($directory, 0755, true);
            $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            $relativePath = 'assets/uploads/profile_pictures/' . $filename;
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $relativePath)) {
                if (!empty($user['profile_picture'])) {
                    $oldPath = __DIR__ . '/' . $user['profile_picture'];
                    if (is_file($oldPath)) unlink($oldPath);
                }
                $update = $conn->prepare('UPDATE users SET profile_picture = ? WHERE user_id = ?');
                $update->bind_param('si', $relativePath, $userId);
                $update->execute();
                $message = 'Profile picture updated.';
            } else $error = 'The picture could not be saved.';
        }
    }
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit Profile</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/style.css" rel="stylesheet"></head><body><main class="profile-page"><section class="profile-editor"><h1 class="h4">Edit Profile</h1><?php if (isset($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><?php if (isset($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?><p class="text-muted mb-4"><?php echo htmlspecialchars($user['full_name']); ?><br><?php echo htmlspecialchars($user['email']); ?><br><?php echo htmlspecialchars($user['phone_number']); ?></p><form method="post" enctype="multipart/form-data"><label class="form-label">Profile picture</label><input class="form-control mb-3" type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp"><button class="btn btn-brand" type="submit">Upload picture</button></form><?php if (!empty($user['profile_picture'])): ?><form method="post" class="mt-3"><button class="btn btn-outline-danger" name="remove_picture" value="1" type="submit">Remove picture</button></form><?php endif; ?><a class="d-block mt-4" href="javascript:history.back()">Back</a></section></main></body></html>
