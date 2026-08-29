<?php
    session_start();
    require '../config/db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $email = $_POST['email'];
        $password = $_POST['password'];

        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {

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

            } else {
                $message = "Incorrect password.";
            }

        } else {
            $message = "No account found with that email.";
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
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
                <div class="alert alert-danger"><?php echo $message; ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
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