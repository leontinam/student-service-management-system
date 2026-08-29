<?php
    session_start();
    require '../config/db.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../auth/login.php");
        exit();
    }

    function updateServiceStatusAndNotify($conn, $service_id, $status) {
        $service_stmt = $conn->prepare("SELECT provider_id, title FROM services WHERE service_id = ? AND status = 'pending'");
        $service_stmt->bind_param("i", $service_id);
        $service_stmt->execute();
        $service = $service_stmt->get_result()->fetch_assoc();

        if (!$service) {
            return;
        }

        $update_stmt = $conn->prepare("UPDATE services SET status = ? WHERE service_id = ? AND status = 'pending'");
        $update_stmt->bind_param("si", $status, $service_id);
        $update_stmt->execute();

        if ($update_stmt->affected_rows === 1) {
            $message = 'Your service "' . $service['title'] . '" has been ' . $status . '.';
            $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notification_stmt->bind_param("is", $service['provider_id'], $message);
            $notification_stmt->execute();
        }
    }

    if (isset($_GET['delete'])) {
        $service_id = $_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        header("Location: manage_listings.php");
        exit();
    }

    if (isset($_GET['approve'])) {
        $service_id = $_GET['approve'];
        updateServiceStatusAndNotify($conn, $service_id, 'approved');
        header("Location: manage_listings.php");
        exit();
    }

    if (isset($_GET['reject'])) {
        $service_id = $_GET['reject'];
        updateServiceStatusAndNotify($conn, $service_id, 'rejected');
        header("Location: manage_listings.php");
        exit();
    }

    $sql = "SELECT services.service_id, services.title, services.description, services.availability_status, services.status,
                   categories.category_name, users.full_name, users.phone_number, COALESCE(AVG(reviews.rating), 0) AS average_rating,
                   COUNT(reviews.review_id) AS rating_count
            FROM services
            JOIN categories ON services.category_id = categories.category_id
            JOIN users ON services.provider_id = users.user_id
            LEFT JOIN reviews ON reviews.service_id = services.service_id
            GROUP BY services.service_id, services.title, services.description, services.availability_status, services.status, categories.category_name, users.full_name, users.phone_number
            ORDER BY 
                CASE services.status WHEN 'pending' THEN 0 ELSE 1 END,
                services.created_at DESC";

    $result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Listings</title>
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
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_listings.php" class="active">Manage Listings</a>
        <a href="pending_approvals.php">Pending Approvals</a>
        <a href="manage_categories.php">Manage Categories</a>
        <a href="notifications.php">Notifications</a>
        <div class="logout-link">
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="app-main">
        <div class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="hamburger-btn" class="app-hamburger-btn" aria-label="Open navigation menu"><span class="hamburger-icon"><span></span><span></span><span></span></span></button>
                <span class="fw-semibold">Manage Listings</span>
            </div>
            <div class="app-topbar-actions">
                <a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg></a>
                <button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))); ?></button>
                <button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button>
            </div>
        </div>

        <div class="app-content">
            <div class="admin-toolbar">
                <div>
                    <h1>Service Listings</h1>
                    <p>Review all services, their provider details, availability, and approval status.</p>
                </div>
            </div>
            <?php if ($result->num_rows === 0): ?>
                <div class="listing-empty">
                    <span class="listing-empty-icon" aria-hidden="true">⌕</span>
                    <p>No service listings found.</p>
                </div>
            <?php else: ?>
                <div class="listing-grid">
                    <?php while ($service = $result->fetch_assoc()): ?>
                        <article class="listing-card">
                            <div class="listing-card-header">
                                <span class="listing-badge listing-badge-category"><?php echo htmlspecialchars($service['category_name']); ?></span>
                                <?php if ($service['status'] === 'pending'): ?>
                                    <span class="listing-badge listing-badge-pending">Pending</span>
                                <?php elseif ($service['status'] === 'approved'): ?>
                                    <span class="listing-badge listing-badge-approved">Approved</span>
                                <?php else: ?>
                                    <span class="listing-badge listing-badge-rejected">Rejected</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="listing-title"><?php echo htmlspecialchars($service['title']); ?></h2>
                            <p class="listing-description"><?php echo htmlspecialchars($service['description']); ?></p>
                            <div class="service-rating"><span class="service-rating-stars">★</span><strong><?php echo $service['rating_count'] ? number_format((float) $service['average_rating'], 1) : 'New'; ?></strong><span><?php echo (int) $service['rating_count']; ?> ratings</span></div>
                            <div class="listing-meta">
                                <div class="listing-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg><span><?php echo htmlspecialchars($service['full_name']); ?></span></div>
                                <div class="listing-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.64a2 2 0 0 1-.45 2.11L8.01 9.74a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.86.29 1.74.5 2.64.62A2 2 0 0 1 22 16.92z"></path></svg><span><?php echo htmlspecialchars($service['phone_number']); ?></span></div>
                                <?php if ($service['availability_status'] === 'available'): ?>
                                    <span class="listing-badge listing-badge-available">Available</span>
                                <?php else: ?>
                                    <span class="listing-badge listing-badge-unavailable">Unavailable</span>
                                <?php endif; ?>
                            </div>
                            <div class="listing-actions">
                                <?php if ($service['status'] === 'pending'): ?>
                                    <a href="manage_listings.php?approve=<?php echo $service['service_id']; ?>" class="listing-action-btn">Approve</a>
                                    <a href="manage_listings.php?reject=<?php echo $service['service_id']; ?>" class="listing-action-btn">Reject</a>
                                <?php endif; ?>
                                <a href="manage_listings.php?delete=<?php echo $service['service_id']; ?>" class="listing-action-btn listing-action-btn-danger confirm-delete">Delete</a>
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
