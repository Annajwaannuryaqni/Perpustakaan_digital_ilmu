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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Presensi Kunjungan</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div class="topbar">
    <h1> Presensi Kunjungan Perpustakaan</h1>
  </div>

  <div class="container">
    <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>

    <div class="card" style="text-align:center;">
      <?php if ($pesan === 'sukses'): ?>
        <p class="alert alert-sukses">Presensi berhasil! Selamat membaca </p>
      <?php endif; ?>

      <?php if ($sudahPresensi): ?>
        <p style="color:var(--seaweed); font-weight:600; font-size:1.1rem;">
           Kamu sudah presensi hari ini. Selamat membaca!
        </p>
      <?php else: ?>
        <p style="color:var(--text-muted);">Datang untuk membaca di tempat tanpa meminjam buku? Presensi dulu di sini.</p>
        <form method="POST">
          <button type="submit" class="btn"> Presensi Sekarang</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Riwayat Kunjungan Kamu</h3>
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
          <?php if (!$riwayat): ?>
          <tr><td colspan="3" style="text-align:center;">Belum ada riwayat kunjungan</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>