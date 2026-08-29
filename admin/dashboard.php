<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../auth/login.php");
        exit();
    }

    require '../config/db.php';

    $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    $total_services = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
    $total_categories = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
    $pending_count = $conn->query("SELECT COUNT(*) as count FROM services WHERE status = 'pending'")->fetch_assoc()['count'];
    $rating_summary = $conn->query("SELECT COUNT(*) AS total_ratings FROM reviews")->fetch_assoc();
    $selected_category = filter_input(INPUT_GET, 'rating_category', FILTER_VALIDATE_INT) ?: 0;
    $categories_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
    $category_filter = $selected_category ? " WHERE s.category_id = " . (int) $selected_category : "";
    $provider_ratings_sql = "SELECT u.full_name, COUNT(r.review_id) AS rating_count, COALESCE(AVG(r.rating), 0) AS average_rating
        FROM users u JOIN services s ON s.provider_id = u.user_id LEFT JOIN reviews r ON r.service_id = s.service_id";
    $most_rated = $conn->query($provider_ratings_sql . $category_filter . " GROUP BY u.user_id, u.full_name ORDER BY rating_count DESC, average_rating DESC, u.full_name ASC LIMIT 5");
    $least_rated = $conn->query($provider_ratings_sql . $category_filter . " GROUP BY u.user_id, u.full_name ORDER BY rating_count ASC, average_rating ASC, u.full_name ASC LIMIT 5");
    $distribution_result = $conn->query("SELECT r.rating, COUNT(*) AS rating_count FROM reviews r JOIN services s ON s.service_id = r.service_id" . $category_filter . " GROUP BY r.rating");
    $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    while ($row = $distribution_result->fetch_assoc()) { $distribution[(int) $row['rating']] = (int) $row['rating_count']; }
    $max_distribution = max(1, ...$distribution);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_listings.php">Manage Listings</a>
        <a href="pending_approvals.php">Pending Approvals <?php if ($pending_count > 0): ?><span class="badge bg-danger"><?php echo $pending_count; ?></span><?php endif; ?></a>
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
                <span class="fw-semibold">Admin Dashboard</span>
            </div>
            <div class="app-topbar-actions">
                <a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg></a>
                <button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))); ?></button>
                <button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button>
            </div>
        </div>

        <div class="app-content">
            <h2>Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
            <p class="text-muted">Monitor and manage the platform from here.</p>

            <div class="row mt-4">
                <div class="col-md-3 mb-3">
                    <div class="dashboard-stat-card"><div class="dashboard-stat-label">Total Users</div><div class="dashboard-stat-value"><?php echo $total_users; ?></div></div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="dashboard-stat-card"><div class="dashboard-stat-label">Total Services</div><div class="dashboard-stat-value"><?php echo $total_services; ?></div></div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="dashboard-stat-card"><div class="dashboard-stat-label">Total Ratings</div><div class="dashboard-stat-value"><?php echo $rating_summary['total_ratings']; ?></div></div>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="pending_approvals.php" class="dashboard-stat-card text-decoration-none"><div class="dashboard-stat-label">Pending Approval</div><div class="dashboard-stat-value" style="color: var(--color-accent);"><?php echo $pending_count; ?></div></a>
                </div>
            </div>

            <section class="analytics-panel mt-2"><div class="analytics-header"><div><h2 class="analytics-title mb-1">Provider Ratings by Category</h2><p class="text-muted small mb-0">Choose a category to compare service providers by submitted ratings.</p></div><form method="get" class="analytics-filter"><select class="form-select" name="rating_category" onchange="this.form.submit()"><option value="">All Categories</option><?php while ($category = $categories_result->fetch_assoc()): ?><option value="<?php echo (int) $category['category_id']; ?>" <?php echo $selected_category === (int) $category['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['category_name']); ?></option><?php endwhile; ?></select></form></div></section>
            <div class="row mt-1 g-3">
                <div class="col-lg-6"><section class="analytics-panel h-100"><h2 class="analytics-title">Most Rated Providers</h2><ol class="analytics-rank-list"><?php if ($most_rated->num_rows === 0): ?><li class="text-muted small">No providers are listed in this category yet.</li><?php endif; ?><?php while ($provider = $most_rated->fetch_assoc()): ?><li class="analytics-rank-item"><span class="analytics-rank-name"><?php echo htmlspecialchars($provider['full_name']); ?></span><span class="analytics-rank-value"><?php echo $provider['rating_count']; ?> ratings · ★ <?php echo number_format($provider['average_rating'], 1); ?></span></li><?php endwhile; ?></ol></section></div>
                <div class="col-lg-6"><section class="analytics-panel h-100"><h2 class="analytics-title">Least Rated Providers</h2><ol class="analytics-rank-list"><?php if ($least_rated->num_rows === 0): ?><li class="text-muted small">No providers are listed in this category yet.</li><?php endif; ?><?php while ($provider = $least_rated->fetch_assoc()): ?><li class="analytics-rank-item"><span class="analytics-rank-name"><?php echo htmlspecialchars($provider['full_name']); ?></span><span class="analytics-rank-value"><?php echo $provider['rating_count']; ?> ratings · ★ <?php echo number_format($provider['average_rating'], 1); ?></span></li><?php endwhile; ?></ol></section></div>
                <div class="col-12"><section class="analytics-panel"><h2 class="analytics-title">Rating Distribution</h2><p class="text-muted small">Distribution for the selected category.</p><div class="rating-distribution"><?php for ($star = 5; $star >= 1; $star--): ?><div class="rating-distribution-row"><span><?php echo $star; ?> ★</span><div class="rating-bar"><span style="width: <?php echo ($distribution[$star] / $max_distribution) * 100; ?>%"></span></div><span><?php echo $distribution[$star]; ?></span></div><?php endfor; ?></div></section></div>
            </div>

            <div class="row mt-2">
                <div class="col-md-4 mb-3">
                    <div class="card service-card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Users</h5>
                            <p class="card-text">View and remove registered accounts.</p>
                            <a href="manage_users.php" class="btn btn-brand">Manage Users</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card service-card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Listings</h5>
                            <p class="card-text">View, approve, or remove posted services.</p>
                            <a href="manage_listings.php" class="btn btn-brand">Manage Listings</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card service-card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Categories</h5>
                            <p class="card-text">Add, edit, or remove service categories.</p>
                            <a href="manage_categories.php" class="btn btn-brand">Manage Categories</a>
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
