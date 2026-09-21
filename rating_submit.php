<?php
/**
 * Endpoint AJAX untuk menyimpan rating dari modal detail buku di index.php
 * (landing page publik). Dipanggil lewat fetch('rating_submit.php', ...).
 *
 * Sengaja TIDAK memakai includes/auth.php (index.php juga tidak memakainya),
 * jadi validasi CSRF di sini memakai token terpisah $_SESSION['rating_csrf']
 * yang sudah dibuat oleh index.php, dikirim balik lewat field POST 'csrf'.
 */

session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

function jsonGagal($pesan, $kode = 400) {
    http_response_code($kode);
    echo json_encode(['success' => false, 'message' => $pesan]);
    exit;
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonGagal('Metode tidak diizinkan.', 405);
}

// Harus login sebagai anggota (siswa)
if (empty($_SESSION['anggota_id'])) {
    jsonGagal('Silakan login sebagai anggota untuk memberikan rating.', 401);
}
$id_anggota = (int) $_SESSION['anggota_id'];

// Validasi CSRF token (token khusus rating yang dibuat index.php)
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['rating_csrf'] ?? '', $csrf)) {
    jsonGagal('Token keamanan tidak valid atau kadaluarsa. Silakan muat ulang halaman.', 403);
}

// Validasi input
$id_buku = filter_input(INPUT_POST, 'id_buku', FILTER_VALIDATE_INT);
$nilai   = filter_input(INPUT_POST, 'nilai', FILTER_VALIDATE_INT);

if (!$id_buku) {
    jsonGagal('Buku tidak valid.');
}
if (!$nilai || $nilai < 1 || $nilai > 5) {
    jsonGagal('Rating harus antara 1 sampai 5.');
}

// Cari transaksi peminjaman TERAKHIR milik anggota ini untuk buku tsb,
// dan pastikan statusnya sudah selesai (buku sudah dikembalikan).
// Aturan ini harus sama persis dengan siswa/proses_rating.php supaya
// tidak ada celah: siswa hanya boleh menilai buku yang benar-benar
// sudah pernah ia pinjam & kembalikan.
$cek = $koneksi->prepare("
    SELECT id_transaksi
    FROM transaksi
    WHERE id_anggota = ? AND id_buku = ? AND status IN ('dikembalikan', 'terlambat')
    ORDER BY id_transaksi DESC
    LIMIT 1
");
$cek->execute([$id_anggota, $id_buku]);
$transaksi = $cek->fetch();

if (!$transaksi) {
    jsonGagal('Kamu belum memiliki riwayat peminjaman buku ini yang sudah selesai, jadi belum bisa memberi rating.');
}

$id_transaksi = $transaksi['id_transaksi'];

try {
    // 1 rating per transaksi (dicegah juga oleh UNIQUE KEY di database),
    // kalau sudah pernah menilai, nilai lama akan diperbarui.
    $stmt = $koneksi->prepare("
        INSERT INTO rating (id_buku, id_anggota, id_transaksi, nilai)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
    ");
    $stmt->execute([$id_buku, $id_anggota, $id_transaksi, $nilai]);

    echo json_encode([
        'success' => true,
        'message' => 'Rating berhasil disimpan, terima kasih!',
    ]);
} catch (PDOException $e) {
    jsonGagal('Terjadi kesalahan saat menyimpan rating. Silakan coba lagi.', 500);
}
