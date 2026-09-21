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

/**
 * Kirim notifikasi yang sama ke SEMUA admin dan petugas sekaligus.
 * Dipanggil saat ada aktivitas siswa yang perlu diketahui staff (peminjaman baru, dsb).
 *
 * CATATAN: fungsi ini mengasumsikan nama kolom primary key id_admin (tabel admin)
 * dan id_petugas (tabel petugas). Kalau nama kolom di database kamu berbeda,
 * sesuaikan dulu di bagian SELECT dan $a['...']/$p['...'] di bawah ini.
 */
function notifyStaff($koneksi, $title, $message, $type = 'info', $icon = 'fa-bell', $color = '#00f2fe') {
    // Kirim ke semua admin
    $admins = $koneksi->query("SELECT id_admin FROM admin")->fetchAll();
    foreach ($admins as $a) {
        createNotification($koneksi, $a['id_admin'], 'admin', $title, $message, $type, $icon, $color);
    }

    // Kirim ke semua petugas
    $petugas = $koneksi->query("SELECT id_petugas FROM petugas")->fetchAll();
    foreach ($petugas as $p) {
        createNotification($koneksi, $p['id_petugas'], 'petugas', $title, $message, $type, $icon, $color);
    }
}