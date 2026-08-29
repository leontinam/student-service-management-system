<?php
    session_start();
    require '../config/db.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: ../auth/login.php");
        exit();
    }

    $student_id = $_SESSION['user_id'];

    $notif_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $notif_count_stmt->bind_param("i", $student_id);
    $notif_count_stmt->execute();
    $notif_count = $notif_count_stmt->get_result()->fetch_assoc()['count'];

    if (isset($_GET['remove'])) {
        $favorite_id = $_GET['remove'];
        $stmt = $conn->prepare("DELETE FROM favorites WHERE favorite_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $favorite_id, $student_id);
        $stmt->execute();
        header("Location: favorites.php");
        exit();
    }

    $sql = "SELECT favorites.favorite_id, services.title, services.description, 
                   categories.category_name, users.full_name, users.phone_number
            FROM favorites
            JOIN services ON favorites.service_id = services.service_id
            JOIN categories ON services.category_id = categories.category_id
            JOIN users ON services.provider_id = users.user_id
            WHERE favorites.student_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites</title>
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
        <a href="browse.php">Browse Services</a>
        <a href="favorites.php" class="active">My Favorites</a>
        <a href="notifications.php">Notifications <?php if ($notif_count > 0): ?><span class="badge bg-danger"><?php echo $notif_count; ?></span><?php endif; ?></a>
        <div class="logout-link">
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="app-main">
        <div class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="hamburger-btn" class="app-hamburger-btn" aria-label="Open navigation menu"><span class="hamburger-icon"><span></span><span></span><span></span></span></button>
                <span class="fw-semibold">My Favorites</span>
            </div>
            <div class="app-topbar-actions">
                <a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg></a>
                <button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))); ?></button>
                <button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button>
            </div>
        </div>

        <div class="app-content">

            <?php if ($result->num_rows === 0): ?>
                <div class="listing-empty">
                    <span class="listing-empty-icon" aria-hidden="true">☆</span>
                    <p>You haven't saved any favorites yet.</p>
                </div>
            <?php else: ?>
                <div class="listing-grid">
                    <?php while ($fav = $result->fetch_assoc()): ?>
                        <article class="listing-card">
                            <div class="listing-card-header">
                                <span class="listing-badge listing-badge-category"><?php echo htmlspecialchars($fav['category_name']); ?></span>
                            </div>
                            <h2 class="listing-title"><?php echo htmlspecialchars($fav['title']); ?></h2>
                            <p class="listing-description"><?php echo htmlspecialchars($fav['description']); ?></p>
                            <div class="listing-meta">
                                <div class="listing-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg><span><?php echo htmlspecialchars($fav['full_name']); ?></span></div>
                                <div class="listing-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.64a2 2 0 0 1-.45 2.11L8.01 9.74a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.86.29 1.74.5 2.64.62A2 2 0 0 1 22 16.92z"></path></svg><span><?php echo htmlspecialchars($fav['phone_number']); ?></span></div>
                            </div>
                            <div class="listing-actions">
                                <a href="favorites.php?remove=<?php echo $fav['favorite_id']; ?>" class="listing-action-btn listing-action-btn-danger confirm-delete">Remove</a>
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
