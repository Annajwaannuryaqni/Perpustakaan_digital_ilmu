<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $koneksi->prepare("DELETE FROM anggota WHERE id_anggota = ?");
    $stmt->execute([$id]);
}
header('Location: anggota.php');
exit;