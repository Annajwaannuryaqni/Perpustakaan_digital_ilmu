<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

// Backend wajib memeriksa CSRF token, bukan hanya mengandalkan JavaScript
requireCsrf();

$id_anggota = $_SESSION['anggota_id'];
$id_buku = filter_input(INPUT_POST, 'id_buku', FILTER_VALIDATE_INT);

if (!$id_buku) {
    header('Location: pinjam.php?pesan=gagal');
    exit;
}

try {
    $koneksi->beginTransaction();

    // Kunci baris buku ini agar tidak ada request lain yang membaca stok basi
    // saat proses ini berjalan (mencegah race condition / peminjaman ganda).
    $cekBuku = $koneksi->prepare("SELECT id_buku, stok FROM buku WHERE id_buku = ? FOR UPDATE");
    $cekBuku->execute([$id_buku]);
    $buku = $cekBuku->fetch();

    if (!$buku || (int)$buku['stok'] < 1) {
        $koneksi->rollBack();
        header('Location: pinjam.php?pesan=gagal');
        exit;
    }

    $tanggal_pinjam = date('Y-m-d');
    $tanggal_jatuh_tempo = date('Y-m-d', strtotime('+7 days'));

    // Kurangi stok secara atomik: hanya berhasil jika stok memang masih > 0
    // saat statement ini dieksekusi (lapisan tambahan selain FOR UPDATE di atas).
    $stmtStok = $koneksi->prepare("UPDATE buku SET stok = stok - 1 WHERE id_buku = ? AND stok > 0");
    $stmtStok->execute([$id_buku]);

    if ($stmtStok->rowCount() !== 1) {
        $koneksi->rollBack();
        header('Location: pinjam.php?pesan=gagal');
        exit;
    }

    $stmtTransaksi = $koneksi->prepare("
        INSERT INTO transaksi (id_anggota, id_buku, tanggal_pinjam, tanggal_jatuh_tempo, status)
        VALUES (?, ?, ?, ?, 'dipinjam')
    ");
    $stmtTransaksi->execute([$id_anggota, $id_buku, $tanggal_pinjam, $tanggal_jatuh_tempo]);
    $id_transaksi = $koneksi->lastInsertId();

    $koneksi->commit();

    header('Location: bukti_peminjaman.php?id=' . $id_transaksi);
    exit;

} catch (PDOException $e) {
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    header('Location: pinjam.php?pesan=gagal');
    exit;
}
