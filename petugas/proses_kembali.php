<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

const TARIF_DENDA_PER_HARI = 1000; // Rp1.000 / hari keterlambatan

requireCsrf();

$id_petugas = $_SESSION['petugas_id'];
$id_transaksi = filter_input(INPUT_POST, 'id_transaksi', FILTER_VALIDATE_INT);

if (!$id_transaksi) {
    header('Location: pengembalian.php');
    exit;
}

try {
    $koneksi->beginTransaction();

    // Kunci baris transaksi supaya tidak diproses dua kali secara bersamaan
    $cek = $koneksi->prepare("SELECT * FROM transaksi WHERE id_transaksi = ? AND status = 'dipinjam' FOR UPDATE");
    $cek->execute([$id_transaksi]);
    $transaksi = $cek->fetch();

    if (!$transaksi) {
        $koneksi->rollBack();
        header('Location: pengembalian.php');
        exit;
    }

    $hari_ini = date('Y-m-d');
    $telat = $hari_ini > $transaksi['tanggal_jatuh_tempo'];
    $status_baru = $telat ? 'terlambat' : 'dikembalikan';

    $denda = 0;
    if ($telat) {
        $hari_terlambat = floor((strtotime($hari_ini) - strtotime($transaksi['tanggal_jatuh_tempo'])) / 86400);
        $denda = $hari_terlambat * TARIF_DENDA_PER_HARI;
    }

    // Catat petugas yang memproses pengembalian ini (jika transaksi belum
    // punya id_petugas dari peminjaman awal, kolomnya diisi di sini).
    $stmt = $koneksi->prepare("
        UPDATE transaksi SET tanggal_kembali = ?, status = ?, denda = ?, id_petugas = COALESCE(id_petugas, ?)
        WHERE id_transaksi = ?
    ");
    $stmt->execute([$hari_ini, $status_baru, $denda, $id_petugas, $id_transaksi]);

    $stmt2 = $koneksi->prepare("UPDATE buku SET stok = stok + 1 WHERE id_buku = ?");
    $stmt2->execute([$transaksi['id_buku']]);

    $koneksi->commit();

    $_SESSION['flash_notif'] = [
        'title'   => 'Pengembalian Berhasil',
        'message' => $telat ? ('Buku dikembalikan dengan denda Rp' . number_format($denda, 0, ',', '.') . '.') : 'Buku berhasil dikembalikan.',
        'type'    => 'success',
        'icon'    => 'fa-circle-check',
        'color'   => '#22c55e',
    ];

    header('Location: pengembalian.php?pesan=sukses');
    exit;

} catch (PDOException $e) {
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    header('Location: pengembalian.php');
    exit;
}
