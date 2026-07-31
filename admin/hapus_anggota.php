<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: anggota.php');
    exit;
}
requireCsrf();

$id = $_POST['id'] ?? null;
if ($id) {
    $stmt = $koneksi->prepare("DELETE FROM anggota WHERE id_anggota = ?");
    $stmt->execute([$id]);
}
header('Location: anggota.php');
exit;