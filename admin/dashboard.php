<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

// ---- Statistik ringkas (sama seperti sebelumnya) ----
$totalJudul   = $koneksi->query("SELECT COUNT(*) AS total FROM buku")->fetch()['total'];
$totalStok    = $koneksi->query("SELECT COALESCE(SUM(stok),0) AS total FROM buku")->fetch()['total'];
$totalAnggota = $koneksi->query("SELECT COUNT(*) AS total FROM anggota")->fetch()['total'];

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - Perpustakaan Digital</title>
<!-- Google Fonts: Poppins & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>
<!-- Chart.js (dipakai untuk grafik tren peminjaman & kunjungan) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/css/notification.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root {
    --navy: #1e293b;
    --royal-blue: #2851d4;
    --royal-blue-hover: #1d3fc4;
    --bg-main: #f8fafc;
    --card-bg: #ffffff;
    --text-main: #0f172a;
    --text-muted: #6b7280;
    --border-color: #e2e8f0;
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
    --radius-lg: 16px;
    --radius-md: 12px;
    --radius-sm: 8px;
  }

  body {
    font-family: 'Inter', sans-serif;
    background-color: var(--bg-main);
    color: var(--text-main);
    margin: 0;
    padding: 0;
    -webkit-font-smoothing: antialiased;
  }

  h1, h2, h3, h4, h5, h6 {
    font-family: 'Poppins', sans-serif;
  }

  .container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 24px;
  }

  /* Page Header */
  .page-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 32px;
    background: var(--card-bg);
    padding: 28px 32px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
  }

  .breadcrumb {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 8px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .page-head h1 {
    font-size: 26px;
    font-weight: 700;
    color: var(--navy);
    margin: 0 0 6px 0;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .page-head p {
    color: var(--text-muted);
    font-size: 15px;
    margin: 0;
  }

  .header-meta {
    text-align: right;
    font-size: 14px;
    color: var(--text-muted);
    background: var(--bg-main);
    padding: 10px 16px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-color);
  }

  /* Statistics Grid */
  .stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
  }

  .stat-card {
    background: var(--card-bg);
    padding: 24px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 20px;
  }

  .stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--royal-blue);
    transition: height 0.2s ease;
  }

  .stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: #cbd5e1;
  }

  .stat-icon {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-md);
    background: rgba(40, 81, 212, 0.1);
    color: var(--royal-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
  }

  .stat-icon.gold {
    background: rgba(217, 164, 65, 0.1);
    color: #d9a441;
  }

  .stat-icon.navy {
    background: rgba(30, 41, 59, 0.1);
    color: var(--navy);
  }

  .stat-value {
    font-family: 'Poppins', sans-serif;
    font-size: 32px;
    font-weight: 700;
    color: var(--navy);
    line-height: 1.2;
    margin-bottom: 4px;
  }

  .stat-label {
    font-size: 14px;
    color: var(--text-muted);
    font-weight: 500;
  }

  /* Charts Grid */
  .chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
  }

  .chart-card, .card {
    background: var(--card-bg);
    padding: 28px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    margin-bottom: 32px;
    transition: box-shadow 0.3s ease;
  }

  .chart-card:hover, .card:hover {
    box-shadow: var(--shadow-md);
  }

  .chart-card h3, .card h3 {
    font-size: 18px;
    font-weight: 600;
    color: var(--navy);
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .chart-wrap {
    position: relative;
    height: 320px;
    width: 100%;
  }

  /* Modern Tables */
  table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
    margin-top: 8px;
  }

  th {
    background: #f1f5f9;
    color: var(--navy);
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 18px;
    position: sticky;
    top: 0;
    z-index: 10;
    border-bottom: 2px solid var(--border-color);
  }

  th:first-child { border-top-left-radius: var(--radius-sm); border-bottom-left-radius: var(--radius-sm); }
  th:last-child { border-top-right-radius: var(--radius-sm); border-bottom-right-radius: var(--radius-sm); }

  td {
    padding: 16px 18px;
    font-size: 14px;
    color: var(--text-main);
    border-bottom: 1px solid var(--border-color);
  }

  tbody tr {
    transition: background-color 0.2s ease;
  }

  tbody tr:hover {
    background-color: #f8fafc;
  }

  tbody tr:last-child td {
    border-bottom: none;
  }

  /* Badges */
  .badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
  }

  .badge-ok {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.2);
  }

  .badge-habis {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.2);
  }

  /* Modern Action Menu Cards */
  .menu-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    background: transparent;
    border: none;
    box-shadow: none;
    padding: 0;
  }

  .card-btn {
    background: var(--card-bg);
    padding: 24px 20px;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
  }

  .card-btn:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--royal-blue);
    background: #ffffff;
  }

  .card-btn .btn-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    background: rgba(40, 81, 212, 0.1);
    color: var(--royal-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    transition: transform 0.3s ease;
  }

  .card-btn:hover .btn-icon-wrap {
    transform: scale(1.1);
  }

  .card-btn.btn-danger-card .btn-icon-wrap {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
  }

  .card-btn.btn-danger-card:hover {
    border-color: #dc2626;
  }

  .card-btn span {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 14px;
    color: var(--navy);
  }

  /* Responsive Adjustments */
  @media (max-width: 768px) {
    .container { padding: 16px; }
    .page-head { flex-direction: column; gap: 16px; align-items: stretch; }
    .header-meta { text-align: left; }
    .chart-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

  <div class="container">
    <!-- Header Halaman -->
    <div class="page-head">
      <div>
        <div class="breadcrumb">Admin Panel &bull; Dashboard</div>
        <h1>📊 Dashboard Admin</h1>
        <p>Halo, <b><?= htmlspecialchars($_SESSION['admin_nama']) ?></b> &mdash; berikut ringkasan sistem perpustakaan hari ini.</p>
      </div>
      <div style="display:flex; align-items:center; gap:16px;">
        <?php require_once '../includes/navbar_notification.php'; ?>
        <div class="header-meta">
          📅 <span id="current-date">Senin, 27 Jul 2026</span>
        </div>
      </div>
    </div>

    <!-- Statistik Utama -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i data-lucide="book-open"></i>
        </div>
        <div>
          <div class="stat-value"><?= $totalJudul ?></div>
          <div class="stat-label">Total Judul Buku</div>
        </div>
      </div>
      <div class="stat-card" style="--royal-blue: #d9a441;">
        <div class="stat-icon gold">
          <i data-lucide="package"></i>
        </div>
        <div>
          <div class="stat-value"><?= $totalStok ?></div>
          <div class="stat-label">Total Stok Buku</div>
        </div>
      </div>
      <div class="stat-card" style="--royal-blue: #1e293b;">
        <div class="stat-icon navy">
          <i data-lucide="graduation-cap"></i>
        </div>
        <div>
          <div class="stat-value"><?= $totalAnggota ?></div>
          <div class="stat-label">Jumlah Anggota</div>
        </div>
      </div>
    </div>

    <!-- Grafik Statistik -->
    <div class="chart-grid">
      <div class="chart-card">
        <h3><i data-lucide="trending-up" style="color: var(--royal-blue);"></i> Tren Peminjaman Buku per Bulan</h3>
        <div class="chart-wrap"><canvas id="chartPeminjaman"></canvas></div>
      </div>
      <div class="chart-card">
        <h3><i data-lucide="clock" style="color: #d9a441;"></i> Tren Kunjungan (Baca di Tempat) per Bulan</h3>
        <div class="chart-wrap"><canvas id="chartKunjungan"></canvas></div>
      </div>
    </div>

    <!-- Rekap Stok per Genre -->
    <div class="card">
      <h3><i data-lucide="library" style="color: var(--navy);"></i> Stok Buku per Genre</h3>
      <div style="overflow-x: auto;">
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
              <td data-label="Genre"><b><?= htmlspecialchars($g['nama_kategori']) ?></b></td>
              <td data-label="Jumlah Judul"><?= $g['jumlah_judul'] ?></td>
              <td data-label="Total Stok">
                <?php if ($g['total_stok'] > 0): ?>
                  <span class="badge badge-ok">Tersedia (<?= $g['total_stok'] ?>)</span>
                <?php else: ?>
                  <span class="badge badge-habis">Habis (0)</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$stokPerGenre): ?>
            <tr><td colspan="3" style="text-align:center; color: var(--text-muted); padding: 32px;">Belum ada data genre buku.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Navigasi Menu Kartu Modern -->
    <div class="menu-cards-grid">
      <a href="buku.php" class="card-btn">
        <div class="btn-icon-wrap"><i data-lucide="book-marked"></i></div>
        <span>Kelola Data Buku</span>
      </a>
      <a href="anggota.php" class="card-btn" style="--royal-blue: #1e293b;">
        <div class="btn-icon-wrap navy"><i data-lucide="users"></i></div>
        <span>Kelola Anggota</span>
      </a>
      <a href="transaksi.php" class="card-btn" style="--royal-blue: #059669;">
        <div class="btn-icon-wrap" style="background: rgba(5, 150, 105, 0.1); color: #059669;"><i data-lucide="repeat"></i></div>
        <span>Transaksi Peminjaman</span>
      </a>
      <a href="kunjungan.php" class="card-btn" style="--royal-blue: #d9a441;">
        <div class="btn-icon-wrap gold"><i data-lucide="calendar-days"></i></div>
        <span>Daftar Kunjungan</span>
      </a>
      <a href="logout.php" class="card-btn btn-danger-card">
        <div class="btn-icon-wrap"><i data-lucide="log-out"></i></div>
        <span style="color: #dc2626;">Logout Sistem</span>
      </a>
    </div>
  </div>

  <!-- Inisialisasi Lucide Icons & Chart.js -->
  <script>
    // Load Lucide Icons
    lucide.createIcons();

    // Set dynamic date
    const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
    document.getElementById('current-date').innerText = new Date().toLocaleDateString('id-ID', options);

    // Chart.js Configuration
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6b7280';

    const labelPeminjaman = <?= json_encode($labelPeminjaman) ?>;
    const dataPeminjaman  = <?= json_encode($dataPeminjaman) ?>;
    const labelKunjungan  = <?= json_encode($labelKunjungan) ?>;
    const dataKunjungan   = <?= json_encode($dataKunjungan) ?>;

    const emptyState = { labels: ['Belum ada data'], values: [0] };

    new Chart(document.getElementById('chartPeminjaman'), {
      type: 'line',
      data: {
        labels: labelPeminjaman.length ? labelPeminjaman : emptyState.labels,
        datasets: [{
          label: 'Jumlah Peminjaman',
          data: dataPeminjaman.length ? dataPeminjaman : emptyState.values,
          borderColor: '#2851d4',
          backgroundColor: 'rgba(40,81,212,0.08)',
          pointBackgroundColor: '#2851d4',
          pointRadius: 5,
          pointHoverRadius: 7,
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
          tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { family: 'Poppins', size: 13 },
            bodyFont: { family: 'Inter', size: 13 },
            padding: 12,
            cornerRadius: 8
          }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
          x: { grid: { display: false } }
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
          maxBarThickness: 36,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { family: 'Poppins', size: 13 },
            bodyFont: { family: 'Inter', size: 13 },
            padding: 12,
            cornerRadius: 8
          }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
          x: { grid: { display: false } }
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
</body>
</html>