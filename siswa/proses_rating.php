<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$id_anggota    = $_SESSION['anggota_id'];
requireCsrf();

$id_transaksi  = $_POST['id_transaksi'] ?? null;
$nilai         = $_POST['nilai'] ?? null;
$isi_komentar  = trim($_POST['isi_komentar'] ?? '');

if (!$id_transaksi || !$nilai) {
    header('Location: kembali.php?pesan=sukses');
    exit;
}

$nilai = max(1, min(5, (int)$nilai));

// Pastikan transaksi ini benar-benar milik siswa yang sedang login & sudah selesai
$cek = $koneksi->prepare("
    SELECT id_buku FROM transaksi
    WHERE id_transaksi = ? AND id_anggota = ? AND status IN ('dikembalikan', 'terlambat')
");
$cek->execute([$id_transaksi, $id_anggota]);
$transaksi = $cek->fetch();

if ($transaksi) {
    $id_buku = $transaksi['id_buku'];

    // Simpan rating (1 rating per transaksi, dicegah oleh UNIQUE KEY di database)
    $stmtRating = $koneksi->prepare("
        INSERT INTO rating (id_buku, id_anggota, id_transaksi, nilai)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
    ");
    $stmtRating->execute([$id_buku, $id_anggota, $id_transaksi, $nilai]);

    // Simpan komentar kalau diisi (opsional)
    if ($isi_komentar !== '') {
        $stmtKomentar = $koneksi->prepare("
            INSERT INTO komentar (id_buku, id_anggota, id_transaksi, isi_komentar)
            VALUES (?, ?, ?, ?)
        ");
        $stmtKomentar->execute([$id_buku, $id_anggota, $id_transaksi, $isi_komentar]);
    }
}

header('Location: kembali.php?pesan=rating_sukses');
exit;