<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

$pesan = $_GET['pesan'] ?? '';

$daftarDenda = $koneksi->query("
    SELECT t.*, a.nama_lengkap AS nama_anggota, a.nis, a.kelas, b.judul, b.deleted_at
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    LEFT JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.denda > 0
    ORDER BY t.status_denda ASC, t.tanggal_kembali DESC
")->fetchAll();

$totalBelumLunas = 0;
$totalTerkumpul = 0;
foreach ($daftarDenda as $d) {
    if ($d['status_denda'] === 'Belum Lunas') {
        $totalBelumLunas += (float)$d['denda'];
    } elseif ($d['status_denda'] === 'Lunas') {
        $totalTerkumpul += (float)$d['denda'];
    }
}

$activeMenu = 'denda';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Denda - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Denda Keterlambatan</h1>
        <p>Denda belum lunas: <strong>Rp<?= number_format($totalBelumLunas, 0, ',', '.') ?></strong> &nbsp; | &nbsp; <strong>Sudah terkumpul: Rp<?= number_format($totalTerkumpul, 0, ',', '.') ?></strong></p>
      </div>
    </div>

    <?php if ($pesan === 'kembali_denda'): ?>
      <p class="alert alert-sukses">Buku berhasil dikembalikan, tetapi ada denda keterlambatan. Silakan proses pembayaran denda di bawah ini.</p>
    <?php endif; ?>

    <?php if ($pesan === 'denda_lunas'): ?>
      <p class="alert alert-sukses">Denda berhasil ditandai lunas.</p>
    <?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Anggota</th>
            <th>Kelas</th>
            <th>Judul Buku</th>
            <th>Tgl Kembali</th>
            <th>Denda</th>
            <th>Status Bayar</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarDenda as $d): ?>
          <tr>
            <td data-label="Anggota" style="font-weight:600;"><?= htmlspecialchars($d['nama_anggota']) ?> <br><small style="color:var(--muted); font-weight:400;">NIS <?= htmlspecialchars($d['nis']) ?></small></td>
            <td data-label="Kelas"><?= htmlspecialchars($d['kelas']) ?></td>
            <td data-label="Judul Buku"><?= htmlspecialchars($d['judul'] ?? 'Buku tidak ditemukan') ?><?php if (!empty($d['deleted_at'])): ?> <span class="badge badge-habis">Diarsipkan</span><?php endif; ?></td>
            <td data-label="Tgl Kembali"><?= htmlspecialchars($d['tanggal_kembali'] ?? '-') ?></td>
            <td data-label="Denda">Rp<?= number_format((float)$d['denda'], 0, ',', '.') ?></td>
            <td data-label="Status Bayar">
              <?php if ($d['status_denda'] === 'Lunas'): ?>
                <span class="badge badge-ok">Lunas<?= $d['tanggal_bayar_denda'] ? ' (' . htmlspecialchars($d['tanggal_bayar_denda']) . ')' : '' ?></span>
              <?php else: ?>
                <span class="badge badge-habis">Belum Lunas</span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <?php if ($d['status_denda'] === 'Belum Lunas'): ?>
              <form method="POST" action="bayar_denda.php" onsubmit="return confirm('Tandai denda transaksi ini sudah lunas dibayar?')" style="margin:0;">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $d['id_transaksi'] ?>">
                <button type="submit" class="btn">Tandai Lunas</button>
              </form>
              <?php else: ?>
                <span style="color:var(--muted);">-</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarDenda): ?>
          <tr><td colspan="7" style="text-align:center;">Belum ada transaksi yang terkena denda.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </main>
</body>
</html>