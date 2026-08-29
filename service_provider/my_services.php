<?php
    session_start();
    require '../config/db.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
        header("Location: ../auth/login.php");
        exit();
    }

    $provider_id = $_SESSION['user_id'];

    $notif_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $notif_count_stmt->bind_param("i", $provider_id);
    $notif_count_stmt->execute();
    $notif_count = $notif_count_stmt->get_result()->fetch_assoc()['count'];

    if (isset($_GET['delete'])) {
        $service_id = $_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ? AND provider_id = ?");
        $stmt->bind_param("ii", $service_id, $provider_id);
        $stmt->execute();
        header("Location: my_services.php");
        exit();
    }

    if (isset($_GET['toggle'])) {
        $service_id = $_GET['toggle'];
        $stmt = $conn->prepare("UPDATE services 
                                 SET availability_status = IF(availability_status = 'available', 'unavailable', 'available') 
                                 WHERE service_id = ? AND provider_id = ?");
        $stmt->bind_param("ii", $service_id, $provider_id);
        $stmt->execute();

        $get_title = $conn->prepare("SELECT title, availability_status FROM services WHERE service_id = ?");
        $get_title->bind_param("i", $service_id);
        $get_title->execute();
        $service_info = $get_title->get_result()->fetch_assoc();

        $notif_message = "A service you favorited (" . $service_info['title'] . ") is now " . $service_info['availability_status'] . ".";

        $find_students = $conn->prepare("SELECT student_id FROM favorites WHERE service_id = ?");
        $find_students->bind_param("i", $service_id);
        $find_students->execute();
        $students_result = $find_students->get_result();

        while ($student = $students_result->fetch_assoc()) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notif_stmt->bind_param("is", $student['student_id'], $notif_message);
            $notif_stmt->execute();
        }

        header("Location: my_services.php");
        exit();
    }

    $sql = "SELECT services.*, categories.category_name FROM services INNER JOIN categories ON services.category_id = categories.category_id WHERE services.provider_id = ? ORDER BY services.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Services</title>
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
        <a href="my_services.php" class="active">My Services</a>
        <a href="notifications.php">Notifications <?php if ($notif_count > 0): ?><span class="badge bg-danger"><?php echo $notif_count; ?></span><?php endif; ?></a>
        <div class="logout-link">
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="app-main">
        <div class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="hamburger-btn" class="app-hamburger-btn" aria-label="Open navigation menu"><span class="hamburger-icon"><span></span><span></span><span></span></span></button>
                <span class="fw-semibold">My Services</span>
            </div>
            <div class="app-topbar-actions">
                <a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg></a>
                <button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))); ?></button>
                <button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button>
            </div>
        </div>

        <div class="app-content">

            <div class="provider-services-heading">
                <div>
                    <h1>My services</h1>
                    <p>Manage your listings and their availability.</p>
                </div>
                <a href="add_service.php" class="btn btn-brand service-add-btn"><span aria-hidden="true">+</span> Add a service</a>
            </div>

            <?php if ($result->num_rows === 0): ?>
                <div class="listing-empty provider-services-empty">
                    <span class="listing-empty-icon" aria-hidden="true">+</span>
                    <h2>No services yet</h2>
                    <p>Add your first service to start building your catalogue.</p>
                    <a href="add_service.php" class="btn btn-brand">Create your first service</a>
                </div>
            <?php else: ?>
                <div class="listing-grid">
                <?php while ($service = $result->fetch_assoc()): ?>
                    <article class="listing-card">
                        <div class="listing-card-header">
                            <span class="listing-badge listing-badge-category"><?php echo htmlspecialchars($service['category_name']); ?></span>
                            <span class="listing-badge <?php echo $service['availability_status'] === 'available' ? 'listing-badge-available' : 'listing-badge-unavailable'; ?>"><span class="listing-status-dot" aria-hidden="true"></span><?php echo ucfirst($service['availability_status']); ?></span>
                        </div>
                        <h2 class="listing-title"><?php echo htmlspecialchars($service['title']); ?></h2>
                        <p class="listing-description"><?php echo htmlspecialchars($service['description']); ?></p>
                        <div class="provider-service-review">
                            <span>Listing status</span>
                            <strong class="provider-review-status provider-review-<?php echo htmlspecialchars($service['status']); ?>"><?php echo ucfirst($service['status']); ?></strong>
                        </div>
                        <div class="listing-actions">
                            <a href="my_services.php?toggle=<?php echo $service['service_id']; ?>" class="listing-action-btn listing-action-btn-primary">
                                <?php echo $service['availability_status'] === 'available' ? 'Pause service' : 'Make available'; ?>
                            </a>
                            <a href="my_services.php?delete=<?php echo $service['service_id']; ?>" class="listing-action-btn listing-action-btn-danger confirm-delete">Delete</a>
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
