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

    // New submissions remain pending so an administrator can review them before publication.
    // New submissions remain pending so an administrator can review them before publication.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_validate_request()) {
            csrf_reject_request();
        }
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = $_POST['category_id'] ?? '';
        $requested_category = trim($_POST['requested_category'] ?? '');
        $provider_id = $_SESSION['user_id'];

        if ($title === '' || $description === '') {
            $message = 'Please complete the service title and description.';
        } elseif ($category_id === 'other' && $requested_category === '') {
            $message = 'Please enter the category you would like to request.';
        } elseif ($category_id === '') {
            $message = 'Please select a category.';
        } else {
            $selected_category_id = $category_id === 'other' ? null : filter_var($category_id, FILTER_VALIDATE_INT);
            if ($category_id !== 'other' && !$selected_category_id) {
                $message = 'Please select a valid category.';
            } else {
                // Store either a selected category or a requested category for admin review.
                // Store either a selected category or a requested category for admin review.
                $sql = "INSERT INTO services (provider_id, category_id, requested_category, title, description, status) VALUES (?, ?, ?, ?, ?, 'pending')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisss", $provider_id, $selected_category_id, $requested_category, $title, $description);

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
            <form action="../auth/logout.php" method="post" class="logout-form"><?php echo csrf_field(); ?><button type="submit" class="logout-form-button">Logout</button></form>
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

        <div class="app-content service-form-content">
            <div class="service-form-layout">
                <section class="service-form-panel">
                    <div class="service-form-panel-header">
                        <div class="service-form-icon" aria-hidden="true">+</div>
                        <div>
                            <h1>Add a service</h1>
                            <p>Complete the details below. New services are reviewed before they are published.</p>
                        </div>
                    </div>

                    <?php if (isset($message)): ?>
                        <div class="alert alert-info service-form-alert"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form action="add_service.php" method="POST" class="service-submission-form">
                        <?php echo csrf_field(); ?>
                        <div class="service-form-field">
                            <label class="form-label" for="service-title">Service title <span>Required</span></label>
                            <input id="service-title" type="text" name="title" class="form-control" maxlength="100" placeholder="e.g. Tutoring, Hair Braiding" required>
                        </div>
                        <div class="service-form-field">
                            <label class="form-label" for="service-category">Category <span>Required</span></label>
                            <select id="service-category" name="category_id" class="form-select" required>
                                <option value="">Select category</option>
                                <?php while ($cat = $categories_result->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['category_id']; ?>">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="other">Other — request a new category</option>
                            </select>
                        </div>
                        <div class="service-form-field" id="requested-category-field" hidden>
                            <label class="form-label" for="requested-category">Your category <span>Required</span></label>
                            <input id="requested-category" type="text" name="requested_category" class="form-control" maxlength="50" placeholder="e.g. Photography">
                            <small class="text-muted">An admin will review and add this category before approving your service.</small>
                        </div>
                        <div class="service-form-field">
                            <label class="form-label" for="service-description">What does this service include? <span>Required</span></label>
                            <textarea id="service-description" name="description" class="form-control" rows="5" placeholder="Describe what you offer, how much it costs, and any other important details." required></textarea>
                        </div>
                        <div class="service-form-footer">
                            <p><span aria-hidden="true">✓</span> Your service will be submitted for review.</p>
                            <button type="submit" class="btn btn-brand service-submit-btn">Submit service</button>
                        </div>
                    </form>

                </section>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    const categorySelect = document.getElementById('service-category');
    const requestedCategoryField = document.getElementById('requested-category-field');
    const requestedCategoryInput = document.getElementById('requested-category');

    categorySelect.addEventListener('change', function () {
        const isOther = this.value === 'other';
        requestedCategoryField.hidden = !isOther;
        requestedCategoryInput.required = isOther;
        if (!isOther) requestedCategoryInput.value = '';
    });
</script>

</body>
</html>
