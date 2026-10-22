<?php
    session_start();
    require '../config/db.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
        header("Location: ../auth/login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Keep provider notifications scoped to their own account and ordered newest first.
    $stmt = $conn->prepare("SELECT notification_id, message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications</title>
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
        <a href="dashboard.php">Dashboard</a>
        <a href="add_service.php">Add a Service</a>
        <a href="my_services.php">My Services</a>
        <a href="notifications.php" class="active">Notifications</a>
        <div class="logout-link">
            <form action="../auth/logout.php" method="post" class="logout-form"><?php echo csrf_field(); ?><button type="submit" class="logout-form-button">Logout</button></form>
        </div>
    </div>

    <div class="app-main">
        <div class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="hamburger-btn" class="app-hamburger-btn" aria-label="Open navigation menu"><span class="hamburger-icon"><span></span><span></span><span></span></span></button>
                <span class="fw-semibold">My Notifications</span>
            </div>
            <div class="app-topbar-actions">
                <a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg></a>
                <button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))); ?></button>
                <button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button>
            </div>
        </div>

        <div class="app-content">
            <?php if ($result->num_rows === 0): ?>
                <div class="alert alert-info notification-empty">No notifications yet.</div>
            <?php else: ?>
                <div class="notification-list">
                    <?php while ($notif = $result->fetch_assoc()): ?>
                    <article class="notification-item <?php echo $notif['is_read'] ? 'notification-read' : 'notification-unread'; ?>" data-notification-id="<?php echo (int) $notif['notification_id']; ?>" role="button" tabindex="0" aria-label="<?php echo $notif['is_read'] ? 'Read notification' : 'Mark notification as read'; ?>">
                        <span class="notification-dot" aria-hidden="true"></span>
                        <div>
                            <p class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <time class="notification-time" datetime="<?php echo htmlspecialchars($notif['created_at']); ?>"><?php echo date('d M Y, H:i', strtotime($notif['created_at'])); ?></time>
                        </div>
                    </article>
                    <?php endwhile; ?>
                    </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>
