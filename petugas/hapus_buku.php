<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_buku.php');
    exit;
}
requireCsrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id) {
    try {
        // Ambil dulu nama file cover-nya sebelum baris buku dihapus dari DB,
        // supaya filenya bisa ikut dihapus dari folder uploads/ setelah
        // penghapusan data berhasil (mencegah file cover jadi yatim/menumpuk).
        $cekCover = $koneksi->prepare("SELECT cover FROM buku WHERE id_buku = ?");
        $cekCover->execute([$id]);
        $coverLama = $cekCover->fetchColumn();

        $stmt = $koneksi->prepare("DELETE FROM buku WHERE id_buku = ?");
        $stmt->execute([$id]);

        // Data buku berhasil dihapus dari DB — baru sekarang aman hapus filenya.
        if ($coverLama) {
            $pathCoverLama = '../uploads/' . $coverLama;
            if (is_file($pathCoverLama)) {
                @unlink($pathCoverLama);
            }
        }
    } catch (PDOException $e) {
        // Kode 23000 = pelanggaran integrity constraint (mis. masih ada baris
        // transaksi yang mereferensikan buku ini lewat foreign key). Tangkap
        // di sini supaya admin dapat pesan yang jelas, bukan error PHP mentah.
        if ((int)$e->getCode() === 23000) {
            header('Location: data_buku.php?pesan=gagal_terpakai');
            exit;
        }
        header('Location: data_buku.php?pesan=gagal_lain');
        exit;
    }
}
header('Location: data_buku.php');
exit;