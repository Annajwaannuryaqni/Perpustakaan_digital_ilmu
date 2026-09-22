<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';
require_once '../config/constants.php'; // TARIF_DENDA_PER_HARI — satu sumber tarif denda untuk seluruh aplikasi

date_default_timezone_set('Asia/Jakarta');

requireCsrf();

$id_petugas = $_SESSION['petugas_id'];
$id_transaksi = filter_input(INPUT_POST, 'id_transaksi', FILTER_VALIDATE_INT);
$kondisi_buku = $_POST['kondisi_buku'] ?? '';
$catatan_kondisi = trim($_POST['catatan_kondisi'] ?? '');

if (!in_array($kondisi_buku, ['Baik', 'Rusak', 'Hilang'], true)) {
    header('Location: pengembalian.php?pesan=kondisi_tidak_valid');
    exit;
}
if (mb_strlen($catatan_kondisi) > 255) {
    $catatan_kondisi = mb_substr($catatan_kondisi, 0, 255);
}

if (!$id_transaksi) {
    header('Location: pengembalian.php');
    exit;
}

try {
    $koneksi->beginTransaction();

    // Kunci baris transaksi supaya tidak diproses dua kali secara bersamaan
    // Semua petugas aktif boleh memproses transaksi yang masih dipinjam maupun
    // yang sudah berstatus menunggu_konfirmasi. Ini memungkinkan petugas lain
    // membantu mengembalikan buku siswa yang datang langsung ke perpustakaan.
    $cek = $koneksi->prepare("SELECT * FROM transaksi WHERE id_transaksi = ? AND status IN ('dipinjam','menunggu_konfirmasi') FOR UPDATE");
    $cek->execute([$id_transaksi]);
    $transaksi = $cek->fetch();

    if (!$transaksi) {
        $koneksi->rollBack();
        header('Location: pengembalian.php');
        exit;
    }

    // Jika siswa sudah mengajukan pengembalian, gunakan tanggal pengajuan
    // agar siswa tidak dirugikan oleh keterlambatan proses petugas. Jika
    // transaksi masih berstatus dipinjam dan petugas menerima buku langsung,
    // gunakan tanggal hari ini sebagai patokan keterlambatan.
    $tanggal_patokan = $transaksi['tanggal_pengajuan_kembali'] ?? date('Y-m-d');
    $tanggal_kembali_resmi = date('Y-m-d');

    $telat = $tanggal_patokan > $transaksi['tanggal_jatuh_tempo'];
    $status_baru = $telat ? 'terlambat' : 'dikembalikan';

    $dendaKeterlambatan = 0;
    $hari_terlambat = 0;
    if ($telat) {
        $hari_terlambat = max(0, (int)floor((strtotime($tanggal_patokan) - strtotime($transaksi['tanggal_jatuh_tempo'])) / 86400));
        $dendaKeterlambatan = $hari_terlambat * TARIF_DENDA_PER_HARI;
    }

    // Denda kondisi ditambahkan ke denda keterlambatan.
    // Rusak = Rp20.000, Hilang = Rp50.000.
    // Laporan kehilangan akan ditambahkan pada Step 2.
    $dendaKondisi = match ($kondisi_buku) {
        'Rusak' => DENDA_BUKU_RUSAK,
        'Hilang' => DENDA_BUKU_HILANG,
        default => 0,
    };
    $denda = $dendaKeterlambatan + $dendaKondisi;

    // Catat petugas yang memproses transaksi jika sebelumnya belum punya
    // id_petugas (misalnya peminjaman mandiri siswa).
    // tanggal_pengajuan_kembali menyimpan tanggal siswa mengajukan kembali,
    // sedangkan tanggal_kembali menyimpan tanggal petugas benar-benar
    // mengonfirmasi dan menerima buku secara resmi.
    $stmt = $koneksi->prepare("
        UPDATE transaksi SET tanggal_kembali = ?, status = ?, denda = ?,
            kondisi_buku = ?, catatan_kondisi = ?, denda_kondisi = ?,
            status_denda = IF(? > 0, 'Belum Lunas', status_denda),
            id_petugas = COALESCE(id_petugas, ?)
        WHERE id_transaksi = ?
    ");
    $stmt->execute([
        $tanggal_kembali_resmi, $status_baru, $denda,
        $kondisi_buku, ($catatan_kondisi !== '' ? $catatan_kondisi : null), $dendaKondisi,
        $denda, $id_petugas, $id_transaksi
    ]);

    // Jika kondisi buku HILANG, buat laporan kehilangan otomatis.
    // Satu transaksi hanya boleh menghasilkan satu laporan kehilangan.
    if ($kondisi_buku === 'Hilang') {
        $stmtLaporan = $koneksi->prepare("
            INSERT INTO laporan_buku_hilang
                (id_transaksi, id_anggota, id_buku, id_petugas, tanggal_laporan, kondisi_buku, denda_kondisi, catatan)
            VALUES (?, ?, ?, ?, ?, 'Hilang', ?, ?)
            ON DUPLICATE KEY UPDATE
                id_petugas = VALUES(id_petugas),
                tanggal_laporan = VALUES(tanggal_laporan),
                denda_kondisi = VALUES(denda_kondisi),
                catatan = VALUES(catatan)
        " );
        $stmtLaporan->execute([
            $id_transaksi,
            $transaksi['id_anggota'],
            $transaksi['id_buku'],
            $id_petugas,
            $tanggal_kembali_resmi,
            $dendaKondisi,
            ($catatan_kondisi !== '' ? $catatan_kondisi : null)
        ]);
    }

    // Hanya kondisi Baik yang langsung kembali menjadi stok tersedia.
    // Rusak/Hilang tidak ditambahkan ke stok otomatis.
    if ($kondisi_buku === 'Baik') {
        $stmt2 = $koneksi->prepare("UPDATE buku SET stok = stok + 1 WHERE id_buku = ?");
        $stmt2->execute([$transaksi['id_buku']]);
    }

    $koneksi->commit();

    $_SESSION['flash_notif'] = [
        'title'   => 'Pengembalian Berhasil',
        'message' => ($denda > 0) ? ('Buku dikembalikan dalam kondisi ' . $kondisi_buku . ' dengan total denda Rp' . number_format($denda, 0, ',', '.') . '.') : ('Buku berhasil dikembalikan dalam kondisi ' . $kondisi_buku . '.'),
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