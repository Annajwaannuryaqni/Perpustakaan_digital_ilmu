<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pembayaran_denda.php');
    exit;
}
requireCsrf();

$id_anggota = $_SESSION['anggota_id'];
$id_transaksi = filter_input(INPUT_POST, 'id_transaksi', FILTER_VALIDATE_INT);
$metode = $_POST['metode_pembayaran'] ?? '';

if (!$id_transaksi || !in_array($metode, ['cash', 'qris'], true)) {
    header('Location: pembayaran_denda.php?pesan=invalid');
    exit;
}

$cek = $koneksi->prepare("\n    SELECT id_transaksi, denda, status_denda\n    FROM transaksi\n    WHERE id_transaksi = ? AND id_anggota = ? AND denda > 0 AND status_denda = 'Belum Lunas'\n");
$cek->execute([$id_transaksi, $id_anggota]);
$transaksi = $cek->fetch();
if (!$transaksi) {
    header('Location: pembayaran_denda.php?pesan=gagal');
    exit;
}

$dir = dirname(__DIR__) . '/uploads/bukti_pembayaran';
$namaFile = null;
$target = null;

// QRIS wajib memiliki bukti; cash tidak perlu file.
if ($metode === 'qris') {
    if (!isset($_FILES['bukti'])) {
        header('Location: pembayaran_denda.php?pesan=invalid');
        exit;
    }
    $file = $_FILES['bukti'];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024) {
        header('Location: pembayaran_denda.php?pesan=invalid');
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf',
    ];
    if (!isset($allowed[$mime])) {
        header('Location: pembayaran_denda.php?pesan=invalid');
        exit;
    }

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        header('Location: pembayaran_denda.php?pesan=gagal');
        exit;
    }

    $namaFile = 'bukti_denda_' . $id_transaksi . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $namaFile;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        header('Location: pembayaran_denda.php?pesan=gagal');
        exit;
    }
}

$oldFile = null;
try {
    $koneksi->beginTransaction();

    $old = $koneksi->prepare("SELECT file_bukti, status, metode_pembayaran FROM pembayaran_denda WHERE id_transaksi = ? FOR UPDATE");
    $old->execute([$id_transaksi]);
    $existing = $old->fetch();
    $oldFile = $existing['file_bukti'] ?? null;

    // Satu transaksi denda hanya memiliki satu pengajuan aktif. Bila bukti QRIS ditolak,
    // siswa boleh mengajukan ulang dan boleh mengganti metode.
    $stmt = $koneksi->prepare("\n        INSERT INTO pembayaran_denda (id_transaksi, id_anggota, metode_pembayaran, file_bukti, uploaded_at, status, diverifikasi_at, diverifikasi_oleh, catatan)\n        VALUES (?, ?, ?, ?, NOW(), 'menunggu_verifikasi', NULL, NULL, NULL)\n        ON DUPLICATE KEY UPDATE\n            id_anggota = VALUES(id_anggota),\n            metode_pembayaran = VALUES(metode_pembayaran),\n            file_bukti = VALUES(file_bukti),\n            uploaded_at = NOW(),\n            status = 'menunggu_verifikasi',\n            diverifikasi_at = NULL,\n            diverifikasi_oleh = NULL,\n            catatan = NULL\n    ");
    $stmt->execute([$id_transaksi, $id_anggota, $metode, $namaFile]);
    $koneksi->commit();

    if ($oldFile && $oldFile !== $namaFile) {
        $oldPath = $dir . '/' . basename($oldFile);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    header('Location: pembayaran_denda.php?pesan=' . ($metode === 'cash' ? 'submitted_cash' : 'uploaded'));
    exit;
} catch (PDOException $e) {
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    if ($target && is_file($target)) {
        @unlink($target);
    }
    header('Location: pembayaran_denda.php?pesan=gagal');
    exit;
}
