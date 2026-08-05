<?php
/**
 * Membuat notifikasi baru untuk seorang user (admin atau anggota).
 * Panggil ini dari mana saja yang sudah require config/database.php (punya $koneksi).
 *
 * $user_type wajib diisi 'admin' atau 'anggota' supaya notifikasi nggak ketuker
 * antara admin dan siswa yang kebetulan punya ID sama.
 *
 * Contoh pemakaian (dari sisi siswa, misal setelah pinjam.php berhasil):
 *   require_once __DIR__ . '/notification_helper.php';
 *   createNotification($koneksi, $id_anggota, 'anggota', 'Peminjaman Berhasil', 'Buku "Laskar Pelangi" berhasil dipinjam.', 'success', 'fa-book', '#22c55e');
 *
 * Contoh pemakaian (kirim notifikasi ke admin, misal ada anggota baru daftar):
 *   createNotification($koneksi, $id_admin, 'admin', 'Anggota Baru', 'Ada pendaftaran anggota baru menunggu verifikasi.', 'info', 'fa-user-plus', '#4facfe');
 */
function createNotification($koneksi, $user_id, $user_type, $title, $message, $type = 'info', $icon = 'fa-bell', $color = '#00f2fe') {
    $stmt = $koneksi->prepare(
        "INSERT INTO notifications (user_id, user_type, title, message, type, icon, color, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())"
    );
    $stmt->execute([$user_id, $user_type, $title, $message, $type, $icon, $color]);
}