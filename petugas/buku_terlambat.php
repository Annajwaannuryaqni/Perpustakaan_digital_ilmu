<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

const TARIF_DENDA_PER_HARI = 1000;

$daftarTerlambat = $koneksi->query("
    SELECT t.*, a.nama_lengkap AS nama_anggota, a.nis, a.kelas, b.judul,
           DATEDIFF(CURDATE(), t.tanggal_jatuh_tempo) AS hari_terlambat
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.status = 'dipinjam' AND t.tanggal_jatuh_tempo < CURDATE()
    ORDER BY t.tanggal_jatuh_tempo ASC
")->fetchAll();

$activeMenu = 'terlambat';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buku Terlambat - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Buku Terlambat</h1>
        <p>Total <?= count($daftarTerlambat) ?> transaksi peminjaman yang melewati jatuh tempo.</p>
      </div>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Anggota</th>
            <th>Kelas</th>
            <th>Judul Buku</th>
            <th>Jatuh Tempo</th>
            <th>Hari Terlambat</th>
            <th>Estimasi Denda</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarTerlambat as $d):
            $denda = (int)$d['hari_terlambat'] * TARIF_DENDA_PER_HARI;
          ?>
          <tr>
            <td data-label="Anggota" style="font-weight:600;"><?= htmlspecialchars($d['nama_anggota']) ?> <br><small style="color:var(--muted); font-weight:400;">NIS <?= htmlspecialchars($d['nis']) ?></small></td>
            <td data-label="Kelas"><?= htmlspecialchars($d['kelas']) ?></td>
            <td data-label="Judul Buku"><?= htmlspecialchars($d['judul']) ?></td>
            <td data-label="Jatuh Tempo"><?= $d['tanggal_jatuh_tempo'] ?></td>
            <td data-label="Hari Terlambat"><span class="badge badge-habis"><?= (int)$d['hari_terlambat'] ?> hari</span></td>
            <td data-label="Estimasi Denda">Rp<?= number_format($denda, 0, ',', '.') ?></td>
            <td data-label="Aksi">
              <form method="POST" action="proses_kembali.php" onsubmit="return confirm('Proses pengembalian buku ini? Denda: Rp<?= number_format($denda, 0, ',', '.') ?>')" style="margin:0;">
                <?= csrfField() ?>
                <input type="hidden" name="id_transaksi" value="<?= $d['id_transaksi'] ?>">
                <button type="submit" class="btn">Kembalikan</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarTerlambat): ?>
          <tr><td colspan="7" style="text-align:center;">Tidak ada buku yang terlambat.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </main>
</body>
</html>
