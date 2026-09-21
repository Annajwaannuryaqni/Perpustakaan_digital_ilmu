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
} elseif (isset($_SESSION['petugas_id'])) {
    $user_id   = $_SESSION['petugas_id'];
    $user_type = 'petugas';
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

$action = $_GET['action'] ?? '';

// Endpoint ini dipakai untuk mengambil notifikasi sekaligus otomatis
// menandainya sudah dibaca (lihat bagian bawah). Tidak ada fitur clear
// atau hapus notifikasi secara manual.
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

    // Setelah diambil & (akan) ditampilkan ke user, langsung tandai sudah dibaca.
    // FIX: sebelumnya is_read TIDAK PERNAH diubah jadi 1 di mana pun, jadi
    // unread_count tidak pernah berkurang. Di sisi lain, notification.js
    // menyimpan "lastUnread" cuma sebagai variabel JS biasa yang reset ke 0
    // setiap halaman dimuat ulang — akibatnya toast+suara notifikasi yang
    // SAMA (mis. "si A meminjam buku X") muncul lagi setiap kali admin/
    // petugas membuka ulang halaman dashboard, walau notifikasi itu sudah
    // pernah tampil sebelumnya. Menandai sudah dibaca di sini membuat setiap
    // notifikasi hanya tampil sekali secara wajar.
    if ($unread_count > 0) {
        $tandaiDibaca = $koneksi->prepare("
            UPDATE notifications SET is_read = 1
            WHERE user_id = ? AND user_type = ? AND is_read = 0
        ");
        $tandaiDibaca->execute([$user_id, $user_type]);
    }

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