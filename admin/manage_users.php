<?php
    session_start();
    require '../config/db.php';
    require_once __DIR__ . '/../config/csrf.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../auth/login.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
        if (!csrf_validate_request()) {
            csrf_reject_request();
        }
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if ((int) $user_id !== (int) $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
        header("Location: manage_users.php?message=deleted");
        exit();
    }

    $search = trim($_GET['search'] ?? '');
    $role = $_GET['role'] ?? '';
    // Build optional search and role filters, binding user-supplied values as parameters.
    $sql = "SELECT * FROM users WHERE 1=1";
    $types = '';
    $params = [];
    if ($search !== '') { $sql .= " AND (full_name LIKE ? OR email LIKE ? OR phone_number LIKE ?)"; $term = '%' . $search . '%'; $types .= 'sss'; $params = [$term, $term, $term]; }
    if (in_array($role, ['student', 'service_provider', 'admin'], true)) { $sql .= " AND role = ?"; $types .= 's'; $params[] = $role; }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
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
        <a href="manage_users.php" class="active">Manage Users</a>
        <a href="manage_listings.php">Manage Listings</a>
        <a href="pending_approvals.php">Pending Approvals</a>
        <a href="manage_categories.php">Manage Categories</a>
        <a href="notifications.php">Notifications</a>
        <div class="logout-link">
            <form action="../auth/logout.php" method="post" class="logout-form"><?php echo csrf_field(); ?><button type="submit" class="logout-form-button">Logout</button></form>
        </div>
    </div>

    <div class="app-main">
        <div class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="hamburger-btn" class="app-hamburger-btn" aria-label="Open navigation menu"><span class="hamburger-icon"><span></span><span></span><span></span></span></button>
                <span class="fw-semibold">Manage Users</span>
            </div>
            <div class="app-topbar-actions">
                <a href="notifications.php" class="app-topbar-action-btn" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg></a>
                <button type="button" class="app-profile-btn" aria-label="Profile for <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))); ?></button>
                <button type="button" id="theme-toggle" class="app-topbar-icon-btn" aria-label="Switch to dark mode">🌙</button>
            </div>
        </div>

        <div class="app-content">
            <div class="admin-toolbar"><div><h1>Users</h1><p>Search and manage registered accounts.</p></div><form method="get" class="d-flex gap-2 admin-search"><input class="form-control" type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search users"><select class="form-select" name="role"><option value="">All roles</option><option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Students</option><option value="service_provider" <?php echo $role === 'service_provider' ? 'selected' : ''; ?>>Providers</option><option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admins</option></select><button class="btn btn-brand" type="submit">Search</button></form></div>
            <?php if (isset($_GET['message'])): ?><div class="alert alert-success">User removed successfully.</div><?php endif; ?>
            <div class="admin-panel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows === 0): ?><tr><td colspan="6" class="text-center text-muted py-5">No users match your search.</td></tr><?php endif; ?>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <?php $role_label = ucwords(str_replace(['_', '-'], ' ', $user['role'])); ?>
                            <tr>
                                <td><div class="user-identity"><span class="user-avatar"><?php if (!empty($user['profile_picture'])): ?><img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt=""><?php else: ?><?php echo htmlspecialchars(strtoupper(substr($user['full_name'], 0, 1))); ?><?php endif; ?></span><div><div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div><div class="user-subtext">Registered user</div></div></div></td>
                                <td><?php echo $user['email']; ?></td>
                                <td><span class="role-chip"><?php echo $role_label; ?></span></td>
                                <td><?php echo $user['phone_number']; ?></td>
                                <td><?php echo $user['created_at']; ?></td>
                                <td>
                                    <?php if ((int) $user['user_id'] !== (int) $_SESSION['user_id']): ?><form action="manage_users.php" method="post" class="d-inline confirm-delete-form">
                                        <?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                        <button type="submit" class="listing-action-btn listing-action-btn-danger confirm-delete">Delete</button>
                                    </form><?php else: ?><span class="user-subtext">Current account</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>
