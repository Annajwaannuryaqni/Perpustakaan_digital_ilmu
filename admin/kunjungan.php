<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$daftarKunjungan = $koneksi->query("
    SELECT k.*, a.nama_lengkap, a.kelas
    FROM kunjungan k
    JOIN anggota a ON a.id_anggota = k.id_anggota
    ORDER BY k.waktu_kunjungan DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Kunjungan</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>


  <div class="container">
    <div class="page-head">
      <div>
        <h1>🕒 Daftar Kunjungan (Baca di Tempat)</h1>
        <p>Total <?= count($daftarKunjungan) ?> kunjungan tercatat.</p>
      </div>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <th>Tanggal</th>
            <th>Jam</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarKunjungan as $k): ?>
          <tr>
            <td data-label="Nama Siswa" style="font-weight:600;"><?= htmlspecialchars($k['nama_lengkap']) ?></td>
            <td data-label="Kelas"><?= htmlspecialchars($k['kelas']) ?></td>
            <td data-label="Tanggal"><?= date('d-m-Y', strtotime($k['waktu_kunjungan'])) ?></td>
            <td data-label="Jam"><?= date('H:i', strtotime($k['waktu_kunjungan'])) ?></td>
            <td data-label="Keterangan"><?= htmlspecialchars($k['keterangan']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarKunjungan): ?>
          <tr><td colspan="5" style="text-align:center;">Belum ada data kunjungan</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
  </div>
</body>
</html>