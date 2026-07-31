<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $koneksi->prepare("DELETE FROM transaksi WHERE id_transaksi = ?");
    $stmt->execute([$id]);
}
header('Location: transaksi.php');
exit;