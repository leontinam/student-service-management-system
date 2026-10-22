<?php
    session_start();
    require '../config/db.php';
    require_once __DIR__ . '/../config/rate_limit.php';

    // Apply both account-specific and IP-wide limits before checking credentials.
    // Apply both account-specific and IP-wide limits before checking credentials.
    $accountRateLimitAction = 'login_account';
    $accountRateLimitMaximum = 5;
    // Reuse the existing IP-wide login bucket so any active legacy block remains effective.
    $ipRateLimitAction = 'login';
    $ipRateLimitMaximum = 20;
    $rateLimitWindow = 15 * 60;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_validate_request()) {
            csrf_reject_request();
        }

        $login_identifier = trim($_POST['login_identifier'] ?? '');
        $normalizedLoginIdentifier = strtolower($login_identifier);
        $loginLocks = rate_limit_acquire_locks($conn, [
            [$accountRateLimitAction, $normalizedLoginIdentifier],
            [$ipRateLimitAction, null],
        ]);
        $accountLimitAllowed = $loginLocks !== false && rate_limit_check(
            $conn,
            $accountRateLimitAction,
            $accountRateLimitMaximum,
            $rateLimitWindow,
            $normalizedLoginIdentifier,
            true
        );
        $ipLimitAllowed = $loginLocks !== false && rate_limit_check(
            $conn,
            $ipRateLimitAction,
            $ipRateLimitMaximum,
            $rateLimitWindow,
            null,
            true
        );

        if (!$accountLimitAllowed || !$ipLimitAllowed) {
            if ($loginLocks !== false) {
                rate_limit_release_locks($conn, $loginLocks);
            }
            $message = rate_limit_message($conn, 'login', $accountRateLimitMaximum, $rateLimitWindow);
        } else {

        $password = $_POST['password'] ?? '';

        // The same submitted identifier may match either the email or student number.
        // The same submitted identifier may match either the email or student number.
        $sql = "SELECT * FROM users WHERE email = ? OR student_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $login_identifier, $login_identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Compare the submitted password with the stored password hash.
            // Compare the submitted password with the stored password hash.
            if (password_verify($password, $user['password'])) {
                if (!$user['is_verified']) {
                    $message = 'Please verify your email before logging in. Check your inbox for the verification link.';
                    $showResendLink = true;
                    rate_limit_release_locks($conn, $loginLocks);
                } else {
                    rate_limit_reset($conn, $accountRateLimitAction, $normalizedLoginIdentifier, true);
                    rate_limit_release_locks($conn, $loginLocks);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];

                    if ($user['role'] === 'student') {
                        header("Location: ../student/dashboard.php");
                        exit();
                    } elseif ($user['role'] === 'service_provider') {
                        header("Location: ../service_provider/dashboard.php");
                        exit();
                    } elseif ($user['role'] === 'admin') {
                        header("Location: ../admin/dashboard.php");
                        exit();
                    }
                }
            } else {
                rate_limit_record($conn, $accountRateLimitAction, $accountRateLimitMaximum, $rateLimitWindow, $normalizedLoginIdentifier, true);
                rate_limit_record($conn, $ipRateLimitAction, $ipRateLimitMaximum, $rateLimitWindow, null, true);
                rate_limit_release_locks($conn, $loginLocks);
                $message = "Incorrect password.";
            }

        } else {
            rate_limit_record($conn, $accountRateLimitAction, $accountRateLimitMaximum, $rateLimitWindow, $normalizedLoginIdentifier, true);
            rate_limit_record($conn, $ipRateLimitAction, $ipRateLimitMaximum, $rateLimitWindow, null, true);
            rate_limit_release_locks($conn, $loginLocks);
            $message = "No account found with that email or student number.";
        }
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Student Service Management System</title>
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
            <h1 class="h3 fw-semibold mb-3">Welcome to the Student Service Management System</h1>
            <p class="small" style="max-width: 320px; opacity: 0.85;">
                Find services offered by students on campus, or list your own services for others to discover.
            </p>
        </div>

        <div></div>
    </div>

    <div class="split-login-form">
        <div class="split-login-form-inner">

            <h2 class="h4 fw-semibold mb-1 text-center">Login</h2>
            <p class="text-muted small mb-4 text-center">Enter your details to continue</p>

            <?php if (isset($message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($showResendLink)): ?>
                    <p class="small text-center"><a href="resend_verification.php">Resend verification email</a></p>
                <?php endif; ?>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                    <label class="form-label">Email or Student Number</label>
                    <input type="text" name="login_identifier" class="form-control" required>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-brand w-100">Login</button>
            </form>

            <p class="text-center small mt-4">
                Don't have an account? <a href="register.php">Register here</a>
            </p>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>
