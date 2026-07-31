<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: transaksi.php');
    exit;
}
requireCsrf();

$id = $_POST['id'] ?? null;
if ($id) {
    $stmt = $koneksi->prepare("DELETE FROM transaksi WHERE id_transaksi = ?");
    $stmt->execute([$id]);
}
header('Location: transaksi.php');
exit;