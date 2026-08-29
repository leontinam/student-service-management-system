<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: auth/login.php'); exit(); }

$userId = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $user = $conn->prepare('SELECT password FROM users WHERE user_id = ?');
    $user->bind_param('i', $userId); $user->execute(); $account = $user->get_result()->fetch_assoc();
    if (!$account || !password_verify($current, $account['password'])) $error = 'Your current password is incorrect.';
    elseif (strlen($new) < 8) $error = 'Your new password must contain at least 8 characters.';
    elseif ($new !== $confirm) $error = 'The new passwords do not match.';
    else { $hash = password_hash($new, PASSWORD_DEFAULT); $update = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?'); $update->bind_param('si', $hash, $userId); if ($update->execute()) $message = 'Your password has been changed.'; else $error = 'Your password could not be updated. Please try again.'; }
}
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Change Password</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/style.css" rel="stylesheet"></head><body><main class="profile-page"><section class="profile-editor"><h1 class="h4 mb-1">Change Password</h1><p class="text-muted mb-4">Use a strong password with at least 8 characters.</p><?php if(isset($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><?php if(isset($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?><form method="post"><label class="form-label">Current password</label><input class="form-control mb-3" type="password" name="current_password" required><label class="form-label">New password</label><input class="form-control mb-3" type="password" name="new_password" minlength="8" required><label class="form-label">Confirm new password</label><input class="form-control mb-3" type="password" name="confirm_password" minlength="8" required><button class="btn btn-brand w-100">Change Password</button></form><a class="d-block mt-4" href="javascript:history.back()">Back</a></section></main></body></html>
