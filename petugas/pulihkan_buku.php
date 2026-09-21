<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_buku.php?mode=arsip');
    exit;
}

requireCsrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: data_buku.php?mode=arsip&pesan=gagal_pulihkan');
    exit;
}

try {
    $stmt = $koneksi->prepare('UPDATE buku SET deleted_at = NULL WHERE id_buku = ? AND deleted_at IS NOT NULL');
    $stmt->execute([$id]);
    header('Location: data_buku.php?mode=arsip&pesan=berhasil_pulihkan');
    exit;
} catch (PDOException $e) {
    error_log('Pulihkan buku error: ' . $e->getMessage());
    header('Location: data_buku.php?mode=arsip&pesan=gagal_pulihkan');
    exit;
}
