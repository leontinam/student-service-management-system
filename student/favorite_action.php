<?php
    session_start();
    require '../config/db.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: ../auth/login.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: browse.php');
        exit();
    }
    if (!csrf_validate_request()) {
        csrf_reject_request();
    }

    // INSERT IGNORE avoids creating a duplicate favorite for the same student and service.
    if (isset($_POST['service_id'])) {
        $student_id = $_SESSION['user_id'];
        $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);

        $sql = "INSERT IGNORE INTO favorites (student_id, service_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $student_id, $service_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $find_provider = $conn->prepare("SELECT provider_id, title FROM services WHERE service_id = ?");
            $find_provider->bind_param("i", $service_id);
            $find_provider->execute();
            $service_info = $find_provider->get_result()->fetch_assoc();

            $provider_id = $service_info['provider_id'];
            $service_title = $service_info['title'];
            $notif_message = "A student favorited your service: " . $service_title;

            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notif_stmt->bind_param("is", $provider_id, $notif_message);
            $notif_stmt->execute();
        }
    }

    header("Location: browse.php");
    exit();
?>
