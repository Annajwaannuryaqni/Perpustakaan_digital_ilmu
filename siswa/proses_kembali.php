<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';
require_once '../config/constants.php'; // TARIF_DENDA_PER_HARI
require_once '../includes/notification_helper.php';

$id_anggota = $_SESSION['anggota_id'];
requireCsrf();

$id_transaksi = filter_input(INPUT_POST, 'id_transaksi', FILTER_VALIDATE_INT);

if (!$id_transaksi) {
    header('Location: kembali.php');
    exit;
}

try {
    $koneksi->beginTransaction();

    // Kunci baris transaksi ini supaya tidak ada request lain (mis. klik dobel)
    // yang memprosesnya secara bersamaan.
    $cek = $koneksi->prepare("SELECT * FROM transaksi WHERE id_transaksi = ? AND id_anggota = ? AND status = 'dipinjam' FOR UPDATE");
    $cek->execute([$id_transaksi, $id_anggota]);
    $transaksi = $cek->fetch();

    if (!$transaksi) {
        $koneksi->rollBack();
        header('Location: kembali.php');
        exit;
    }

    // PERUBAHAN: pengembalian yang diajukan siswa sendiri TIDAK langsung
    // difinalisasi. Status hanya diubah menjadi "menunggu_konfirmasi";
    // penambahan stok buku baru diproses di petugas/proses_kembali.php saat
    // petugas mengecek fisik bukunya dan mengonfirmasi.
    //
    // FIX KEADILAN DENDA: tanggal siswa mengajukan pengembalian (hari ini)
    // DICATAT di sini lewat kolom tanggal_pengajuan_kembali. Nantinya
    // petugas/proses_kembali.php menghitung denda berdasarkan tanggal INI,
    // bukan tanggal petugas sempat klik konfirmasi. Sebelumnya denda
    // dihitung dari tanggal konfirmasi petugas, sehingga siswa yang sudah
    // mengajukan tepat waktu bisa dirugikan kalau petugasnya baru sempat
    // memproses beberapa hari kemudian.
    $tanggal_pengajuan = date('Y-m-d');
    $telat = $tanggal_pengajuan > $transaksi['tanggal_jatuh_tempo'];

    $stmt = $koneksi->prepare("
        UPDATE transaksi SET status = 'menunggu_konfirmasi', tanggal_pengajuan_kembali = ?
        WHERE id_transaksi = ?
    ");
    $stmt->execute([$tanggal_pengajuan, $id_transaksi]);

    $koneksi->commit();

    // Beri tahu admin & petugas bahwa ada pengembalian baru dari siswa.
    // Dibungkus try-catch sendiri supaya kegagalan kirim notifikasi tidak
    // membuat pengembalian yang sudah berhasil di-commit dianggap gagal.
    try {
        $infoNama = $koneksi->prepare("SELECT nama_lengkap FROM anggota WHERE id_anggota = ?");
        $infoNama->execute([$id_anggota]);
        $namaAnggota = $infoNama->fetchColumn() ?: 'Seorang siswa';

        $infoBuku = $koneksi->prepare("SELECT judul FROM buku WHERE id_buku = ?");
        $infoBuku->execute([$transaksi['id_buku']]);
        $judulBuku = $infoBuku->fetchColumn() ?: 'sebuah buku';

        $pesanNotif = $namaAnggota . ' mengajukan pengembalian buku "' . $judulBuku . '", menunggu konfirmasi petugas.';
        if ($telat) {
            $pesanNotif .= ' Buku ini sudah melewati jatuh tempo — denda akan dihitung saat petugas mengonfirmasi.';
        }
        notifyStaff($koneksi, 'Pengajuan Pengembalian', $pesanNotif, $telat ? 'warning' : 'success', 'fa-book-open', $telat ? '#d97706' : '#22c55e');
    } catch (PDOException $e) {
        // Notifikasi gagal terkirim, tapi pengembalian tetap sah — abaikan saja.
    }

    header('Location: kembali.php?pesan=diajukan');
    exit;

} catch (PDOException $e) {
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    header('Location: kembali.php?pesan=gagal');
    exit;
}