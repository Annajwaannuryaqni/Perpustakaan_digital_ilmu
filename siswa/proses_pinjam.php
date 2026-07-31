<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$id_anggota = $_SESSION['anggota_id'];
$id_buku = $_POST['id_buku'] ?? null;

if (!$id_buku) {
    header('Location: pinjam.php');
    exit;
}

// Cek dulu stok buku masih ada atau tidak (mencegah pinjam buku yang stoknya sudah 0)
$cekBuku = $koneksi->prepare("SELECT stok FROM buku WHERE id_buku = ?");
$cekBuku->execute([$id_buku]);
$buku = $cekBuku->fetch();

if (!$buku || $buku['stok'] < 1) {
    header('Location: pinjam.php?pesan=gagal');
    exit;
}

$tanggal_pinjam = date('Y-m-d');                     // hari ini
$tanggal_jatuh_tempo = date('Y-m-d', strtotime('+7 days')); // 7 hari dari sekarang

// 1. Catat transaksi baru
$stmt = $koneksi->prepare("
    INSERT INTO transaksi (id_anggota, id_buku, tanggal_pinjam, tanggal_jatuh_tempo, status)
    VALUES (?, ?, ?, ?, 'dipinjam')
");
$stmt->execute([$id_anggota, $id_buku, $tanggal_pinjam, $tanggal_jatuh_tempo]);

// 2. Kurangi stok buku sebanyak 1
$stmt2 = $koneksi->prepare("UPDATE buku SET stok = stok - 1 WHERE id_buku = ?");
$stmt2->execute([$id_buku]);

header('Location: pinjam.php?pesan=sukses');
exit;