<?php
session_start();
require '../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../auth/login.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        csrf_reject_request();
    }

    if (($_POST['action'] ?? '') === 'add_category') {
        $serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
        $categoryName = trim($_POST['category_name'] ?? '');
        $service = $conn->prepare("SELECT service_id FROM services WHERE service_id = ? AND status = 'pending' AND category_id IS NULL");
        $service->bind_param('i', $serviceId);
        $service->execute();
        if (!$serviceId || $categoryName === '' || $service->get_result()->num_rows !== 1) {
            header('Location: pending_approvals.php?message=category_invalid');
            exit();
        }
        $category = $conn->prepare('INSERT INTO categories (category_name) VALUES (?) ON DUPLICATE KEY UPDATE category_id = LAST_INSERT_ID(category_id)');
        $category->bind_param('s', $categoryName);
        $category->execute();
        $categoryId = $conn->insert_id;
        $assign = $conn->prepare("UPDATE services SET category_id = ?, requested_category = NULL WHERE service_id = ? AND status = 'pending'");
        $assign->bind_param('ii', $categoryId, $serviceId);
        $assign->execute();
        header('Location: pending_approvals.php?message=category_added');
        exit();
    }

    if (in_array($_POST['action'] ?? '', ['approve', 'reject'], true)) {
        $serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
        $status = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
        if (!$serviceId) {
            header('Location: pending_approvals.php');
            exit();
        }
        $service = $conn->prepare("SELECT provider_id, title, category_id FROM services WHERE service_id = ? AND status = 'pending'");
        $service->bind_param('i', $serviceId);
        $service->execute();
        $item = $service->get_result()->fetch_assoc();
        if ($status === 'approved' && $item && $item['category_id'] === null) {
            header('Location: pending_approvals.php?message=category_required');
            exit();
        }
        if ($item) {
            $update = $conn->prepare("UPDATE services SET status = ? WHERE service_id = ? AND status = 'pending'");
            $update->bind_param('si', $status, $serviceId);
            $update->execute();
            if ($update->affected_rows === 1) {
                $message = 'Your service "' . $item['title'] . '" has been ' . $status . '.';
                $notice = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
                $notice->bind_param('is', $item['provider_id'], $message);
                $notice->execute();
            }
        }
        header('Location: pending_approvals.php?message=' . $status);
        exit();
    }
}
$sql = "SELECT s.service_id, s.title, s.description, s.created_at, s.requested_category, c.category_name, u.full_name, u.phone_number FROM services s LEFT JOIN categories c ON c.category_id = s.category_id JOIN users u ON u.user_id = s.provider_id WHERE s.status = 'pending' ORDER BY s.created_at ASC";
$services = $conn->query($sql);
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pending Approvals</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/style.css" rel="stylesheet"></head><body><div class="app-shell"><div class="app-sidebar-overlay" id="sidebar-overlay"></div><div class="app-sidebar"><button type="button" class="app-sidebar-close-btn" aria-label="Close navigation menu">&times;</button><div class="brand"><div class="split-login-mark" style="width:28px;height:28px;font-size:12px">S</div>SSMS</div><a href="dashboard.php">Dashboard</a><a href="manage_users.php">Manage Users</a><a href="manage_listings.php">Manage Listings</a><a href="pending_approvals.php" class="active">Pending Approvals</a><a href="manage_categories.php">Manage Categories</a><a href="notifications.php">Notifications</a><div class="logout-link"><form action="../auth/logout.php" method="post" class="logout-form"><?php echo csrf_field(); ?><button type="submit" class="logout-form-button">Logout</button></form></div></div><div class="app-main"><div class="app-topbar"><div class="d-flex align-items-center gap-3"><button type="button" id="hamburger-btn" class="app-hamburger-btn" aria-label="Open navigation menu"><span class="hamburger-icon"><span></span><span></span><span></span></span></button><span class="fw-semibold">Pending Approvals</span></div><div class="app-topbar-actions"><a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications">◌</a><button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name']); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'], 0, 1))); ?></button><button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button></div></div><main class="app-content"><div class="admin-toolbar"><div><h1>Pending Service Approvals</h1><p>Review new service submissions before they are visible to students.</p></div></div><?php if(($_GET['message']??'')==='category_added'):?><div class="alert alert-success">Category added and assigned. You can now approve the service.</div><?php elseif(($_GET['message']??'')==='category_required'):?><div class="alert alert-warning">Add and assign the requested category before approving this service.</div><?php elseif(($_GET['message']??'')==='category_invalid'):?><div class="alert alert-danger">The requested category could not be added.</div><?php elseif(isset($_GET['message'])):?><div class="alert alert-success">Service <?php echo htmlspecialchars($_GET['message']); ?> and the provider has been notified.</div><?php endif;?><?php if($services->num_rows===0):?><div class="listing-empty"><span class="listing-empty-icon">✓</span><p>There are no services waiting for approval.</p></div><?php else:?><div class="listing-grid"><?php while($service=$services->fetch_assoc()):?><article class="listing-card"><div class="listing-card-header"><span class="listing-badge listing-badge-category"><?php echo htmlspecialchars($service['category_name'] ?: $service['requested_category']);?></span><span class="listing-badge listing-badge-pending">Pending</span></div><h2 class="listing-title"><?php echo htmlspecialchars($service['title']);?></h2><p class="listing-description"><?php echo htmlspecialchars($service['description']);?></p><?php if($service['category_name'] === null):?><form method="post" class="mb-3"><?php echo csrf_field();?><input type="hidden" name="action" value="add_category"><input type="hidden" name="service_id" value="<?php echo (int)$service['service_id'];?>"><label class="form-label small">Requested category</label><div class="input-group"><input class="form-control" name="category_name" maxlength="50" required value="<?php echo htmlspecialchars($service['requested_category']);?>"><button class="btn btn-brand" type="submit">Add &amp; assign</button></div></form><?php endif;?><div class="listing-meta"><div class="listing-meta-item"><span>Provider: <?php echo htmlspecialchars($service['full_name']);?></span></div><div class="listing-meta-item"><span><?php echo htmlspecialchars($service['phone_number']);?></span></div></div><div class="listing-actions"><form method="post" class="d-inline"><?php echo csrf_field();?><input type="hidden" name="service_id" value="<?php echo (int)$service['service_id'];?>"><button class="listing-action-btn" type="submit" name="action" value="approve">Approve</button></form><form method="post" class="d-inline"><?php echo csrf_field();?><input type="hidden" name="service_id" value="<?php echo (int)$service['service_id'];?>"><button class="listing-action-btn listing-action-btn-danger" type="submit" name="action" value="reject">Reject</button></form></div></article><?php endwhile;?></div><?php endif;?></main></div></div><script src="../assets/js/script.js"></script></body></html>
