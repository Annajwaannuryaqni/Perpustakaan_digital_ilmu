<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

const TARIF_DENDA_PER_HARI = 1000; // Rp1.000 / hari keterlambatan (estimasi tampilan, sama seperti kembali.php)

$id_anggota = $_SESSION['anggota_id'];

// Buku yang sedang dipinjam siswa ini (detail, bukan cuma jumlah)
$stmtDipinjam = $koneksi->prepare("
    SELECT t.*, b.judul, b.pengarang, b.cover
    FROM transaksi t
    JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.id_anggota = ? AND t.status = 'dipinjam'
    ORDER BY t.tanggal_jatuh_tempo ASC
");
$stmtDipinjam->execute([$id_anggota]);
$bukuDipinjam = $stmtDipinjam->fetchAll();

$totalDipinjam = count($bukuDipinjam);
$totalTerlambat = 0;
$estimasiDenda = 0;
$hariIni = date('Y-m-d');
foreach ($bukuDipinjam as $p) {
    if ($p['tanggal_jatuh_tempo'] < $hariIni) {
        $totalTerlambat++;
        $hariTerlambat = floor((strtotime($hariIni) - strtotime($p['tanggal_jatuh_tempo'])) / 86400);
        $estimasiDenda += $hariTerlambat * TARIF_DENDA_PER_HARI;
    }
}

// Total denda yang sudah tercatat dari riwayat pengembalian
$stmtDendaTercatat = $koneksi->prepare("SELECT COALESCE(SUM(denda),0) AS total FROM transaksi WHERE id_anggota = ?");
$stmtDendaTercatat->execute([$id_anggota]);
$dendaTercatat = $stmtDendaTercatat->fetch()['total'];

// Jumlah buku yang sudah pernah dikembalikan
$stmtDikembalikan = $koneksi->prepare("SELECT COUNT(*) AS total FROM transaksi WHERE id_anggota = ? AND status <> 'dipinjam'");
$stmtDikembalikan->execute([$id_anggota]);
$totalDikembalikan = $stmtDikembalikan->fetch()['total'];

// Riwayat peminjaman siswa ini (terbaru dulu)
$riwayat = $koneksi->prepare("
    SELECT t.*, b.judul
    FROM transaksi t
    JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.id_anggota = ?
    ORDER BY t.id_transaksi DESC
    LIMIT 6
");
$riwayat->execute([$id_anggota]);
$riwayat = $riwayat->fetchAll();

// Rekomendasi: beberapa buku yang tersedia (stok > 0), untuk dipinjam
$rekomendasi = $koneksi->query("
    SELECT b.*, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
    WHERE b.stok > 0
    ORDER BY b.id_buku DESC
    LIMIT 4
")->fetchAll();

/**
 * Ikon UI ringan (SVG inline), selaras gaya index.php — tanpa emoji.
 */
function siswaIcon($name, $class = 'ic') {
    $paths = [
        'book'     => '<path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/>',
        'repeat'   => '<path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/>',
        'clock'    => '<circle cx="12" cy="12" r="8.5"/><polyline points="12 7.5 12 12 15.5 14"/>',
        'coin'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v9M9.5 9.7c0-1.3 1.1-2.2 2.5-2.2s2.5.8 2.5 2c0 3-5 1.7-5 4.7 0 1.2 1.1 2 2.5 2s2.5-.9 2.5-2.2"/>',
        'check'    => '<circle cx="12" cy="12" r="8.5"/><polyline points="8 12.5 11 15.5 16 9"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/>',
        'presensi' => '<path d="M9 11.5 11.5 14 16 8.5"/><circle cx="12" cy="12" r="9"/>',
        'logout'   => '<path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/>',
        'sparkle'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
    ];
    $d = $paths[$name] ?? '';
    return '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'.$d.'</svg>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Siswa - Perpustakaan Digital</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/css/notification.css">
<style>
  .ic { width: 22px; height: 22px; }
  .stat-icon .ic { width: 24px; height: 24px; }

  .section-title {
    display: flex; align-items: center; gap: 10px;
    font-family: 'Poppins', sans-serif; font-weight: 700;
    font-size: 1.05rem; color: var(--navy); margin: 0 0 18px;
  }
  .section-title .ic { color: var(--royal-600); }

  .loan-list { display: flex; flex-direction: column; gap: 12px; }
  .loan-item {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px; border: 1px solid var(--border);
    border-radius: var(--radius-btn); background: var(--slate-50);
  }
  .loan-item.telat { border-color: rgba(220,38,38,.3); background: rgba(220,38,38,.04); }
  .loan-thumb {
    width: 42px; height: 56px; border-radius: 6px; object-fit: cover;
    box-shadow: var(--shadow-card); flex-shrink: 0; background: var(--slate-200);
  }
  .loan-info { flex: 1; min-width: 0; }
  .loan-title { font-weight: 700; color: var(--navy); font-size: .92rem; }
  .loan-meta { font-size: .78rem; color: var(--muted); margin-top: 2px; }

  .empty-state { text-align: center; padding: 32px 16px; color: var(--muted); font-size: .88rem; }

  .reco-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 16px;
  }
  .reco-card {
    border: 1px solid var(--border); border-radius: var(--radius-card);
    overflow: hidden; background: var(--surface); box-shadow: var(--shadow-card);
    transition: var(--transition-smooth);
  }
  .reco-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }
  .reco-cover { width: 100%; aspect-ratio: 3/4; object-fit: cover; background: var(--slate-100); }
  .reco-body { padding: 10px 12px; }
  .reco-title { font-size: .82rem; font-weight: 700; color: var(--navy); line-height: 1.3; }
  .reco-genre { font-size: .72rem; color: var(--muted); margin-top: 2px; }

  .quick-actions { display: flex; gap: 12px; flex-wrap: wrap; }
  .quick-actions .btn .ic { width: 18px; height: 18px; }

  @media (max-width: 640px) {
    .quick-actions .btn { flex: 1 1 auto; justify-content: center; }
  }
</style>
</head>
<body>

  <div class="container">
    <div class="page-head">
      <div>
        <h1><?= siswaIcon('book') ?> Dashboard Siswa</h1>
        <p>Halo, <b><?= htmlspecialchars($_SESSION['anggota_nama']) ?></b> &mdash; berikut ringkasan aktivitas peminjamanmu.</p>
      </div>
      <?php require_once '../includes/navbar_notification.php'; ?>
    </div>

    <!-- Statistik Siswa -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon"><?= siswaIcon('repeat') ?></div>
        <div>
          <div class="stat-value"><?= $totalDipinjam ?></div>
          <div class="stat-label">Buku Sedang Dipinjam</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:<?= $totalTerlambat ? 'rgba(220,38,38,.1)' : 'rgba(22,163,74,.1)' ?>; color:<?= $totalTerlambat ? 'var(--danger)' : 'var(--success)' ?>;"><?= siswaIcon('clock') ?></div>
        <div>
          <div class="stat-value"><?= $totalTerlambat ?></div>
          <div class="stat-label">Buku Terlambat</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon navy"><?= siswaIcon('check') ?></div>
        <div>
          <div class="stat-value"><?= $totalDikembalikan ?></div>
          <div class="stat-label">Total Sudah Dikembalikan</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon gold"><?= siswaIcon('coin') ?></div>
        <div>
          <div class="stat-value">Rp<?= number_format($dendaTercatat + $estimasiDenda, 0, ',', '.') ?></div>
          <div class="stat-label">Total Denda<?= $estimasiDenda ? ' (termasuk estimasi berjalan)' : '' ?></div>
        </div>
      </div>
    </div>

    <!-- Buku Sedang Dipinjam & Jatuh Tempo -->
    <div class="card">
      <h3 class="section-title"><?= siswaIcon('calendar') ?> Buku Sedang Dipinjam &amp; Jatuh Tempo</h3>
      <?php if ($bukuDipinjam): ?>
      <div class="loan-list">
        <?php foreach ($bukuDipinjam as $p):
          $telat = $p['tanggal_jatuh_tempo'] < $hariIni;
        ?>
        <div class="loan-item <?= $telat ? 'telat' : '' ?>">
          <?php if ($p['cover']): ?>
            <img src="../uploads/<?= htmlspecialchars($p['cover']) ?>" class="loan-thumb" alt="">
          <?php else: ?>
            <div class="loan-thumb"></div>
          <?php endif; ?>
          <div class="loan-info">
            <div class="loan-title"><?= htmlspecialchars($p['judul']) ?></div>
            <div class="loan-meta">Pinjam: <?= htmlspecialchars($p['tanggal_pinjam']) ?> &middot; Jatuh tempo: <?= htmlspecialchars($p['tanggal_jatuh_tempo']) ?></div>
          </div>
          <?php if ($telat): ?>
            <span class="badge badge-habis">Terlambat</span>
          <?php else: ?>
            <span class="badge badge-ok">Dipinjam</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="empty-state">Kamu tidak sedang meminjam buku apa pun.</div>
      <?php endif; ?>
    </div>

    <!-- Riwayat Peminjaman -->
    <div class="card">
      <h3 class="section-title"><?= siswaIcon('repeat') ?> Riwayat Peminjaman Terakhir</h3>
      <div style="overflow-x:auto;">
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
              <td data-label="Tgl Pinjam"><?= htmlspecialchars($r['tanggal_pinjam']) ?></td>
              <td data-label="Jatuh Tempo"><?= htmlspecialchars($r['tanggal_jatuh_tempo']) ?></td>
              <td data-label="Status">
                <?php if ($r['status'] === 'dipinjam'): ?>
                  <span class="badge badge-habis"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                <?php else: ?>
                  <span class="badge badge-ok"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$riwayat): ?>
            <tr><td colspan="4" style="text-align:center; color:var(--muted);">Belum ada riwayat peminjaman.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Rekomendasi Buku Tersedia -->
    <?php if ($rekomendasi): ?>
    <div class="card">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h3 class="section-title" style="margin:0;"><?= siswaIcon('sparkle') ?> Buku Tersedia untuk Dipinjam</h3>
        <a href="pinjam.php" class="btn-link">Lihat Katalog Lengkap &rarr;</a>
      </div>
      <div class="reco-grid">
        <?php foreach ($rekomendasi as $b): ?>
        <a href="pinjam_konfirmasi.php?id=<?= $b['id_buku'] ?>" class="reco-card" style="text-decoration:none; display:block;">
          <?php if ($b['cover']): ?>
            <img src="../uploads/<?= htmlspecialchars($b['cover']) ?>" class="reco-cover" alt="">
          <?php else: ?>
            <div class="reco-cover"></div>
          <?php endif; ?>
          <div class="reco-body">
            <div class="reco-title"><?= htmlspecialchars($b['judul']) ?></div>
            <div class="reco-genre"><?= htmlspecialchars($b['nama_kategori'] ?? 'Lainnya') ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Aksi Cepat -->
    <div class="card quick-actions">
      <a href="pinjam.php" class="btn"><?= siswaIcon('book') ?> Peminjaman Buku</a>
      <a href="kembali.php" class="btn btn-outline"><?= siswaIcon('repeat') ?> Pengembalian Buku</a>
      <a href="presensi.php" class="btn btn-outline"><?= siswaIcon('presensi') ?> Presensi Kunjungan</a>
      <a href="logout.php" class="btn btn-danger"><?= siswaIcon('logout') ?> Logout</a>
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
