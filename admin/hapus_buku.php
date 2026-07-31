<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $koneksi->prepare("DELETE FROM buku WHERE id_buku = ?");
    $stmt->execute([$id]);
}
header('Location: buku.php');
exit;