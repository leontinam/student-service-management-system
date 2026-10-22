<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/rate_limit.php';
require_once __DIR__ . '/../config/verification_email.php';

// Record operational stage and outcome without writing the submitted email or token.
$logResendDiagnostic = static function (string $stage, string $result): void {
    $line = gmdate('Y-m-d\\TH:i:s\\Z') . ' stage=' . $stage . ' result=' . $result;
    error_log('Verification resend diagnostic: ' . $line . '.');
    @file_put_contents(__DIR__ . '/../config/resend-diagnostics.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
};

$message = null;
$messageType = 'info';
$email = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        csrf_reject_request();
    } else {
        $emailBucket = 'resend_verification_email';
        $ipBucket = 'resend_verification_ip';
        $emailLimit = 3;
        $ipLimit = 10;
        $windowSeconds = 15 * 60;
        // Limit resends by both normalized email and client IP.
        $locks = rate_limit_acquire_locks($conn, [[$emailBucket, strtolower($email)], [$ipBucket, null]]);
        $rateLimitLocksAcquired = $locks !== false;
        $emailBucketAllowed = $locks !== false
            && rate_limit_check($conn, $emailBucket, $emailLimit, $windowSeconds, strtolower($email), true);
        $ipBucketAllowed = $emailBucketAllowed
            && rate_limit_check($conn, $ipBucket, $ipLimit, $windowSeconds, null, true);
        $allowed = $emailBucketAllowed && $ipBucketAllowed;

        if ($allowed) {
            rate_limit_record($conn, $emailBucket, $emailLimit, $windowSeconds, strtolower($email), true);
            rate_limit_record($conn, $ipBucket, $ipLimit, $windowSeconds, null, true);
            $emailLockName = rate_limit_lock_name($emailBucket, strtolower($email));
            $ipLocks = array_values(array_filter($locks, static function ($lockName) use ($emailLockName) {
                return $lockName !== $emailLockName;
            }));
            rate_limit_release_locks($conn, $ipLocks);
            $locks = [$emailLockName];
        } elseif ($locks !== false) {
            rate_limit_release_locks($conn, $locks);
            $locks = false;
        }

        if (!$allowed) {
            $blockedBucket = !$rateLimitLocksAcquired
                ? 'lock_unavailable'
                : (!$emailBucketAllowed ? 'email_bucket' : 'ip_bucket');
            $logResendDiagnostic('rate_limit', $blockedBucket . '_blocked');
            $message = 'Too many requests. Please wait before asking for another verification email.';
            $messageType = 'warning';
        } elseif (verification_base_url() === null || !verification_smtp_is_configured()) {
            $logResendDiagnostic('configuration', 'incomplete');
            $message = 'Verification email service is not configured. Please try again later or contact the site administrator.';
            $messageType = 'danger';
        } else {
            // Always show the same response to avoid confirming whether an account exists.
            $message = 'Your request was received. For privacy, this page cannot confirm whether the address matches an account or whether an email was delivered. If no message arrives, check your spam folder and try again after the cooldown or contact site support.';
            $messageType = 'info';
            $failureStage = 'request';

            try {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failureStage = 'delivery_lock';
                    $deliveryLocks = rate_limit_acquire_locks($conn, [['verification_delivery', strtolower($email)]]);
                    if ($deliveryLocks === false) {
                        throw new RuntimeException('Could not acquire the verification delivery lock.');
                    }
                    try {
                        $failureStage = 'account_lookup';
                        $lookup = $conn->prepare('SELECT user_id, email, full_name, role FROM users WHERE email = ? AND is_verified = FALSE LIMIT 1');
                        $lookup->bind_param('s', $email);
                        $lookup->execute();
                        $user = $lookup->get_result()->fetch_assoc();

                        if ($user) {
                            $token = bin2hex(random_bytes(32));
                            $failureStage = 'token_update';
                            $conn->begin_transaction();
                            try {
                                $update = $conn->prepare('UPDATE users SET verification_token = ?, verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE user_id = ? AND is_verified = FALSE');
                                $update->bind_param('si', $token, $user['user_id']);
                                $update->execute();
                                $changed = $update->affected_rows === 1;
                                $conn->commit();
                            } catch (Throwable $exception) {
                                $conn->rollback();
                                throw $exception;
                            }

                            if ($changed) {
                                $failureStage = 'smtp_send';
                                send_verification_email($user['email'], $user['full_name'], $user['role'], $token, verification_base_url());
                                $logResendDiagnostic('smtp_send', 'accepted_by_relay');
                            } else {
                                $logResendDiagnostic('token_update', 'no_row_updated');
                            }
                        } else {
                            $logResendDiagnostic('account_lookup', 'no_unverified_match');
                        }
                    } finally {
                        rate_limit_release_locks($conn, $deliveryLocks);
                    }
                } else {
                    $logResendDiagnostic('email_validation', 'invalid');
                }
            } catch (Throwable $exception) {
                $errorCategory = 'internal';
                if ($exception instanceof mysqli_sql_exception) {
                    $errorCategory = (int) $exception->getCode() === 1054 ? 'database_schema' : 'database';
                } elseif ($exception instanceof \PHPMailer\PHPMailer\Exception) {
                    $safeErrorHint = strtolower($exception->getMessage());
                    if (strpos($safeErrorHint, 'authenticat') !== false) {
                        $errorCategory = 'smtp_authentication';
                    } elseif (strpos($safeErrorHint, 'connect') !== false) {
                        $errorCategory = 'smtp_connection';
                    } else {
                        $errorCategory = 'smtp_delivery';
                    }
                }
                error_log('Verification email resend failed; stage=' . $failureStage . '; category=' . $errorCategory . '; code=' . (int) $exception->getCode() . '.');
                $logResendDiagnostic($failureStage . '_' . $errorCategory, 'failed_code_' . (int) $exception->getCode());
            } finally {
                if ($locks !== false) {
                    rate_limit_release_locks($conn, $locks);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resend Verification - Student Service Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="split-login">
    <div class="split-login-panel">
        <div class="d-flex align-items-center gap-2"><div class="split-login-mark">S</div><span class="fw-semibold">SSMS</span></div>
        <div>
            <h1 class="h3 fw-semibold mb-3">Verify your email</h1>
            <p class="small" style="max-width: 320px; opacity: 0.85;">Request another secure link to activate your account.</p>
        </div>
        <div></div>
    </div>
    <div class="split-login-form">
        <div class="split-login-form-inner">
            <h2 class="h4 fw-semibold mb-1 text-center">Resend verification email</h2>
            <p class="text-muted small mb-4 text-center">Enter the email address used when registering.</p>
            <?php if ($message !== null): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form action="resend_verification.php" method="POST">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control" id="email" type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required>
                </div>
                <button type="submit" class="btn btn-brand w-100">Resend verification email</button>
            </form>
            <p class="text-center small mt-4"><a href="login.php">Return to login</a></p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
