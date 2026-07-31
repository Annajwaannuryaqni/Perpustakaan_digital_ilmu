<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$daftarTransaksi = $koneksi->query("
    SELECT t.*, a.nama_lengkap, a.kelas, b.judul
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    JOIN buku b ON b.id_buku = t.id_buku
    ORDER BY t.id_transaksi DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Transaksi</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>


  <div class="container">
    <div class="page-head">
      <div>
        <h1>🔄 Data Transaksi Peminjaman</h1>
        <p>Total <?= count($daftarTransaksi) ?> transaksi tercatat.</p>
      </div>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <th>Judul Buku</th>
            <th>Tgl Pinjam</th>
            <th>Jatuh Tempo</th>
            <th>Tgl Kembali</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarTransaksi as $t): ?>
          <tr>
            <td data-label="Nama Siswa" style="font-weight:600;"><?= htmlspecialchars($t['nama_lengkap']) ?></td>
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
            <td data-label="Aksi">
              <a href="hapus_transaksi.php?id=<?= $t['id_transaksi'] ?>" class="btn-link" style="color:var(--coral);" onclick="return confirm('Hapus data transaksi ini?')">Hapus</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarTransaksi): ?>
          <tr><td colspan="8" style="text-align:center;">Belum ada transaksi</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
  </div>
</body>
</html>