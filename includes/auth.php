<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buat token CSRF sekali per sesi, dipakai di semua form yang mengubah/menghapus data
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Panggil di dalam <form> pada halaman admin: <?= csrfField() ?>
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

// Panggil di awal file proses (proses_*.php / hapus_*.php) sebelum eksekusi query
function requireCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Permintaan ditolak: token keamanan (CSRF) tidak valid atau kadaluarsa. Silakan kembali dan coba lagi.');
    }
}

// Panggil di awal halaman khusus admin
function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Panggil di awal halaman khusus siswa
function requireSiswa() {
    if (!isset($_SESSION['anggota_id'])) {
        header('Location: login.php');
        exit;
    }
}