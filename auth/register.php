<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/rate_limit.php';
require_once __DIR__ . '/../config/verification_email.php';

// The shared CSRF helper supplies the token used by registration and resend forms.
$message = null;
$message_type = 'danger';
$registrationConfirmationEmail = null;
$verificationBaseUrl = verification_base_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_registration') {
    if (!csrf_validate_request()) {
        csrf_reject_request();
    }
    unset($_SESSION['registration_confirmation_email']);
    header('Location: register.php');
    exit();
}

if (isset($_SESSION['registration_confirmation_email'])) {
    $registrationConfirmationEmail = $_SESSION['registration_confirmation_email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        csrf_reject_request();
    } else {
        $rateLimitAction = 'registration';
        $rateLimitMaximum = 5;
        $rateLimitWindow = 15 * 60;

        if (!rate_limit_check($conn, $rateLimitAction, $rateLimitMaximum, $rateLimitWindow)) {
            $message = rate_limit_message($conn, $rateLimitAction, $rateLimitMaximum, $rateLimitWindow);
        } else {
            rate_limit_record($conn, $rateLimitAction, $rateLimitMaximum, $rateLimitWindow);

            $full_name = trim($_POST['full_name'] ?? '');
            $student_number = trim($_POST['student_number'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $phone_number = trim($_POST['phone_number'] ?? '');
            $role = $_POST['role'] ?? '';
            $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';

            // Validate each submitted field before creating an account or sending email.
            // Validate each submitted field before creating an account or sending email.
            if (!preg_match('/^\d{10}$/', $student_number)) {
                $message = 'Student number must be exactly 10 digits.';
            } elseif (!preg_match('/^\d{10}$/', $phone_number)) {
                $message = 'Phone number must be exactly 10 digits.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address.';
            } elseif (!in_array($role, ['student', 'service_provider'], true)) {
                $message = 'Please select a valid registration role.';
            } elseif (!preg_match($password_pattern, $password)) {
                $message = 'Password must be at least 8 characters and include an uppercase letter, lowercase letter, number, and special character.';
            } elseif ($verificationBaseUrl === null) {
                $message = 'Registration email links are not configured. Please contact the site administrator.';
            } elseif (!verification_smtp_is_configured()) {
                $message = 'Registration email is not configured. Please contact the site administrator.';
            } else {
                $existingUserStmt = $conn->prepare('SELECT is_verified FROM users WHERE email = ?');
                $existingUserStmt->bind_param('s', $email);
                $existingUserStmt->execute();
                $existingUser = $existingUserStmt->get_result()->fetch_assoc();

                if ($existingUser) {
                    $message = 'An account with that email already exists. If it is not verified, use the resend verification option.';
                    $showResendLink = true;
                } else {
                    $transactionStarted = false;
                    $deliveryLocks = false;
                    try {
                        $deliveryLocks = rate_limit_acquire_locks($conn, [['verification_delivery', strtolower($email)]]);
                        if ($deliveryLocks === false) {
                            throw new RuntimeException('Could not acquire the verification delivery lock.');
                        }
                        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                        // Save the account and administrator notices as one database operation.
                        // Save the account and administrator notices as one database operation.
                        $conn->begin_transaction();
                        $transactionStarted = true;
                        // Store a password hash and a random token with a 24-hour expiry.
                        // Store a password hash and a random token with a 24-hour expiry.
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $verificationToken = bin2hex(random_bytes(32));
                        $insert = $conn->prepare('INSERT INTO users (full_name, email, password, phone_number, student_number, role, verification_token, verification_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
                        $insert->bind_param('sssssss', $full_name, $email, $hashedPassword, $phone_number, $student_number, $role, $verificationToken);
                        $insert->execute();

                        $adminIds = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
                        $notificationMessage = $full_name . ' created a new ' . str_replace('_', ' ', $role) . ' profile.';
                        $notificationStmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
                        while ($admin = $adminIds->fetch_assoc()) {
                            $notificationStmt->bind_param('is', $admin['user_id'], $notificationMessage);
                            $notificationStmt->execute();
                        }
                        $conn->commit();
                        $transactionStarted = false;

                        // Send activation mail only after the account transaction commits.
                        // Send activation mail only after the account transaction commits.
                        send_verification_email($email, $full_name, $role, $verificationToken, $verificationBaseUrl);
                    } catch (Throwable $exception) {
                        if ($transactionStarted) {
                            $conn->rollback();
                        }
                        error_log('Registration or verification email delivery failed.');
                        if ($exception instanceof mysqli_sql_exception && $exception->getCode() === 1062) {
                            $message = 'An account with that email already exists. If it is not verified, use the resend verification option.';
                        } else {
                            $message = 'Registration could not be completed or the verification email could not be sent. If you already submitted, use the resend verification option or try again later.';
                        }
                        $showResendLink = true;
                    } finally {
                        if ($deliveryLocks !== false) {
                            rate_limit_release_locks($conn, $deliveryLocks);
                        }
                    }

                    if (!isset($message)) {
                        $_SESSION['registration_confirmation_email'] = $email;
                        header('Location: register.php?registration=sent');
                        exit();
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - Student Service Management System</title>
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
            <h1 class="h3 fw-semibold mb-3">Join the Student Service Management System</h1>
            <p class="small" style="max-width: 320px; opacity: 0.85;">
                Create an account to browse services on campus, or register as a provider to list your own.
            </p>
        </div>

        <div></div>
    </div>

    <div class="split-login-form">
        <div class="split-login-form-inner registration-form-inner">

            <?php if ($registrationConfirmationEmail !== null): ?>
                <div class="text-center">
                    <h2 class="h4 fw-semibold mb-3">Check your email</h2>
                    <p class="text-muted mb-2">Your account has been created. Please check your email inbox, including your spam or junk folder, and click the verification link to activate your account.</p>
                    <p class="small mb-4">Verification email sent to <strong><?php echo htmlspecialchars($registrationConfirmationEmail, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <form action="resend_verification.php" method="POST" class="mb-3">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($registrationConfirmationEmail, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-brand w-100">Resend verification email</button>
                    </form>
                    <a href="login.php">Return to login</a>
                    <form action="register.php" method="post" class="mt-3"><input type="hidden" name="action" value="new_registration"><?php echo csrf_field(); ?><button type="submit" class="btn btn-link btn-sm">Register another account</button></form>
                </div>
            <?php else: ?>

            <h2 class="h4 fw-semibold mb-1 text-center">Create an Account</h2>
            <p class="text-muted small mb-4 text-center">Fill in your details to get started</p>

            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($showResendLink)): ?>
                    <p class="small text-center"><a href="resend_verification.php">Resend verification email</a></p>
                <?php endif; ?>
            <?php endif; ?>

            <form action="register.php" method="POST" autocomplete="off" class="registration-form">
                <?php echo csrf_field(); ?>

                <div class="mb-3 registration-field">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>

                <div class="mb-3 registration-field">
                    <label class="form-label">Student Number</label>
                    <input type="text" name="student_number" class="form-control" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" required>
                </div>

                <div class="mb-3 registration-field">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" autocomplete="off" readonly data-prevent-autofill required>
                </div>

                <div class="mb-3 registration-field">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" required>
                </div>

                <div class="mb-3 registration-field registration-password-field">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" readonly data-prevent-autofill required>
                    <div id="password-feedback" class="registration-password-feedback" aria-live="polite" hidden>
                        <div class="password-strength-bars" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                        <span class="password-strength-text"></span>
                    </div>
                </div>

                <div class="mb-4 registration-field">
                    <label class="form-label">Register as</label>
                    <select name="role" class="form-select" required>
                        <option value="student">Student</option>
                        <option value="service_provider">Service Provider</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-brand w-100">Register</button>

            </form>

            <p class="text-center small mt-4">
                Already have an account? <a href="login.php">Login here</a>
            </p>

            <?php endif; ?>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    document.querySelectorAll('[data-prevent-autofill]').forEach(function (field) {
        const enableField = function () { field.removeAttribute('readonly'); };
        field.addEventListener('pointerdown', enableField, { once: true });
        field.addEventListener('focus', enableField, { once: true });
    });

    const passwordInput = document.getElementById('password');
    const passwordFeedback = document.getElementById('password-feedback');
    const passwordRequirements = {
        length: function (value) { return value.length >= 8; },
        uppercase: function (value) { return /[A-Z]/.test(value); },
        lowercase: function (value) { return /[a-z]/.test(value); },
        number: function (value) { return /\d/.test(value); },
        special: function (value) { return /[^A-Za-z0-9]/.test(value); }
    };

    passwordInput.addEventListener('input', function () {
        const metRequirements = Object.keys(passwordRequirements).filter(function (requirement) {
            return passwordRequirements[requirement](passwordInput.value);
        });
        const isValid = metRequirements.length === Object.keys(passwordRequirements).length;
        const hasStartedTyping = passwordInput.value.length > 0;
        const strength = metRequirements.length;
        const strengthNames = ['Very weak', 'Weak', 'Fair', 'Good', 'Great'];
        const strengthText = passwordFeedback.querySelector('.password-strength-text');

        passwordInput.classList.toggle('is-invalid', hasStartedTyping && !isValid);
        passwordFeedback.hidden = !hasStartedTyping || isValid;
        passwordFeedback.dataset.strength = strength;
        strengthText.textContent = 'Password strength: ' + strengthNames[Math.max(0, strength - 1)] + '. Use at least 8 characters and a mix of letters, numbers, and symbols.';
    });
</script>

</body>
</html>
