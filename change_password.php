<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        csrf_reject_request();
    }
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    // Verify the current password before hashing and storing a replacement.
    $user = $conn->prepare('SELECT password FROM users WHERE user_id = ?');
    $user->bind_param('i', $userId);
    $user->execute();
    $account = $user->get_result()->fetch_assoc();

    if (!$account || !password_verify($current, $account['password'])) {
        $error = 'Your current password is incorrect.';
    } elseif (!preg_match($passwordPattern, $new)) {
        $error = 'Your new password must be at least 8 characters and include an uppercase letter, lowercase letter, number, and special character.';
    } elseif ($new !== $confirm) {
        $error = 'The new passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $update->bind_param('si', $hash, $userId);

        if ($update->execute()) {
            $message = 'Your password has been changed.';
        } else {
            $error = 'Your password could not be updated. Please try again.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <main class="profile-page">
        <section class="profile-editor">
            <h1 class="h4 mb-1">Change Password</h1>
            <p class="text-muted mb-4">Use a strong password with at least 8 characters.</p>

            <?php if (isset($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <?php echo csrf_field(); ?>
                <label class="form-label">Current password</label>
                <input class="form-control mb-3" type="password" name="current_password" required>

                <label class="form-label">New password</label>
                <input class="form-control" type="password" name="new_password" id="new-password" minlength="8" required>
                <div id="password-feedback" class="password-feedback mb-3" aria-live="polite" hidden>
                    <div class="password-strength-bars" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                    <span class="password-strength-text"></span>
                </div>

                <label class="form-label">Confirm new password</label>
                <input class="form-control mb-3" type="password" name="confirm_password" minlength="8" required>

                <button class="btn btn-brand w-100">Change Password</button>
            </form>
            <a class="d-block mt-4" href="javascript:history.back()">Back</a>
        </section>
    </main>

    <script>
        const passwordInput = document.getElementById('new-password');
        const passwordFeedback = document.getElementById('password-feedback');
        const passwordRequirements = {
            length: value => value.length >= 8,
            uppercase: value => /[A-Z]/.test(value),
            lowercase: value => /[a-z]/.test(value),
            number: value => /\d/.test(value),
            special: value => /[^A-Za-z0-9]/.test(value)
        };

        passwordInput.addEventListener('input', function () {
            const metRequirements = Object.values(passwordRequirements).filter(requirement => requirement(passwordInput.value));
            const isValid = metRequirements.length === Object.keys(passwordRequirements).length;
            const hasStartedTyping = passwordInput.value.length > 0;
            const strengthNames = ['Very weak', 'Weak', 'Fair', 'Good', 'Great'];

            passwordInput.classList.toggle('is-invalid', hasStartedTyping && !isValid);
            passwordFeedback.hidden = !hasStartedTyping || isValid;
            passwordFeedback.dataset.strength = metRequirements.length;
            passwordFeedback.querySelector('.password-strength-text').textContent =
                'Password strength: ' + strengthNames[Math.max(0, metRequirements.length - 1)] +
                '. Use at least 8 characters and a mix of letters, numbers, and symbols.';
        });

        document.querySelectorAll('.alert').forEach(function (alert) {
            window.setTimeout(function () {
                alert.classList.add('alert-auto-dismissing');
                window.setTimeout(function () { alert.remove(); }, 250);
            }, 4000);
        });
    </script>
</body>
</html>
