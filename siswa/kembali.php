<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

const TARIF_DENDA_PER_HARI = 1000; // Rp1.000 / hari keterlambatan

$id_anggota = $_SESSION['anggota_id'];
$pesan = $_GET['pesan'] ?? '';

$stmt = $koneksi->prepare("
    SELECT t.*, b.judul, b.pengarang
    FROM transaksi t
    JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.id_anggota = ? AND t.status = 'dipinjam'
    ORDER BY t.tanggal_jatuh_tempo ASC
");
$stmt->execute([$id_anggota]);
$daftarPinjaman = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pengembalian Buku</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div class="topbar">
    <h1> Pengembalian Buku</h1>
  </div>

  <div class="container">
    <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>

    <?php if ($pesan === 'sukses'): ?>
      <p class="alert alert-sukses">Buku berhasil dikembalikan. Terima kasih!</p>
    <?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Judul</th>
            <th>Tgl Pinjam</th>
            <th>Jatuh Tempo</th>
            <th>Status</th>
            <th>Denda</th>
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
            <td data-label="Judul"><?= htmlspecialchars($p['judul']) ?></td>
            <td data-label="Tgl Pinjam"><?= $p['tanggal_pinjam'] ?></td>
            <td data-label="Jatuh Tempo"><?= $p['tanggal_jatuh_tempo'] ?></td>
            <td data-label="Status">
              <span class="badge <?= $telat ? 'badge-habis' : 'badge-ok' ?>">
                <?= $telat ? "Terlambat $hariTerlambat hari" : 'Masih dalam batas waktu' ?>
              </span>
            </td>
            <td data-label="Denda">
              <?php if ($telat): ?>
                <span class="badge badge-habis">Rp<?= number_format($denda, 0, ',', '.') ?></span>
              <?php else: ?>
                <span style="color:#94a3b8;">-</span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <form method="POST" action="proses_kembali.php" onsubmit="return confirm('Kembalikan buku ini?<?= $telat ? " Denda: Rp" . number_format($denda, 0, ',', '.') : '' ?>')" style="margin:0;">
                <input type="hidden" name="id_transaksi" value="<?= $p['id_transaksi'] ?>">
                <button type="submit" class="btn">Kembalikan</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarPinjaman): ?>
          <tr><td colspan="6" style="text-align:center;">Kamu tidak sedang meminjam buku apapun</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>