<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

$daftarTransaksi = $koneksi->query("
    SELECT t.*, a.nama_lengkap AS nama_anggota, a.kelas, b.judul, p.nama_lengkap AS nama_petugas
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    JOIN buku b ON b.id_buku = t.id_buku
    LEFT JOIN petugas p ON p.id_petugas = t.id_petugas
    ORDER BY t.id_transaksi DESC
")->fetchAll();

$activeMenu = 'aktivitas';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aktivitas Transaksi - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Aktivitas Transaksi</h1>
        <p>Total <?= count($daftarTransaksi) ?> transaksi tercatat.</p>
      </div>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Anggota</th>
            <th>Kelas</th>
            <th>Judul Buku</th>
            <th>Tgl Pinjam</th>
            <th>Jatuh Tempo</th>
            <th>Tgl Kembali</th>
            <th>Status</th>
            <th>Diproses Oleh</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarTransaksi as $t): ?>
          <tr>
            <td data-label="Anggota" style="font-weight:600;"><?= htmlspecialchars($t['nama_anggota']) ?></td>
            <td data-label="Kelas"><?= htmlspecialchars($t['kelas']) ?></td>
            <td data-label="Judul Buku"><?= htmlspecialchars($t['judul']) ?></td>
            <td data-label="Tgl Pinjam"><?= $t['tanggal_pinjam'] ?></td>
            <td data-label="Jatuh Tempo"><?= $t['tanggal_jatuh_tempo'] ?></td>
            <td data-label="Tgl Kembali"><?= $t['tanggal_kembali'] ?? '-' ?></td>
            <td data-label="Status">
              <?php if ($t['status'] === 'dipinjam'): ?>
                <span class="badge badge-pending">Dipinjam</span>
              <?php else: ?>
                <span class="badge badge-ok"><?= htmlspecialchars(ucfirst($t['status'])) ?></span>
              <?php endif; ?>
            </td>
            <td data-label="Diproses Oleh"><?= $t['nama_petugas'] ? htmlspecialchars($t['nama_petugas']) : '<span style="color:#94a3b8;">Swalayan</span>' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarTransaksi): ?>
          <tr><td colspan="8" style="text-align:center;">Belum ada transaksi</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </main>
</body>
</html>
