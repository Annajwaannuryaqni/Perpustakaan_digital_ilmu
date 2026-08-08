<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

// ---- Statistik ringkas ----
$totalJudul   = $koneksi->query("SELECT COUNT(*) AS total FROM buku")->fetch()['total'];
$totalStok    = $koneksi->query("SELECT COALESCE(SUM(stok),0) AS total FROM buku")->fetch()['total'];
$totalAnggota = $koneksi->query("SELECT COUNT(*) AS total FROM anggota")->fetch()['total'];

// ---- Statistik transaksi (peminjaman aktif, terlambat, total denda terkumpul) ----
$peminjamanAktif = $koneksi->query("SELECT COUNT(*) AS total FROM transaksi WHERE status = 'dipinjam'")->fetch()['total'];
$terlambat       = $koneksi->query("SELECT COUNT(*) AS total FROM transaksi WHERE status = 'dipinjam' AND tanggal_jatuh_tempo < CURDATE()")->fetch()['total'];
$totalDenda      = $koneksi->query("SELECT COALESCE(SUM(denda),0) AS total FROM transaksi")->fetch()['total'];

// ---- Rekap jumlah judul & total stok per genre/kategori ----
$stokPerGenre = $koneksi->query("
    SELECT k.nama_kategori,
           COUNT(b.id_buku) AS jumlah_judul,
           COALESCE(SUM(b.stok), 0) AS total_stok
    FROM kategori k
    LEFT JOIN buku b ON b.id_kategori = k.id_kategori
    GROUP BY k.id_kategori, k.nama_kategori
    ORDER BY k.nama_kategori ASC
")->fetchAll();

// ---- Aktivitas terbaru: transaksi peminjaman terakhir ----
$aktivitasTerbaru = $koneksi->query("
    SELECT t.*, b.judul, a.nama_lengkap
    FROM transaksi t
    JOIN buku b ON b.id_buku = t.id_buku
    JOIN anggota a ON a.id_anggota = t.id_anggota
    ORDER BY t.id_transaksi DESC
    LIMIT 6
")->fetchAll();

// ---- Tren peminjaman per bulan ----
$peminjamanRaw = $koneksi->query("
    SELECT DATE_FORMAT(tanggal_pinjam, '%Y-%m') AS bulan, COUNT(*) AS jumlah
    FROM transaksi
    GROUP BY bulan
    ORDER BY bulan ASC
")->fetchAll();

// ---- Tren kunjungan (baca di tempat) per bulan ----
$kunjunganRaw = $koneksi->query("
    SELECT DATE_FORMAT(waktu_kunjungan, '%Y-%m') AS bulan, COUNT(*) AS jumlah
    FROM kunjungan
    GROUP BY bulan
    ORDER BY bulan ASC
")->fetchAll();

function formatLabelBulan($raw) {
    $labels = [];
    $values = [];
    foreach ($raw as $row) {
        $labels[] = date('M Y', strtotime($row['bulan'] . '-01'));
        $values[] = (int) $row['jumlah'];
    }
    return [$labels, $values];
}
[$labelPeminjaman, $dataPeminjaman] = formatLabelBulan($peminjamanRaw);
[$labelKunjungan, $dataKunjungan]   = formatLabelBulan($kunjunganRaw);

/**
 * Ikon UI ringan (SVG inline, selaras dengan gaya index.php).
 * Tidak memakai emoji maupun library icon eksternal.
 */
function dashIcon($name, $class = 'ic') {
    $paths = [
        'book'      => '<path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/>',
        'stack'     => '<path d="M12 3.5 4 8l8 4.5L20 8Z"/><path d="M4 12l8 4.5L20 12"/><path d="M4 16l8 4.5L20 16"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.3c2.4.3 4 2.2 4 5.2"/>',
        'repeat'    => '<path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/>',
        'clock'     => '<circle cx="12" cy="12" r="8.5"/><polyline points="12 7.5 12 12 15.5 14"/>',
        'coin'      => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v9M9.5 9.7c0-1.3 1.1-2.2 2.5-2.2s2.5.8 2.5 2c0 3-5 1.7-5 4.7 0 1.2 1.1 2 2.5 2s2.5-.9 2.5-2.2"/>',
        'library'   => '<rect x="3.5" y="3.5" width="6.5" height="17" rx="1"/><rect x="14" y="6.2" width="6.5" height="14.3" rx="1"/><line x1="3.5" y1="8.2" x2="10" y2="8.2"/>',
        'trend'     => '<polyline points="4 16 9.5 10 13.5 14 20 6.5"/><polyline points="14.5 6.5 20 6.5 20 12"/>',
        'calendar'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/>',
        'logout'    => '<path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/>',
        'activity'  => '<polyline points="3.5 12 8 12 10 7 14 17 16 12 20.5 12"/>',
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
<title>Dashboard Admin - Perpustakaan Digital</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/css/notification.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
  /* ===== Penyesuaian khusus halaman Dashboard Admin ===== */
  .ic { width: 22px; height: 22px; }
  .stat-icon .ic { width: 24px; height: 24px; }

  .dash-status {
    display: inline-flex; align-items: center; gap: 8px;
    background: #f1f5f9; color: #475569;
    padding: 9px 16px; border-radius: 999px; font-size: .8rem; font-weight: 600;
    border: 1px solid #e2e8f0;
  }
  .dash-status .ic { width: 16px; height: 16px; color: #2563eb; }

  .section-title {
    display: flex; align-items: center; gap: 10px;
    color: #0f172a; font-family: 'Poppins', sans-serif;
    font-weight: 700; font-size: 1.05rem; margin: 0 0 18px;
  }
  .section-title .ic { color: #2563eb; }

  .quick-menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-top: 8px;
  }
  .menu-card-item .ic { width: 26px; height: 26px; color: #2563eb; }
  .menu-card-item.danger .ic { color: #dc2626; }
  .menu-card-item.danger span { color: #dc2626; }

  .status-pill {
    display: inline-flex; align-items: center; padding: 5px 12px;
    border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .02em;
  }
  .status-pill.dipinjam { background: rgba(37,99,235,.10); color: #2563eb; }
  .status-pill.selesai  { background: rgba(52,211,153,.15); color: #16a34a; }
  .status-pill.telat    { background: rgba(248,113,113,.15); color: #dc2626; }

  .empty-row { text-align: center; color: #64748b; padding: 28px !important; font-size: .85rem; }

  @media (max-width: 768px) {
    .quick-menu-grid { grid-template-columns: repeat(2, 1fr); }
  }
</style>
</head>
<body class="admin-page dashboard-body">
  <button class="admin-menu-toggle" type="button" aria-label="Buka menu" onclick="document.body.classList.toggle('admin-menu-open')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="14" y2="17"/></svg></button>
  <div class="admin-sidebar-overlay" onclick="document.body.classList.remove('admin-menu-open')"></div>
  <aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
      <div class="admin-brand-mark">P</div>
      <div><strong>Perpustakaan</strong><small>Panel Admin</small></div>
    </div>
    <nav class="admin-side-nav" aria-label="Navigasi admin">
      <div class="admin-side-label">MENU UTAMA</div>
      <a href="dashboard.php" class="admin-side-link active"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="8" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="14.5" width="7" height="6" rx="1.5"/></svg></span><span>Dashboard</span></a>
      <a href="buku.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/></svg></span><span>Buku</span></a>
      <a href="anggota.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.3c2.4.3 4 2.2 4 5.2"/></svg></span><span>Anggota</span></a>
      <a href="transaksi.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/></svg></span><span>Transaksi</span></a>
      <a href="kunjungan.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/></svg></span><span>Kunjungan</span></a>
    </nav>
    <div class="admin-sidebar-bottom">
      <div class="admin-side-user"><span class="admin-avatar">A</span><span><strong>Admin</strong><small>Pengelola Perpustakaan</small></span></div>
      <a href="logout.php" class="admin-logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/></svg><span>Keluar</span></a>
    </div>
  </aside>
  <main class="admin-main">


  <div class="container">
    <!-- Header Halaman -->
    <div class="page-head glass-header" style="align-items:center;">
      <div>
        <div class="breadcrumb" >Admin Panel &bull; Dashboard</div>
        <h1 style="display:flex; align-items:center; gap:12px;"><?= dashIcon('trend') ?> Dashboard Admin</h1>
        <p >Halo, <b ><?= htmlspecialchars($_SESSION['admin_nama']) ?></b> &mdash; ringkasan aktivitas dan informasi perpustakaan hari ini.</p>
      </div>
      <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <?php require_once '../includes/navbar_notification.php'; ?>
        <div class="dash-status"><?= dashIcon('calendar') ?> <span id="current-date">Hari ini</span></div>
      </div>
    </div>

    <!-- Statistik Utama -->
    <div class="stat-grid">
      <div class="stat-card-modern">
        <div class="stat-icon"><?= dashIcon('book') ?></div>
        <div>
          <div class="stat-value" ><?= $totalJudul ?></div>
          <div class="stat-label" >Total Judul Buku</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon gold"><?= dashIcon('stack') ?></div>
        <div>
          <div class="stat-value" ><?= $totalStok ?></div>
          <div class="stat-label" >Total Stok Buku</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon navy" style="background:rgba(148,163,184,.12); color:#cbd5e1;"><?= dashIcon('users') ?></div>
        <div>
          <div class="stat-value" ><?= $totalAnggota ?></div>
          <div class="stat-label" >Jumlah Anggota</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon" style="background:rgba(37,99,235,.10); color:#60a5fa;"><?= dashIcon('repeat') ?></div>
        <div>
          <div class="stat-value" ><?= $peminjamanAktif ?></div>
          <div class="stat-label" >Peminjaman Aktif</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon" style="background:rgba(248,113,113,.15); color:#dc2626;"><?= dashIcon('clock') ?></div>
        <div>
          <div class="stat-value" ><?= $terlambat ?></div>
          <div class="stat-label" >Terlambat Dikembalikan</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon gold"><?= dashIcon('coin') ?></div>
        <div>
          <div class="stat-value" >Rp<?= number_format($totalDenda, 0, ',', '.') ?></div>
          <div class="stat-label" >Total Denda Tercatat</div>
        </div>
      </div>
    </div>

    <!-- Grafik Statistik -->
    <div class="chart-grid">
      <div class="glass-card" style="padding:24px 28px;">
        <h3 class="section-title"><?= dashIcon('trend') ?> Tren Peminjaman Buku per Bulan</h3>
        <div class="chart-wrap"><canvas id="chartPeminjaman"></canvas></div>
      </div>
      <div class="glass-card" style="padding:24px 28px;">
        <h3 class="section-title"><?= dashIcon('clock') ?> Tren Kunjungan (Baca di Tempat) per Bulan</h3>
        <div class="chart-wrap"><canvas id="chartKunjungan"></canvas></div>
      </div>
    </div>

    <!-- Aktivitas Terbaru -->
    <div class="glass-card" style="padding:24px 28px; margin-bottom:24px;">
      <h3 class="section-title"><?= dashIcon('activity') ?> Aktivitas Peminjaman Terbaru</h3>
      <div class="table-glass-container">
        <table>
          <thead>
            <tr>
              <th>Buku</th>
              <th>Anggota</th>
              <th>Tgl Pinjam</th>
              <th>Jatuh Tempo</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($aktivitasTerbaru as $t):
              $telat = $t['status'] === 'dipinjam' && $t['tanggal_jatuh_tempo'] < date('Y-m-d');
            ?>
            <tr>
              <td><?= htmlspecialchars($t['judul']) ?></td>
              <td><?= htmlspecialchars($t['nama_lengkap']) ?></td>
              <td><?= htmlspecialchars($t['tanggal_pinjam']) ?></td>
              <td><?= htmlspecialchars($t['tanggal_jatuh_tempo']) ?></td>
              <td>
                <?php if ($telat): ?>
                  <span class="status-pill telat">Terlambat</span>
                <?php elseif ($t['status'] === 'dipinjam'): ?>
                  <span class="status-pill dipinjam">Dipinjam</span>
                <?php else: ?>
                  <span class="status-pill selesai"><?= htmlspecialchars(ucfirst($t['status'])) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$aktivitasTerbaru): ?>
            <tr><td colspan="5" class="empty-row">Belum ada aktivitas peminjaman.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Rekap Stok per Genre -->
    <div class="glass-card" style="padding:24px 28px; margin-bottom:24px;">
      <h3 class="section-title"><?= dashIcon('library') ?> Stok Buku per Genre</h3>
      <div class="table-glass-container">
        <table>
          <thead>
            <tr>
              <th>Genre / Kategori</th>
              <th>Jumlah Judul</th>
              <th>Total Stok</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($stokPerGenre as $g): ?>
            <tr>
              <td><b><?= htmlspecialchars($g['nama_kategori']) ?></b></td>
              <td><?= $g['jumlah_judul'] ?></td>
              <td>
                <?php if ($g['total_stok'] > 0): ?>
                  <span class="badge-capsule ok">Tersedia (<?= $g['total_stok'] ?>)</span>
                <?php else: ?>
                  <span class="badge-capsule habis">Habis (0)</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$stokPerGenre): ?>
            <tr><td colspan="3" class="empty-row">Belum ada data genre buku.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Navigasi Menu Akses Cepat -->
    <div class="glass-card" style="padding:24px 28px;">
      <h3 class="section-title">Akses Cepat</h3>
      <div class="quick-menu-grid">
        <a href="buku.php" class="menu-card-item"><?= dashIcon('book') ?><span>Data Buku</span></a>
        <a href="anggota.php" class="menu-card-item"><?= dashIcon('users') ?><span>Data Anggota</span></a>
        <a href="transaksi.php" class="menu-card-item"><?= dashIcon('repeat') ?><span>Transaksi</span></a>
        <a href="kunjungan.php" class="menu-card-item"><?= dashIcon('calendar') ?><span>Kunjungan</span></a>
        <a href="logout.php" class="menu-card-item danger"><?= dashIcon('logout') ?><span>Logout</span></a>
      </div>
    </div>
  </div>

  <script>
    // Tanggal hari ini
    document.getElementById('current-date').innerText = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' });

    // Chart.js: warna disamakan dengan palet royal/navy index.php
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#94a3b8';

    const labelPeminjaman = <?= json_encode($labelPeminjaman) ?>;
    const dataPeminjaman  = <?= json_encode($dataPeminjaman) ?>;
    const labelKunjungan  = <?= json_encode($labelKunjungan) ?>;
    const dataKunjungan   = <?= json_encode($dataKunjungan) ?>;
    const emptyState = { labels: ['Belum ada data'], values: [0] };
    const gridColor = 'rgba(148,163,184,0.18)';

    new Chart(document.getElementById('chartPeminjaman'), {
      type: 'line',
      data: {
        labels: labelPeminjaman.length ? labelPeminjaman : emptyState.labels,
        datasets: [{
          label: 'Jumlah Peminjaman',
          data: dataPeminjaman.length ? dataPeminjaman : emptyState.values,
          borderColor: '#2563eb',
          backgroundColor: 'rgba(37,99,235,0.10)',
          pointBackgroundColor: '#2563eb',
          pointRadius: 4,
          pointHoverRadius: 6,
          tension: 0.35,
          fill: true,
          borderWidth: 3,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: '#0f172a', titleFont: { family: 'Poppins', size: 13 }, bodyFont: { family: 'Inter', size: 13 }, padding: 12, cornerRadius: 8 }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8' }, grid: { color: gridColor } },
          x: { ticks: { color: '#94a3b8' }, grid: { display: false } }
        }
      }
    });

    new Chart(document.getElementById('chartKunjungan'), {
      type: 'bar',
      data: {
        labels: labelKunjungan.length ? labelKunjungan : emptyState.labels,
        datasets: [{
          label: 'Jumlah Kunjungan',
          data: dataKunjungan.length ? dataKunjungan : emptyState.values,
          backgroundColor: '#d9a441',
          borderRadius: 8,
          maxBarThickness: 34,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: '#0f172a', titleFont: { family: 'Poppins', size: 13 }, bodyFont: { family: 'Inter', size: 13 }, padding: 12, cornerRadius: 8 }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8' }, grid: { color: gridColor } },
          x: { ticks: { color: '#94a3b8' }, grid: { display: false } }
        }
      }
    });
  </script>

  <script src="../assets/js/notification.js"></script>

  <?php if (isset($_SESSION['flash_notif'])):
      $flash = $_SESSION['flash_notif'];
      unset($_SESSION['flash_notif']);
  ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.showToast) {
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
  </main>
</body>
</html>
