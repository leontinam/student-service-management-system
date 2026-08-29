<?php
    require '../config/db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $phone_number = $_POST['phone_number'];
        $role = $_POST['role'];
        $student_number = $_POST['student_number'];

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (full_name, email, password, phone_number, student_number, role) 
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $full_name, $email, $hashed_password, $phone_number, $student_number, $role);

        if ($stmt->execute()) {
            $admin_ids = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
            $notification_message = $full_name . ' created a new ' . str_replace('_', ' ', $role) . ' profile.';
            $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            while ($admin = $admin_ids->fetch_assoc()) {
                $notification_stmt->bind_param("is", $admin['user_id'], $notification_message);
                $notification_stmt->execute();
            }
            $message = "Registration successful! You can now log in.";
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
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
        <div class="split-login-form-inner">

            <h2 class="h4 fw-semibold mb-1 text-center">Create an Account</h2>
            <p class="text-muted small mb-4 text-center">Fill in your details to get started</p>

            <?php if (isset($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST">

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Student Number</label>
                    <input type="text" name="student_number" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" required>
                </div>

                <div class="mb-4">
                    <label class="form-label">Register as</label>
                    <select name="role" class="form-select" required>
                        <option value="">-- Select --</option>
                        <option value="student">Student</option>
                        <option value="service_provider">Service Provider</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-brand w-100">Register</button>

            </form>

            <p class="text-center small mt-4">
                Already have an account? <a href="login.php">Login here</a>
            </p>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>
