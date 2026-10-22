<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';

// GET displays a confirmation form; account activation is handled only after a valid POST.
$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['verification_token'] ?? '')
    : ($_GET['token'] ?? '');
$verificationState = is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) ? 'confirm' : 'invalid';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        csrf_reject_request();
    }

    $verificationState = 'invalid';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $lookup = $conn->prepare('SELECT user_id, verification_expires_at, (verification_expires_at <= NOW()) AS token_expired FROM users WHERE verification_token = ? AND is_verified = FALSE LIMIT 1');
    $lookup->bind_param('s', $token);
    $lookup->execute();
    $user = $lookup->get_result()->fetch_assoc();

    if ($user) {
        // Expired tokens are cleared; valid unexpired tokens activate the account once.
        if (empty($user['verification_expires_at']) || (int) $user['token_expired'] === 1) {
            $clear = $conn->prepare('UPDATE users SET verification_token = NULL, verification_expires_at = NULL WHERE user_id = ? AND verification_token = ? AND (verification_expires_at IS NULL OR verification_expires_at <= NOW())');
            $clear->bind_param('is', $user['user_id'], $token);
            $clear->execute();
            $verificationState = 'expired';
        } else {
            $activate = $conn->prepare('UPDATE users SET is_verified = TRUE, verification_token = NULL, verification_expires_at = NULL WHERE user_id = ? AND verification_token = ? AND is_verified = FALSE AND verification_expires_at > NOW()');
            $activate->bind_param('is', $user['user_id'], $token);
            $activate->execute();
            if ($activate->affected_rows === 1) {
                $verificationState = 'verified';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($verificationState === 'verified'): ?>
        <meta http-equiv="refresh" content="3;url=login.php">
    <?php endif; ?>
    <title>Email Verification - Student Service Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="split-login">
    <div class="split-login-panel">
        <div class="d-flex align-items-center gap-2">
            <div class="split-login-mark">S</div>
            <span class="fw-semibold">SSMS</span>
        </div>
        <div>
            <h1 class="h3 fw-semibold mb-3">Student Service Management System</h1>
            <p class="small" style="max-width: 320px; opacity: 0.85;">Your account security matters to us.</p>
        </div>
        <div></div>
    </div>

    <div class="split-login-form">
        <div class="split-login-form-inner text-center">
            <?php if ($verificationState === 'verified'): ?>
                <h2 class="h4 fw-semibold mb-3">Email verified</h2>
                <p class="text-muted mb-4">Your email address has been verified successfully. You can now log in. You will be redirected to the login page in 3 seconds.</p>
                <a href="login.php" class="btn btn-brand w-100">Log In now</a>
            <?php elseif ($verificationState === 'expired'): ?>
                <h2 class="h4 fw-semibold mb-3">Verification link expired</h2>
                <p class="text-muted mb-4">This verification link has expired. Request a new link to activate your account.</p>
                <a href="resend_verification.php" class="btn btn-brand w-100 mb-3">Resend verification email</a>
                <a href="login.php">Back to login</a>
            <?php elseif ($verificationState === 'confirm'): ?>
                <h2 class="h4 fw-semibold mb-3">Confirm your email address</h2>
                <p class="text-muted mb-4">Select the button below to verify your email and activate your account.</p>
                <form method="post" action="verify.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="verification_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-brand w-100">Verify email</button>
                </form>
            <?php else: ?>
                <h2 class="h4 fw-semibold mb-3">Verification link is invalid or already used</h2>
                <p class="text-muted mb-4">This verification link is invalid or has already been used. If your account is not verified, request a new link.</p>
                <a href="resend_verification.php" class="btn btn-brand w-100 mb-3">Resend verification email</a>
                <a href="login.php" class="btn btn-brand w-100">Back to Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
