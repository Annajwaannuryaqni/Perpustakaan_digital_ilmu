<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_buku.php');
    exit;
}
requireCsrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id) {
    try {
        // Soft delete: buku tetap tersimpan agar riwayat transaksi tidak ikut hilang.
        $stmt = $koneksi->prepare("UPDATE buku SET deleted_at = NOW() WHERE id_buku = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        header('Location: data_buku.php?pesan=gagal_lain');
        exit;
    }
}
header('Location: data_buku.php?pesan=berhasil_hapus');
exit;