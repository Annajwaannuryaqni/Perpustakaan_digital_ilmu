<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: buku.php');
    exit;
}
requireCsrf();

$id = $_POST['id'] ?? null;
if ($id) {
    $stmt = $koneksi->prepare("DELETE FROM buku WHERE id_buku = ?");
    $stmt->execute([$id]);
}
header('Location: buku.php');
exit;