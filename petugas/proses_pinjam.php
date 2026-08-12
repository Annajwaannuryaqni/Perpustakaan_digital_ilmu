<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

requireCsrf();

$id_petugas = $_SESSION['petugas_id'];
$id_anggota = filter_input(INPUT_POST, 'id_anggota', FILTER_VALIDATE_INT);
$id_buku    = filter_input(INPUT_POST, 'id_buku', FILTER_VALIDATE_INT);

if (!$id_anggota || !$id_buku) {
    header('Location: peminjaman.php?pesan=gagal');
    exit;
}

// Pastikan anggota masih valid & aktif
$cekAnggota = $koneksi->prepare("SELECT id_anggota FROM anggota WHERE id_anggota = ? AND status = 'aktif'");
$cekAnggota->execute([$id_anggota]);
if (!$cekAnggota->fetch()) {
    header('Location: peminjaman.php?pesan=gagal');
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
        header('Location: peminjaman.php?anggota=' . $id_anggota . '&pesan=gagal_stok');
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
        header('Location: peminjaman.php?anggota=' . $id_anggota . '&pesan=gagal_stok');
        exit;
    }

    $stmtTransaksi = $koneksi->prepare("
        INSERT INTO transaksi (id_anggota, id_petugas, id_buku, tanggal_pinjam, tanggal_jatuh_tempo, status)
        VALUES (?, ?, ?, ?, ?, 'dipinjam')
    ");
    $stmtTransaksi->execute([$id_anggota, $id_petugas, $id_buku, $tanggal_pinjam, $tanggal_jatuh_tempo]);

    $koneksi->commit();

    $_SESSION['flash_notif'] = [
        'title'   => 'Peminjaman Berhasil',
        'message' => 'Transaksi peminjaman berhasil dicatat.',
        'type'    => 'success',
        'icon'    => 'fa-circle-check',
        'color'   => '#22c55e',
    ];

    header('Location: dashboard.php');
    exit;

} catch (PDOException $e) {
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    header('Location: peminjaman.php?pesan=gagal');
    exit;
}
