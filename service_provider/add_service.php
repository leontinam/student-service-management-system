<?php
    session_start();
    require '../config/db.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
        header("Location: ../auth/login.php");
        exit();
    }

    $notif_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $notif_count_stmt->bind_param("i", $_SESSION['user_id']);
    $notif_count_stmt->execute();
    $notif_count = $notif_count_stmt->get_result()->fetch_assoc()['count'];

    $categories_result = $conn->query("SELECT * FROM categories");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $category_id = $_POST['category_id'];
        $provider_id = $_SESSION['user_id'];

        
        $sql = "INSERT INTO services (provider_id, category_id, title, description, status) VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $provider_id, $category_id, $title, $description);

        if ($stmt->execute()) {
            $admin_ids = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
            $notification_message = $_SESSION['full_name'] . ' submitted the service "' . $title . '" for approval.';
            $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            while ($admin = $admin_ids->fetch_assoc()) {
                $notification_stmt->bind_param("is", $admin['user_id'], $notification_message);
                $notification_stmt->execute();
            }
            $message = "Service submitted successfully! It will appear once approved by an admin.";
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add a Service</title>
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
        <a href="add_service.php" class="active">Add a Service</a>
        <a href="my_services.php">My Services</a>
        <a href="notifications.php">Notifications <?php if ($notif_count > 0): ?><span class="badge bg-danger"><?php echo $notif_count; ?></span><?php endif; ?></a>
        <div class="logout-link">
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="app-main">
        <div class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="hamburger-btn" class="app-hamburger-btn" aria-label="Open navigation menu"><span class="hamburger-icon"><span></span><span></span><span></span></span></button>
                <span class="fw-semibold">Add a Service</span>
            </div>
            <div class="app-topbar-actions">
                <a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg></a>
                <button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))); ?></button>
                <button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button>
            </div>
        </div>

        <div class="app-content">
            <div class="service-form-layout">
                <section class="service-form-intro">
                    <span class="service-form-eyebrow">SERVICE CATALOGUE</span>
                    <h1>Share what you can help students with.</h1>
                    <p>Your service will be sent to the admin team for review before it becomes visible to students.</p>
                    <div class="service-form-steps" aria-label="Service submission steps">
                        <div class="service-form-step is-active"><span>1</span><div><strong>Add the details</strong><small>Describe your service clearly</small></div></div>
                        <div class="service-form-step"><span>2</span><div><strong>Admin review</strong><small>We check it meets the guidelines</small></div></div>
                        <div class="service-form-step"><span>3</span><div><strong>Go live</strong><small>Students can discover your service</small></div></div>
                    </div>
                </section>

                <section class="service-form-panel">
                    <div class="service-form-panel-header">
                        <div class="service-form-icon" aria-hidden="true">+</div>
                        <div>
                            <span class="service-form-eyebrow">NEW LISTING</span>
                            <h2>Service details</h2>
                            <p>Give students a clear picture of what you offer.</p>
                        </div>
                    </div>

                    <?php if (isset($message)): ?>
                        <div class="alert alert-info service-form-alert"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form action="add_service.php" method="POST" class="service-submission-form">
                        <div class="service-form-field">
                            <label class="form-label" for="service-title">Service title <span>Required</span></label>
                            <input id="service-title" type="text" name="title" class="form-control" maxlength="100" placeholder="e.g. CV design and review" required>
                            <small>A short, specific name makes your listing easier to find.</small>
                        </div>
                        <div class="service-form-field">
                            <label class="form-label" for="service-category">Category <span>Required</span></label>
                            <select id="service-category" name="category_id" class="form-select" required>
                                <option value="">Choose the best fit</option>
                                <?php while ($cat = $categories_result->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['category_id']; ?>">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="service-form-field">
                            <label class="form-label" for="service-description">What does this service include? <span>Required</span></label>
                            <textarea id="service-description" name="description" class="form-control" rows="5" placeholder="Explain what students can expect, who it is for, and how you can help." required></textarea>
                            <small>Helpful descriptions include the outcome and any important details.</small>
                        </div>
                        <div class="service-form-footer">
                            <p><span aria-hidden="true">✓</span> Submitted services are reviewed before publishing.</p>
                            <button type="submit" class="btn btn-brand service-submit-btn">Submit for review <span aria-hidden="true">→</span></button>
                        </div>
                    </form>

                </section>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>
