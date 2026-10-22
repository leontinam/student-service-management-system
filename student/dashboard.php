<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: ../auth/login.php");
        exit();
    }

    require '../config/db.php';
    // Count unread notices so the dashboard navigation can show the current badge.
    $notif_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $notif_count_stmt->bind_param("i", $_SESSION['user_id']);
    $notif_count_stmt->execute();
    $notif_count = $notif_count_stmt->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="app-shell">

    <div class="app-sidebar-overlay" id="sidebar-overlay"></div>

    <div class="app-sidebar">
        <button type="button" class="app-sidebar-close-btn" aria-label="Close navigation menu">&times;</button>
        <div class="brand">
            <div class="split-login-mark" style="width:28px;height:28px;font-size:12px;">S</div>
            SSMS
        </div>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="browse.php">Browse Services</a>
        <a href="favorites.php">My Favorites</a>
        <a href="notifications.php">Notifications <?php if ($notif_count > 0): ?><span class="badge bg-danger"><?php echo $notif_count; ?></span><?php endif; ?></a>
        <div class="logout-link">
            <form action="../auth/logout.php" method="post" class="logout-form"><?php echo csrf_field(); ?><button type="submit" class="logout-form-button">Logout</button></form>
        </div>
    </div>

    <div class="app-main">
        <div class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="hamburger-btn" class="app-hamburger-btn" aria-label="Open navigation menu"><span class="hamburger-icon"><span></span><span></span><span></span></span></button>
                <span class="fw-semibold">Student Dashboard</span>
            </div>
            <div class="app-topbar-actions">
                <a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg></a>
                <button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))); ?></button>
                <button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button>
            </div>
        </div>

        <div class="app-content">
            <h2>Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
            <p class="text-muted">Here's what you can do from here.</p>

            <div class="row mt-4">
                <div class="col-md-4 mb-3">
                    <div class="card service-card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Browse Services</h5>
                            <p class="card-text">Find services offered by other students on campus.</p>
                            <a href="browse.php" class="btn btn-brand">Browse Now</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card service-card h-100">
                        <div class="card-body">
                            <h5 class="card-title">My Favorites</h5>
                            <p class="card-text">View services you've saved.</p>
                            <a href="favorites.php" class="btn btn-brand">View Favorites</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card service-card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Notifications</h5>
                            <p class="card-text">See updates on your favorited services.</p>
                            <a href="notifications.php" class="btn btn-brand">View Notifications</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>
