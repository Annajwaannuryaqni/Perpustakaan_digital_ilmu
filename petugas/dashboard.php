<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

// ---- Statistik operasional hari ini (semua dari database, tidak ada dummy) ----
$stmtPinjamHariIni = $koneksi->prepare("SELECT COUNT(*) AS total FROM transaksi WHERE tanggal_pinjam = CURDATE()");
$stmtPinjamHariIni->execute();
$peminjamanHariIni = $stmtPinjamHariIni->fetch()['total'];

$stmtKembaliHariIni = $koneksi->prepare("SELECT COUNT(*) AS total FROM transaksi WHERE tanggal_kembali = CURDATE()");
$stmtKembaliHariIni->execute();
$pengembalianHariIni = $stmtKembaliHariIni->fetch()['total'];

$bukuDipinjam = $koneksi->query("SELECT COUNT(*) AS total FROM transaksi WHERE status = 'dipinjam'")->fetch()['total'];

$bukuTerlambat = $koneksi->query("
    SELECT COUNT(*) AS total FROM transaksi
    WHERE status = 'dipinjam' AND tanggal_jatuh_tempo < CURDATE()
")->fetch()['total'];

// ---- Buku terlambat (ringkas, 5 teratas) ----
$daftarTerlambat = $koneksi->query("
    SELECT t.*, a.nama_lengkap AS nama_anggota, b.judul,
           DATEDIFF(CURDATE(), t.tanggal_jatuh_tempo) AS hari_terlambat
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.status = 'dipinjam' AND t.tanggal_jatuh_tempo < CURDATE()
    ORDER BY t.tanggal_jatuh_tempo ASC
    LIMIT 5
")->fetchAll();

$activeMenu = 'dashboard';

function petugasIcon($name) {
    $paths = [
        'repeat'   => '<path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/>',
        'undo'     => '<path d="M20 7.5H6.5L9 4.5"/><path d="M4 16.5h13.5L15 19.5"/>',
        'stack'    => '<path d="M12 3.5 4 8l8 4.5L20 8Z"/><path d="M4 12l8 4.5L20 12"/><path d="M4 16l8 4.5L20 16"/>',
        'clock'    => '<circle cx="12" cy="12" r="8.5"/><polyline points="12 7.5 12 12 15.5 14"/>',
        'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.3c2.4.3 4 2.2 4 5.2"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/>',
        'activity' => '<polyline points="3.5 12 8 12 10 7 14 17 16 12 20.5 12"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'book'     => '<path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/>',
    ];
    $d = $paths[$name] ?? '';
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'.$d.'</svg>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Petugas - Perpustakaan Digital</title>
<script>
  // Cegah browser memulihkan posisi scroll (vertikal/horizontal) halaman
  // maupun sidebar dari kunjungan sebelumnya, supaya dashboard selalu
  // dimulai dari atas saat dimuat/di-refresh.
  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }
  window.scrollTo(0, 0);
</script>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/css/notification.css">
<style>
  .ic { width: 22px; height: 22px; }
  .stat-icon .ic { width: 24px; height: 24px; }
  .dash-status {
    display: inline-flex; align-items: center; gap: 8px;
    background: #f1f5f9; color: #475569;
    padding: 9px 16px; border-radius: 999px; font-size: .8rem; font-weight: 600;
    border: 1px solid #e2e8f0;
  }
  .dash-status .ic { width: 16px; height: 16px; color: #2563eb; }
  .status-pill {
    display: inline-flex; align-items: center; padding: 5px 12px;
    border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .02em;
  }
  .status-pill.dipinjam { background: rgba(37,99,235,.10); color: #2563eb; }
  .status-pill.selesai  { background: rgba(52,211,153,.15); color: #16a34a; }
  .status-pill.telat    { background: rgba(248,113,113,.15); color: #dc2626; }
  .empty-row { text-align: center; color: #64748b; padding: 28px !important; font-size: .85rem; }
</style>
</head>
<body class="admin-page dashboard-body">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head glass-header" style="align-items:center;">
      <div>
        <div class="breadcrumb">Panel Petugas &bull; Dashboard</div>
        <h1 style="display:flex; align-items:center; gap:12px;"><?= petugasIcon('activity') ?> Selamat datang, <?= htmlspecialchars($_SESSION['petugas_nama']) ?></h1>
        <p>Kelola aktivitas perpustakaan hari ini dengan mudah.</p>
      </div>
      <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <?php require_once '../includes/navbar_notification.php'; ?>
        <div class="dash-status"><?= petugasIcon('calendar') ?> <span id="current-date">Hari ini</span></div>
      </div>
    </div>

    <!-- Statistik -->
    <div class="stat-grid">
      <div class="stat-card-modern">
        <div class="stat-icon" style="background:rgba(37,99,235,.10); color:#60a5fa;"><?= petugasIcon('repeat') ?></div>
        <div>
          <div class="stat-value"><?= $peminjamanHariIni ?></div>
          <div class="stat-label">Peminjaman Hari Ini</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon gold"><?= petugasIcon('undo') ?></div>
        <div>
          <div class="stat-value"><?= $pengembalianHariIni ?></div>
          <div class="stat-label">Pengembalian Hari Ini</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon navy" style="background:rgba(148,163,184,.12); color:#cbd5e1;"><?= petugasIcon('stack') ?></div>
        <div>
          <div class="stat-value"><?= $bukuDipinjam ?></div>
          <div class="stat-label">Buku Sedang Dipinjam</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon" style="background:rgba(248,113,113,.15); color:#dc2626;"><?= petugasIcon('clock') ?></div>
        <div>
          <div class="stat-value"><?= $bukuTerlambat ?></div>
          <div class="stat-label">Buku Terlambat</div>
        </div>
      </div>
    </div>

    <!-- Aksi Cepat -->
    <div class="glass-card quick-actions-card">
      <div class="quick-actions-head">
        <h3 class="section-title"><?= petugasIcon('stack') ?> <span>Aksi Cepat</span></h3>
      </div>
      <div class="quick-menu-grid">
        <a href="peminjaman.php" class="menu-card-item">
          <span class="quick-action-icon"><?= petugasIcon('repeat') ?></span>
          <span class="quick-action-content">
            <strong>Peminjaman Baru</strong>
            <small>Proses peminjaman untuk anggota</small>
          </span>
          <span class="quick-action-arrow" aria-hidden="true">&rarr;</span>
        </a>
        <a href="pengembalian.php" class="menu-card-item">
          <span class="quick-action-icon"><?= petugasIcon('undo') ?></span>
          <span class="quick-action-content">
            <strong>Proses Pengembalian</strong>
            <small>Cari transaksi dan proses kembali</small>
          </span>
          <span class="quick-action-arrow" aria-hidden="true">&rarr;</span>
        </a>
        <a href="buku_terlambat.php" class="menu-card-item">
          <span class="quick-action-icon"><?= petugasIcon('clock') ?></span>
          <span class="quick-action-content">
            <strong>Lihat Buku Terlambat</strong>
            <small>Pantau keterlambatan pengembalian</small>
          </span>
          <span class="quick-action-arrow" aria-hidden="true">&rarr;</span>
        </a>
        <a href="data_anggota.php" class="menu-card-item">
          <span class="quick-action-icon"><?= petugasIcon('search') ?></span>
          <span class="quick-action-content">
            <strong>Cari Anggota</strong>
            <small>Lihat data anggota perpustakaan</small>
          </span>
          <span class="quick-action-arrow" aria-hidden="true">&rarr;</span>
        </a>
      </div>
    </div>

    <!-- Buku Terlambat -->
    <div class="glass-card" style="padding:24px 28px; margin-bottom:24px;">
      <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:18px;">
        <h3 class="section-title" style="margin-bottom:0;"><?= petugasIcon('clock') ?> Buku Terlambat</h3>
        <a href="buku_terlambat.php" class="btn-link">Lihat Semua &rarr;</a>
      </div>
      <div class="table-glass-container">
        <table>
          <thead>
            <tr>
              <th>Anggota</th>
              <th>Judul Buku</th>
              <th>Jatuh Tempo</th>
              <th>Terlambat</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarTerlambat as $d): ?>
            <tr>
              <td><?= htmlspecialchars($d['nama_anggota']) ?></td>
              <td><?= htmlspecialchars($d['judul']) ?></td>
              <td><?= htmlspecialchars($d['tanggal_jatuh_tempo']) ?></td>
              <td><?= (int)$d['hari_terlambat'] ?> hari</td>
              <td><span class="status-pill telat">Terlambat</span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$daftarTerlambat): ?>
            <tr><td colspan="5" class="empty-row">Tidak ada buku yang terlambat.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('current-date').innerText = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' });

    // Pastikan area menu sidebar juga mulai dari paling atas (menu Dashboard),
    // bukan posisi scroll internal yang tersisa dari kunjungan sebelumnya.
    var sideNav = document.querySelector('.admin-side-nav');
    if (sideNav) { sideNav.scrollTop = 0; }
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