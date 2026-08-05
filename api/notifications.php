<?php
session_start();
// Sesuaikan dengan koneksi database Anda (contoh: koneksi.php)
require_once '../koneksi.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'get') {
    // Ambil notifikasi user
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    
    $unread_count = 0;
    while ($row = $result->fetch_assoc()) {
        if ($row['is_read'] == 0) {
            $unread_count++;
        }
        // Format waktu relatif sederhana
        $row['time_ago'] = timeAgo($row['created_at']);
        $notifications[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'unread_count' => $unread_count,
        'notifications' => $notifications
    ]);
} 
elseif ($action == 'read_all') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
} 
elseif ($action == 'clear_all') {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
}
elseif ($action == 'read_one') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
}

// Fungsi pembantu waktu relatif
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $ago = $now - $time;
    
    if ($ago < 60) {
        return 'Baru saja';
    } elseif ($ago < 3600) {
        return floor($ago / 60) . ' menit yang lalu';
    } elseif ($ago < 86400) {
        return floor($ago / 3600) . ' jam yang lalu';
    } else {
        return floor($ago / 86400) . ' hari yang lalu';
    }
}
?>