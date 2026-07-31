<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

const TARIF_DENDA_PER_HARI = 1000; // Rp1.000 / hari keterlambatan

$id_anggota = $_SESSION['anggota_id'];
$id_transaksi = $_POST['id_transaksi'] ?? null;

if (!$id_transaksi) {
    header('Location: kembali.php');
    exit;
}

$cek = $koneksi->prepare("SELECT * FROM transaksi WHERE id_transaksi = ? AND id_anggota = ? AND status = 'dipinjam'");
$cek->execute([$id_transaksi, $id_anggota]);
$transaksi = $cek->fetch();

if ($transaksi) {
    $hari_ini = date('Y-m-d');
    $telat = $hari_ini > $transaksi['tanggal_jatuh_tempo'];
    $status_baru = $telat ? 'terlambat' : 'dikembalikan';

    // Hitung denda: selisih hari antara jatuh tempo dan tanggal kembali (hari ini)
    $denda = 0;
    if ($telat) {
        $hari_terlambat = floor((strtotime($hari_ini) - strtotime($transaksi['tanggal_jatuh_tempo'])) / 86400);
        $denda = $hari_terlambat * TARIF_DENDA_PER_HARI;
    }

    $stmt = $koneksi->prepare("
        UPDATE transaksi SET tanggal_kembali = ?, status = ?, denda = ?
        WHERE id_transaksi = ?
    ");
    $stmt->execute([$hari_ini, $status_baru, $denda, $id_transaksi]);

    $stmt2 = $koneksi->prepare("UPDATE buku SET stok = stok + 1 WHERE id_buku = ?");
    $stmt2->execute([$transaksi['id_buku']]);

    header('Location: kembali.php?pesan=sukses&rate=' . $id_transaksi);
    exit;
}

header('Location: kembali.php?pesan=sukses');
exit;