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

// Dibutuhkan di sini (bukan cuma di file pemanggil) supaya requireSiswa()/
// requirePetugas() di bawah bisa mengecek status akun ke database, terlepas
// dari urutan require_once di file yang memanggilnya. require_once aman
// dipanggil dua kali (di sini dan lagi di file pemanggil) karena PHP hanya
// menyertakan file yang sama satu kali.
require_once __DIR__ . '/../config/database.php';

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
    // Status hanya dicek saat login sebelumnya — akun yang baru saja
    // dinonaktifkan admin masih tetap dianggap sah selama sesinya hidup.
    // Satu query kecil ini memastikan status akun selalu dicek ulang di
    // setiap halaman siswa, bukan cuma sekali di awal sesi.
    global $koneksi;
    $stmt = $koneksi->prepare("SELECT status FROM anggota WHERE id_anggota = ?");
    $stmt->execute([$_SESSION['anggota_id']]);
    $status = $stmt->fetchColumn();
    if ($status !== 'aktif') {
        session_unset();
        session_destroy();
        header('Location: login.php?pesan=nonaktif');
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
    global $koneksi;
    $stmt = $koneksi->prepare("SELECT status FROM petugas WHERE id_petugas = ?");
    $stmt->execute([$_SESSION['petugas_id']]);
    $status = $stmt->fetchColumn();
    if ($status !== 'aktif') {
        session_unset();
        session_destroy();
        header('Location: login.php?pesan=nonaktif');
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

// === PEMBLOKIRAN PINJAM KARENA ADA BUKU TERLAMBAT ===
// Mengembalikan jumlah pinjaman aktif anggota yang sudah lewat jatuh tempo.
// Denda baru tercatat saat petugas mengonfirmasi pengembalian, jadi kalau
// hanya mengandalkan cek denda 'Belum Lunas', siswa yang menahan buku
// berminggu-minggu tetap lolos meminjam lagi. Definisi "terlambat" di sini
// SAMA dengan petugas/buku_terlambat.php:
//  - 'dipinjam'            -> dibandingkan dengan hari ini
//  - 'menunggu_konfirmasi' -> dibandingkan dengan tanggal siswa mengajukan kembali
function hitungPinjamanTerlambat(PDO $koneksi, $id_anggota) {
    $stmt = $koneksi->prepare("
        SELECT COUNT(*) FROM transaksi
        WHERE id_anggota = ?
          AND status IN ('dipinjam','menunggu_konfirmasi')
          AND (CASE WHEN status = 'menunggu_konfirmasi'
                    THEN COALESCE(tanggal_pengajuan_kembali, CURDATE())
                    ELSE CURDATE() END) > tanggal_jatuh_tempo
    ");
    $stmt->execute([$id_anggota]);
    return (int)$stmt->fetchColumn();
}