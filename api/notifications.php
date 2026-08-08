<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php'; // menyediakan $koneksi (PDO)

header('Content-Type: application/json');

// Dukung admin maupun siswa/anggota yang sedang login
if (isset($_SESSION['admin_id'])) {
    $user_id   = $_SESSION['admin_id'];
    $user_type = 'admin';
} elseif (isset($_SESSION['anggota_id'])) {
    $user_id   = $_SESSION['anggota_id'];
    $user_type = 'anggota';
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

$action = $_GET['action'] ?? '';

// Endpoint ini hanya digunakan untuk mengambil notifikasi.
// Tidak ada fitur tandai sudah dibaca, clear, atau hapus notifikasi.
if ($action === 'get') {
    $stmt = $koneksi->prepare("
        SELECT *
        FROM notifications
        WHERE user_id = ? AND user_type = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");

    $stmt->execute([$user_id, $user_type]);
    $notifications = $stmt->fetchAll();

    $unread_count = 0;

    foreach ($notifications as &$row) {
        if ($row['is_read'] == 0) {
            $unread_count++;
        }

        $row['time_ago'] = timeAgo($row['created_at']);
    }

    unset($row);

    echo json_encode([
        'status' => 'success',
        'unread_count' => $unread_count,
        'notifications' => $notifications
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Aksi tidak dikenali'
    ]);
}

function timeAgo($datetime) {
    $ago = time() - strtotime($datetime);

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