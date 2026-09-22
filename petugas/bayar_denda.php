<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: denda.php');
    exit;
}
requireCsrf();

$id_petugas = $_SESSION['petugas_id'];
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: denda.php');
    exit;
}

try {
    $koneksi->beginTransaction();

    $cek = $koneksi->prepare("\n        SELECT t.id_transaksi, t.denda, t.status_denda,\n               p.id_pembayaran, p.metode_pembayaran, p.file_bukti, p.status AS status_bukti\n        FROM transaksi t\n        LEFT JOIN pembayaran_denda p ON p.id_transaksi = t.id_transaksi\n        WHERE t.id_transaksi = ? AND t.denda > 0 AND t.status_denda = 'Belum Lunas'\n        FOR UPDATE\n    ");
    $cek->execute([$id]);
    $data = $cek->fetch();

    if (!$data) {
        $koneksi->rollBack();
        header('Location: denda.php');
        exit;
    }

    $metode = $data['metode_pembayaran'] ?? '';
    $statusBukti = $data['status_bukti'] ?? '';
    $fileBukti = $data['file_bukti'] ?? '';

    // Cash: petugas mengonfirmasi uang sudah diterima.
    // QRIS: petugas wajib melihat bukti terlebih dahulu.
    if ($metode === 'cash') {
        if ($statusBukti !== 'menunggu_verifikasi') {
            $koneksi->rollBack();
            header('Location: denda.php?pesan=pembayaran_belum_diajukan');
            exit;
        }
    } elseif ($metode === 'qris') {
        if (empty($fileBukti) || $statusBukti !== 'menunggu_verifikasi') {
            $koneksi->rollBack();
            header('Location: denda.php?pesan=bukti_belum_ada');
            exit;
        }
    } else {
        $koneksi->rollBack();
        header('Location: denda.php?pesan=metode_belum_dipilih');
        exit;
    }

    $stmt = $koneksi->prepare("\n        UPDATE transaksi\n        SET status_denda = 'Lunas', tanggal_bayar_denda = CURDATE()\n        WHERE id_transaksi = ? AND denda > 0 AND status_denda = 'Belum Lunas'\n    ");
    $stmt->execute([$id]);

    $stmtBukti = $koneksi->prepare("\n        UPDATE pembayaran_denda\n        SET status = 'diterima', diverifikasi_at = NOW(), diverifikasi_oleh = ?, catatan = NULL\n        WHERE id_transaksi = ?\n    ");
    $stmtBukti->execute([$id_petugas, $id]);

    $koneksi->commit();
    header('Location: denda.php?pesan=denda_lunas');
    exit;
} catch (PDOException $e) {
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    header('Location: denda.php');
    exit;
}
