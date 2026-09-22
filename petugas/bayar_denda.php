<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: denda.php');
    exit;
}
requireCsrf();

$id_petugas = $_SESSION['petugas_id'] ?? 0;
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$aksi = $_POST['aksi'] ?? 'terima';
$catatan = trim((string)($_POST['catatan'] ?? ''));

if (!$id || !in_array($aksi, ['terima', 'tolak'], true)) {
    header('Location: denda.php');
    exit;
}

try {
    $koneksi->beginTransaction();

    $cek = $koneksi->prepare("SELECT
            t.id_transaksi, t.denda, t.status_denda,
            p.id_pembayaran, p.metode_pembayaran, p.file_bukti, p.status AS status_bukti
        FROM transaksi t
        LEFT JOIN pembayaran_denda p ON p.id_transaksi = t.id_transaksi
        WHERE t.id_transaksi = ? AND t.denda > 0 AND t.status_denda = 'Belum Lunas'
        FOR UPDATE");
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

    if ($aksi === 'tolak') {
        // Hanya QRIS dengan bukti yang masih menunggu verifikasi yang boleh ditolak.
        // Denda tetap BELUM LUNAS agar siswa dapat memperbaiki pembayaran.
        if ($metode !== 'qris' || empty($fileBukti) || $statusBukti !== 'menunggu_verifikasi' || $catatan === '') {
            $koneksi->rollBack();
            header('Location: denda.php?pesan=bukti_tidak_valid');
            exit;
        }

        $catatan = substr($catatan, 0, 255);
        $stmt = $koneksi->prepare("UPDATE pembayaran_denda
            SET status = 'ditolak',
                diverifikasi_at = NOW(),
                diverifikasi_oleh = ?,
                catatan = ?
            WHERE id_transaksi = ?
              AND metode_pembayaran = 'qris'
              AND status = 'menunggu_verifikasi'");
        $stmt->execute([$id_petugas, $catatan, $id]);

        $koneksi->commit();
        header('Location: denda.php?pesan=bukti_ditolak');
        exit;
    }

    // Cash: petugas mengonfirmasi uang sudah diterima.
    // QRIS: petugas hanya bisa melunasi setelah bukti tersedia dan masih menunggu verifikasi.
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

    $stmt = $koneksi->prepare("UPDATE transaksi
        SET status_denda = 'Lunas', tanggal_bayar_denda = CURDATE()
        WHERE id_transaksi = ? AND denda > 0 AND status_denda = 'Belum Lunas'");
    $stmt->execute([$id]);

    $stmtBukti = $koneksi->prepare("UPDATE pembayaran_denda
        SET status = 'diterima', diverifikasi_at = NOW(), diverifikasi_oleh = ?, catatan = NULL
        WHERE id_transaksi = ?");
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
