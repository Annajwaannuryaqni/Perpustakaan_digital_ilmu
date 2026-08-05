<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$id_anggota = $_SESSION['anggota_id'];

// Hitung buku yang sedang dipinjam siswa ini
$totalDipinjam = $koneksi->prepare("SELECT COUNT(*) AS total FROM transaksi WHERE id_anggota = ? AND status = 'dipinjam'");
$totalDipinjam->execute([$id_anggota]);
$totalDipinjam = $totalDipinjam->fetch()['total'];

// Ambil riwayat peminjaman siswa ini (terbaru dulu)
$riwayat = $koneksi->prepare("
    SELECT t.*, b.judul
    FROM transaksi t
    JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.id_anggota = ?
    ORDER BY t.id_transaksi DESC
    LIMIT 5
");
$riwayat->execute([$id_anggota]);
$riwayat = $riwayat->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Siswa</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/css/notification.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="topbar" style="display:flex; align-items:center; justify-content:space-between;">
    <h1> Dashboard Siswa</h1>
    <?php require_once '../includes/navbar_notification.php'; ?>
  </div>

  <div class="container">
    <p>Halo, <b><?= htmlspecialchars($_SESSION['anggota_nama']) ?></b></p>

    <div class="card">
      <div style="text-align:center; padding:16px; background:var(--foam); border-radius:var(--radius); max-width:260px;">
        <div style="font-size:0.85rem; color:var(--text-muted);">Buku yang sedang kamu pinjam</div>
        <div style="font-size:2rem; font-weight:700; color:var(--navy-deep);"><?= $totalDipinjam ?></div>
      </div>
    </div>

    <div class="card">
      <h3>Riwayat Peminjaman Terakhir</h3>
      <table>
        <thead>
          <tr>
            <th>Judul Buku</th>
            <th>Tgl Pinjam</th>
            <th>Jatuh Tempo</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($riwayat as $r): ?>
          <tr>
            <td data-label="Judul Buku"><?= htmlspecialchars($r['judul']) ?></td>
            <td data-label="Tgl Pinjam"><?= $r['tanggal_pinjam'] ?></td>
            <td data-label="Jatuh Tempo"><?= $r['tanggal_jatuh_tempo'] ?></td>
            <td data-label="Status">
              <?php if ($r['status'] === 'dipinjam'): ?>
                <span class="badge badge-habis"><?= htmlspecialchars($r['status']) ?></span>
              <?php else: ?>
                <span class="badge badge-ok"><?= htmlspecialchars($r['status']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$riwayat): ?>
          <tr><td colspan="4" style="text-align:center;">Belum ada riwayat peminjaman</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="display:flex; gap:12px; flex-wrap:wrap;">
      <a href="pinjam.php" class="btn" style="text-decoration:none;"> Peminjaman Buku</a>
      <a href="kembali.php" class="btn" style="text-decoration:none;"> Pengembalian Buku</a>
       <a href="presensi.php" class="btn" style="text-decoration:none;"> Presensi Kunjungan</a>
      <a href="logout.php" class="btn btn-danger" style="text-decoration:none;"> Logout</a>
    </div>
  </div>

  <script src="../assets/js/notification.js"></script>
  <?php
if (isset($_SESSION['flash_notif'])):
    $flash = $_SESSION['flash_notif'];
    unset($_SESSION['flash_notif']);
?>
<script>
window.addEventListener("load", function () {
    if (typeof showToast === "function") {
        showToast(
            <?= json_encode($flash['title']) ?>,
            <?= json_encode($flash['message']) ?>,
            <?= json_encode($flash['type']) ?>,
            <?= json_encode($flash['icon']) ?>,
            <?= json_encode($flash['color']) ?>
        );
    }
});
</script>
<?php endif; ?>
</body>
</html>