<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // aktifkan baris ini kalau situs sudah diakses via HTTPS
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function requireCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Permintaan ditolak: token keamanan (CSRF) tidak valid atau kadaluarsa. Silakan kembali dan coba lagi.');
    }
}

function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireSiswa() {
    if (!isset($_SESSION['anggota_id'])) {
        header('Location: login.php');
        exit;
    }
}

// === PETUGAS HELPERS ===
function isPetugas() {
    return isset($_SESSION['petugas_id']);
}

function requirePetugas() {
    if (!isPetugas()) {
        header('Location: login.php');
        exit;
    }
}

function petugasName() {
    return $_SESSION['petugas_nama'] ?? null;
}

// === PEMBATASAN PERCOBAAN LOGIN (ANTI BRUTE-FORCE) ===
// $role dibedakan per jenis akun ('admin', 'petugas', 'siswa') supaya
// percobaan gagal di satu role tidak memblokir role lain.
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_BLOCK_SECONDS', 300); // 5 menit

// Mengembalikan false jika belum diblokir, atau jumlah detik sisa blokir jika masih diblokir.
function isLoginBlocked($role) {
    $attempts = $_SESSION['login_attempts_' . $role] ?? 0;
    $blockTime = $_SESSION['login_block_time_' . $role] ?? 0;
    $sisaWaktu = LOGIN_BLOCK_SECONDS - (time() - $blockTime);

    if ($attempts >= LOGIN_MAX_ATTEMPTS && $sisaWaktu > 0) {
        return $sisaWaktu;
    }

    // Waktu blokir sudah lewat, reset hitungan supaya user bisa coba lagi.
    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        unset($_SESSION['login_attempts_' . $role], $_SESSION['login_block_time_' . $role]);
    }
    return false;
}

function recordFailedLogin($role) {
    $_SESSION['login_attempts_' . $role] = ($_SESSION['login_attempts_' . $role] ?? 0) + 1;
    $_SESSION['login_block_time_' . $role] = time();
}

function clearLoginAttempts($role) {
    unset($_SESSION['login_attempts_' . $role], $_SESSION['login_block_time_' . $role]);
}