<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: denda.php');
    exit;
}
requireCsrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id) {
    // Hanya menandai lunas transaksi yang benar-benar punya denda
    // dan belum ditandai lunas sebelumnya — mencegah tanggal_bayar_denda
    // tertimpa berulang kalau tombol ditekan dua kali.
    $stmt = $koneksi->prepare("
        UPDATE transaksi
        SET status_denda = 'Lunas', tanggal_bayar_denda = CURDATE()
        WHERE id_transaksi = ? AND denda > 0 AND status_denda = 'Belum Lunas'
    ");
    $stmt->execute([$id]);
}

header('Location: denda.php?pesan=denda_lunas');
exit;
