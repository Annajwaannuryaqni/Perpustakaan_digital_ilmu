<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

requireCsrf();

// Aturan operasional peminjaman Petugas:
// Senin-Kamis 07:30-15:30 WIB
// Jumat       07:30-14:00 WIB
// Sabtu-Minggu ditolak.
date_default_timezone_set('Asia/Jakarta');

$hari = (int) date('N'); // 1=Senin ... 7=Minggu
$jamMenit = ((int) date('H') * 60) + (int) date('i');

$jamBuka = 7 * 60 + 30;
$jamTutup = null;

if ($hari >= 1 && $hari <= 4) {
    $jamTutup = 15 * 60 + 30;
} elseif ($hari === 5) {
    $jamTutup = 14 * 60;
}

if ($jamTutup === null || $jamMenit < $jamBuka || $jamMenit >= $jamTutup) {
    header('Location: peminjaman.php?pesan=di_luar_jam');
    exit;
}

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

// Siswa dengan denda yang belum lunas tidak boleh dipinjamkan buku baru
// sampai dendanya diselesaikan — aturan yang sama seperti di alur
// peminjaman mandiri siswa (siswa/pinjam_konfirmasi.php), supaya
// kebijakannya konsisten mau lewat jalur mana pun siswa meminjam.
$cekDenda = $koneksi->prepare("
    SELECT COALESCE(SUM(denda),0) AS total FROM transaksi
    WHERE id_anggota = ? AND status_denda = 'Belum Lunas'
");
$cekDenda->execute([$id_anggota]);
if ((float)$cekDenda->fetch()['total'] > 0) {
    header('Location: peminjaman.php?anggota=' . $id_anggota . '&pesan=ada_denda');
    exit;
}

// Anggota yang masih memegang buku lewat jatuh tempo juga tidak boleh
// dipinjamkan buku baru — aturan yang sama dengan alur peminjaman mandiri
// siswa (siswa/pinjam_konfirmasi.php), lewat fungsi bersama di auth.php.
if (hitungPinjamanTerlambat($koneksi, $id_anggota) > 0) {
    header('Location: peminjaman.php?anggota=' . $id_anggota . '&pesan=ada_terlambat');
    exit;
}

try {
    $koneksi->beginTransaction();

    // BUG FIX: cegah anggota memiliki lebih dari satu transaksi aktif untuk
    // buku yang sama (sebelumnya tidak dicek sama sekali di alur petugas ini).
    $cekAktif = $koneksi->prepare("
        SELECT id_transaksi FROM transaksi
        WHERE id_anggota = ? AND id_buku = ? AND status = 'dipinjam'
        FOR UPDATE
    ");
    $cekAktif->execute([$id_anggota, $id_buku]);
    if ($cekAktif->fetch()) {
        $koneksi->rollBack();
        header('Location: peminjaman.php?anggota=' . $id_anggota . '&pesan=gagal_duplikat');
        exit;
    }

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