<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$id_anggota = $_SESSION['anggota_id'];
$pesan = '';

// Cek apakah siswa ini sudah presensi HARI INI
$cek = $koneksi->prepare("
    SELECT id_kunjungan FROM kunjungan
    WHERE id_anggota = ? AND DATE(waktu_kunjungan) = CURDATE()
");
$cek->execute([$id_anggota]);
$sudahPresensi = $cek->fetch();

// Kalau tombol presensi ditekan dan belum presensi hari ini -> simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$sudahPresensi) {
    requireCsrf();

    $stmt = $koneksi->prepare("INSERT INTO kunjungan (id_anggota) VALUES (?)");
    $stmt->execute([$id_anggota]);
    $sudahPresensi = true;
    $pesan = 'sukses';
}

// Ambil riwayat kunjungan siswa ini (5 terakhir)
$riwayat = $koneksi->prepare("
    SELECT * FROM kunjungan WHERE id_anggota = ? ORDER BY waktu_kunjungan DESC LIMIT 5
");
$riwayat->execute([$id_anggota]);
$riwayat = $riwayat->fetchAll();

// Waktu kunjungan hari ini untuk ditampilkan di status card (ambil dari riwayat, tanpa query baru)
$kunjunganHariIni = null;
if ($sudahPresensi && $riwayat) {
    foreach ($riwayat as $r) {
        if (date('Y-m-d', strtotime($r['waktu_kunjungan'])) === date('Y-m-d')) {
            $kunjunganHariIni = $r;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Presensi Kunjungan</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-page">
  <?php $activeMenu = 'presensi'; require '../includes/siswa_sidebar.php'; ?>
  <div class="topbar">
    <h1>Presensi Kunjungan Perpustakaan</h1>
  </div>

  <div class="container">
    <div class="section-header" style="margin-top:18px;">
      <h1>Presensi Kunjungan</h1>
      <p>Datang untuk membaca di tempat tanpa meminjam buku? Presensi dulu di sini.</p>
    </div>

    <?php if ($pesan === 'sukses'): ?>
      <p class="alert alert-sukses">Presensi berhasil! Selamat membaca.</p>
    <?php endif; ?>

    <div class="card">
      <?php if ($sudahPresensi): ?>
        <div class="status-cta done">
          <div class="status-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><polyline points="8 12.5 11 15.5 16 9"/></svg>
          </div>
          <h3>Presensi Berhasil</h3>
          <p>Kamu sudah presensi hari ini. Selamat membaca!</p>
          <?php if ($kunjunganHariIni): ?>
          <div class="meta-time">
            <div>
              <strong><?= date('d-m-Y', strtotime($kunjunganHariIni['waktu_kunjungan'])) ?></strong>
              <span>Tanggal</span>
            </div>
            <div>
              <strong><?= date('H:i', strtotime($kunjunganHariIni['waktu_kunjungan'])) ?></strong>
              <span>Jam Kunjungan</span>
            </div>
          </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="status-cta pending">
          <div class="status-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><polyline points="12 7 12 12 15.5 14"/></svg>
          </div>
          <h3>Belum Presensi Hari Ini</h3>
          <p>Datang untuk membaca di tempat tanpa meminjam buku? Presensi dulu di sini.</p>
          <form method="POST">
            <?= csrfField() ?>
            <button type="submit" class="btn">Presensi Sekarang</button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Riwayat Kunjungan Kamu</h3>
      <?php if ($riwayat): ?>
      <table>
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Jam</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($riwayat as $r): ?>
          <tr>
            <td data-label="Tanggal"><?= date('d-m-Y', strtotime($r['waktu_kunjungan'])) ?></td>
            <td data-label="Jam"><?= date('H:i', strtotime($r['waktu_kunjungan'])) ?></td>
            <td data-label="Keterangan"><?= htmlspecialchars($r['keterangan']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <strong>Belum ada riwayat kunjungan</strong>
          <span>Riwayat presensimu akan muncul di sini.</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>