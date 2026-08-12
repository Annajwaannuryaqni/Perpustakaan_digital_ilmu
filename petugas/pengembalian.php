<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

const TARIF_DENDA_PER_HARI = 1000; // Rp1.000 / hari keterlambatan, sama dengan siswa/proses_kembali.php

$pesan = $_GET['pesan'] ?? '';
$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $stmt = $koneksi->prepare("
        SELECT t.*, a.nama_lengkap AS nama_anggota, a.nis, a.kelas, b.judul
        FROM transaksi t
        JOIN anggota a ON a.id_anggota = t.id_anggota
        JOIN buku b ON b.id_buku = t.id_buku
        WHERE t.status = 'dipinjam'
          AND (a.nama_lengkap LIKE :kw OR a.nis LIKE :kw OR b.judul LIKE :kw)
        ORDER BY t.tanggal_jatuh_tempo ASC
        LIMIT 40
    ");
    $stmt->execute(['kw' => '%' . $q . '%']);
    $daftarPinjaman = $stmt->fetchAll();
} else {
    $daftarPinjaman = $koneksi->query("
        SELECT t.*, a.nama_lengkap AS nama_anggota, a.nis, a.kelas, b.judul
        FROM transaksi t
        JOIN anggota a ON a.id_anggota = t.id_anggota
        JOIN buku b ON b.id_buku = t.id_buku
        WHERE t.status = 'dipinjam'
        ORDER BY t.tanggal_jatuh_tempo ASC
        LIMIT 40
    ")->fetchAll();
}

$activeMenu = 'pengembalian';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengembalian - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Proses Pengembalian</h1>
        <p>Cari transaksi peminjaman yang sedang aktif untuk diproses pengembaliannya.</p>
      </div>
    </div>

    <?php if ($pesan === 'sukses'): ?>
      <p class="alert alert-sukses">Buku berhasil dikembalikan.</p>
    <?php endif; ?>

    <div class="card">
      <form method="GET" action="pengembalian.php" class="search-form">
        <input type="text" name="q" placeholder="Cari nama anggota, NIS, atau judul buku..." value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn btn-outline">Cari</button>
        <?php if ($q !== ''): ?><a href="pengembalian.php" class="btn-link">Reset</a><?php endif; ?>
      </form>

      <table>
        <thead>
          <tr>
            <th>Anggota</th>
            <th>Kelas</th>
            <th>Judul Buku</th>
            <th>Jatuh Tempo</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarPinjaman as $p):
              $hariIni = strtotime(date('Y-m-d'));
              $jatuhTempo = strtotime($p['tanggal_jatuh_tempo']);
              $telat = $hariIni > $jatuhTempo;
              $hariTerlambat = $telat ? floor(($hariIni - $jatuhTempo) / 86400) : 0;
              $denda = $hariTerlambat * TARIF_DENDA_PER_HARI;
          ?>
          <tr>
            <td data-label="Anggota" style="font-weight:600;"><?= htmlspecialchars($p['nama_anggota']) ?> <br><small style="color:var(--muted); font-weight:400;">NIS <?= htmlspecialchars($p['nis']) ?></small></td>
            <td data-label="Kelas"><?= htmlspecialchars($p['kelas']) ?></td>
            <td data-label="Judul Buku"><?= htmlspecialchars($p['judul']) ?></td>
            <td data-label="Jatuh Tempo"><?= $p['tanggal_jatuh_tempo'] ?></td>
            <td data-label="Status">
              <?php if ($telat): ?>
                <span class="badge badge-habis">Terlambat <?= $hariTerlambat ?> hari</span>
              <?php else: ?>
                <span class="badge badge-ok">Masih dalam batas waktu</span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <form method="POST" action="proses_kembali.php" onsubmit="return confirm('Proses pengembalian buku ini?<?= $telat ? ' Denda: Rp' . number_format($denda, 0, ',', '.') : '' ?>')" style="margin:0;">
                <?= csrfField() ?>
                <input type="hidden" name="id_transaksi" value="<?= $p['id_transaksi'] ?>">
                <button type="submit" class="btn">Kembalikan</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarPinjaman): ?>
          <tr><td colspan="6" style="text-align:center;">
            <?= $q !== '' ? 'Tidak ada peminjaman aktif yang cocok dengan pencarian.' : 'Tidak ada peminjaman yang sedang aktif.' ?>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </main>
</body>
</html>
