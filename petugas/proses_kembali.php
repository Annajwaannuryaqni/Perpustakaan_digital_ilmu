<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';
require_once '../config/constants.php'; // TARIF_DENDA_PER_HARI — satu sumber tarif denda untuk seluruh aplikasi

date_default_timezone_set('Asia/Jakarta');

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
    // Petugas hanya boleh MENGONFIRMASI pengajuan pengembalian yang sudah
    // diajukan siswa sendiri (status 'menunggu_konfirmasi') — bukan
    // memproses/"membantu mengembalikan" buku yang masih berstatus
    // 'dipinjam' dan belum pernah diajukan pengembaliannya oleh siswa.
    $cek = $koneksi->prepare("SELECT * FROM transaksi WHERE id_transaksi = ? AND status = 'menunggu_konfirmasi' FOR UPDATE");
    $cek->execute([$id_transaksi]);
    $transaksi = $cek->fetch();

    if (!$transaksi) {
        $koneksi->rollBack();
        header('Location: pengembalian.php');
        exit;
    }

    // FIX KEADILAN DENDA: pakai tanggal SISWA MENGAJUKAN pengembalian
    // (tanggal_pengajuan_kembali) sebagai patokan hitung telat/denda —
    // BUKAN tanggal hari ini saat petugas kebetulan sempat konfirmasi.
    // Kalau siswa mengajukan tepat waktu tapi petugas baru sempat
    // memproses beberapa hari kemudian, siswa TIDAK ikut dirugikan oleh
    // keterlambatan proses petugas.
    //
    // Fallback ke tanggal hari ini hanya untuk transaksi lama yang sudah
    // terlanjur berstatus 'menunggu_konfirmasi' SEBELUM kolom ini ada
    // (nilainya masih NULL), supaya tidak error.
    $tanggal_pengajuan = $transaksi['tanggal_pengajuan_kembali'] ?? date('Y-m-d');
    $tanggal_kembali_resmi = date('Y-m-d');

    $telat = $tanggal_pengajuan > $transaksi['tanggal_jatuh_tempo'];
    $status_baru = $telat ? 'terlambat' : 'dikembalikan';

    $denda = 0;
    if ($telat) {
        $hari_terlambat = floor((strtotime($tanggal_pengajuan) - strtotime($transaksi['tanggal_jatuh_tempo'])) / 86400);
        $denda = $hari_terlambat * TARIF_DENDA_PER_HARI;
    }

    // Catat petugas yang memproses transaksi jika sebelumnya belum punya
    // id_petugas (misalnya peminjaman mandiri siswa).
    // tanggal_pengajuan_kembali menyimpan tanggal siswa mengajukan kembali,
    // sedangkan tanggal_kembali menyimpan tanggal petugas benar-benar
    // mengonfirmasi dan menerima buku secara resmi.
    $stmt = $koneksi->prepare("
        UPDATE transaksi SET tanggal_kembali = ?, status = ?, denda = ?,
            status_denda = IF(? > 0, 'Belum Lunas', status_denda),
            id_petugas = COALESCE(id_petugas, ?)
        WHERE id_transaksi = ?
    ");
    $stmt->execute([$tanggal_kembali_resmi, $status_baru, $denda, $denda, $id_petugas, $id_transaksi]);

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

    // Alur: ada denda -> ke halaman denda untuk dibayar; tidak ada -> kembali ke daftar pengembalian
    if ($denda > 0) {
        header('Location: denda.php?pesan=kembali_denda');
    } else {
        header('Location: pengembalian.php?pesan=sukses');
    }
    exit;

} catch (PDOException $e) {
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    header('Location: pengembalian.php');
    exit;
}