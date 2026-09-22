<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d');

// ---- Statistik ringkas ----
$totalJudul   = $koneksi->query("SELECT COUNT(*) AS total FROM buku WHERE deleted_at IS NULL")->fetch()['total'];
$totalStok    = $koneksi->query("SELECT COALESCE(SUM(stok),0) AS total FROM buku WHERE deleted_at IS NULL")->fetch()['total'];
$totalAnggota = $koneksi->query("SELECT COUNT(*) AS total FROM anggota")->fetch()['total'];

// ---- Statistik transaksi ----
// Peminjaman Aktif hanya berarti buku masih dipinjam oleh siswa.
$peminjamanAktif = $koneksi->query("
    SELECT COUNT(*) AS total
    FROM transaksi
    WHERE status = 'dipinjam'
")->fetch()['total'];

// Buku Sedang Terlambat dihitung dari data transaksi aktif, lalu dibandingkan
// di PHP menggunakan tanggal hari ini yang sama dengan tampilan dashboard.
// Cara ini menghindari perbedaan tanggal/jam antara PHP dan MySQL.
$transaksiAktif = $koneksi->query("
    SELECT t.id_transaksi, t.status, t.tanggal_jatuh_tempo,
           t.tanggal_pengajuan_kembali, a.nama_lengkap, b.judul
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    LEFT JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.status IN ('dipinjam', 'menunggu_konfirmasi')
      AND t.tanggal_jatuh_tempo IS NOT NULL
")->fetchAll();

$bukuSedangTerlambatDetail = [];
foreach ($transaksiAktif as $row) {
    // Saat masih dipinjam, acuannya hari ini.
    // Saat menunggu konfirmasi, acuannya tanggal siswa mengajukan pengembalian.
    $tanggalAcuan = ($row['status'] === 'menunggu_konfirmasi' && !empty($row['tanggal_pengajuan_kembali']))
        ? $row['tanggal_pengajuan_kembali']
        : $today;

    if ($tanggalAcuan > $row['tanggal_jatuh_tempo']) {
        $row['hari_terlambat'] = (int) floor((strtotime($tanggalAcuan) - strtotime($row['tanggal_jatuh_tempo'])) / 86400);
        $bukuSedangTerlambatDetail[] = $row;
    }
}

usort($bukuSedangTerlambatDetail, function ($a, $b) {
    if ($a['hari_terlambat'] === $b['hari_terlambat']) {
        return strcmp($a['tanggal_jatuh_tempo'], $b['tanggal_jatuh_tempo']);
    }
    return $b['hari_terlambat'] <=> $a['hari_terlambat'];
});

$terlambat = count($bukuSedangTerlambatDetail);
// Sebelumnya menjumlahkan SEMUA denda (termasuk yang sudah lunas dibayar),
// sehingga angka ini tidak pernah cocok dengan kondisi nyata. Sekarang
// hanya menjumlahkan denda yang BELUM lunas — konsisten dengan kartu
// "Denda Belum Lunas" di halaman Transaksi.
$totalDendaTercatat = $koneksi->query("SELECT COALESCE(SUM(denda),0) AS total FROM transaksi")->fetch()['total'];
$totalDendaBelumLunas = $koneksi->query("SELECT COALESCE(SUM(denda),0) AS total FROM transaksi WHERE status_denda = 'Belum Lunas'")->fetch()['total'];
$totalDenda = $totalDendaBelumLunas;

// ---- Informasi penting yang perlu diperhatikan admin hari ini ----
$kunjunganHariIni = $koneksi->query("
    SELECT COUNT(*) AS total
    FROM kunjungan
    WHERE DATE(waktu_kunjungan) = CURDATE()
")->fetch()['total'];

$menungguKonfirmasi = $koneksi->query("
    SELECT COUNT(*) AS total
    FROM transaksi
    WHERE status = 'menunggu_konfirmasi'
")->fetch()['total'];

$bukuStokHabis = $koneksi->query("
    SELECT COUNT(*) AS total
    FROM buku
    WHERE COALESCE(stok, 0) <= 0
      AND deleted_at IS NULL
")->fetch()['total'];

$bukuSedangTerlambatDetail = array_slice($bukuSedangTerlambatDetail, 0, 5);

$bukuTerlaris = $koneksi->query("
    SELECT b.judul, COUNT(t.id_transaksi) AS jumlah_dipinjam
    FROM transaksi t
    JOIN buku b ON b.id_buku = t.id_buku
    WHERE b.deleted_at IS NULL
    GROUP BY b.id_buku, b.judul
    ORDER BY jumlah_dipinjam DESC, b.judul ASC
    LIMIT 5
")->fetchAll();

// ---- Rekap jumlah judul & total stok per genre/kategori ----
$stokPerGenre = $koneksi->query("
    SELECT k.nama_kategori,
           COUNT(b.id_buku) AS jumlah_judul,
           COALESCE(SUM(b.stok), 0) AS total_stok
    FROM kategori k
    LEFT JOIN buku b ON b.id_kategori = k.id_kategori AND b.deleted_at IS NULL
    GROUP BY k.id_kategori, k.nama_kategori
    ORDER BY k.nama_kategori ASC
")->fetchAll();

// ---- Aktivitas terbaru: transaksi peminjaman terakhir ----
$aktivitasTerbaru = $koneksi->query("
    SELECT t.*, b.judul, b.deleted_at, a.nama_lengkap
    FROM transaksi t
    LEFT JOIN buku b ON b.id_buku = t.id_buku
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
        'alert'     => '<path d="M12 3.5 21 20H3Z"/><line x1="12" y1="8.5" x2="12" y2="13"/><circle cx="12" cy="16.5" r=".7"/>',
        'check'     => '<path d="m5 12.5 4 4L19.5 6"/>',
        'calendar2' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/><line x1="8" y1="13" x2="10.5" y2="13"/><line x1="13.5" y1="13" x2="16" y2="13"/><line x1="8" y1="16" x2="10.5" y2="16"/><line x1="13.5" y1="16" x2="16" y2="16"/>',
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
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    margin-top: 8px;
  }

  .status-pill {
    display: inline-flex; align-items: center; padding: 5px 12px;
    border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .02em;
  }
  .status-pill.dipinjam { background: rgba(37,99,235,.10); color: #2563eb; }
  .status-pill.menunggu { background: rgba(217,119,6,.12); color: #b45309; }
  .status-pill.selesai  { background: rgba(52,211,153,.15); color: #16a34a; }
  .status-pill.telat    { background: rgba(248,113,113,.15); color: #dc2626; }
  .status-pill.telat-kembali { background: rgba(248,113,113,.12); color: #dc2626; }

  .empty-row { text-align: center; color: #64748b; padding: 28px !important; font-size: .85rem; }

  .admin-alert-card {
    padding: 24px 28px;
    margin-bottom: 24px;
  }
  .admin-alert-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
  }
  .admin-alert-item {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 15px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    text-decoration: none;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  }
  .admin-alert-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15,23,42,.06);
    border-color: #cbd5e1;
  }
  .admin-alert-icon {
    width: 40px; height: 40px; flex: 0 0 40px;
    display: grid; place-items: center; border-radius: 12px;
    background: #eff6ff; color: #2563eb;
  }
  .admin-alert-icon svg { width: 20px; height: 20px; }
  .admin-alert-item.warning .admin-alert-icon { background: #fffbeb; color: #d97706; }
  .admin-alert-item.danger .admin-alert-icon { background: #fef2f2; color: #dc2626; }
  .admin-alert-item.success .admin-alert-icon { background: #ecfdf5; color: #059669; }
  .admin-alert-content { min-width: 0; }
  .admin-alert-content strong { display: block; color: #0f172a; font-size: 16px; line-height: 1.2; }
  .admin-alert-content span { display: block; margin-top: 4px; color: #64748b; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .admin-insight-grid {
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 24px;
    margin-bottom: 24px;
  }
  .admin-mini-table {
    width: 100%;
    border-collapse: collapse;
  }
  .admin-mini-table th {
    padding: 10px 0; text-align: left; color: #94a3b8;
    font-size: 10px; text-transform: uppercase; letter-spacing: .04em;
    border-bottom: 1px solid #e2e8f0;
  }
  .admin-mini-table td {
    padding: 12px 0; color: #475569; font-size: 12px;
    border-bottom: 1px solid #f1f5f9; vertical-align: middle;
  }
  .admin-mini-table tr:last-child td { border-bottom: 0; }
  .admin-mini-title { color: #0f172a; font-weight: 700; }
  .admin-mini-sub { color: #94a3b8; font-size: 10px; margin-top: 3px; }
  .admin-number-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; padding: 6px 9px; border-radius: 999px;
    background: #eff6ff; color: #2563eb; font-weight: 800; font-size: 11px;
  }
  .admin-days-badge {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 6px 9px; border-radius: 999px;
    background: #fef2f2; color: #dc2626; font-weight: 800; font-size: 11px;
  }
  .admin-link-inline { color: #2563eb; text-decoration: none; font-size: 11px; font-weight: 700; }
  .admin-link-inline:hover { text-decoration: underline; }
  .admin-no-data { text-align: center; padding: 28px 8px; color: #94a3b8; font-size: 12px; }

  /* ===== Grafik: tinggi tetap terkontrol di semua ukuran layar ===== */
  .chart-wrap { position: relative; height: 300px; width: 100%; }

  /* ===== Tabel: bisa discroll horizontal tanpa merusak layout halaman ===== */
  .table-glass-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .table-glass-container table { min-width: 560px; }

  /* =======================================================
     RESPONSIVE — Tablet & layar sedang (<= 1100px)
     ======================================================= */
  @media (max-width: 1100px) {
    .quick-menu-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
    .chart-grid { grid-template-columns: 1fr !important; }
    .admin-alert-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .admin-insight-grid { grid-template-columns: 1fr; }
  }

  /* =======================================================
     RESPONSIVE — Tablet kecil / HP landscape (<= 780px)
     ======================================================= */
  @media (max-width: 780px) {
    .page-head.glass-header {
      flex-direction: column;
      align-items: flex-start !important;
      gap: 14px;
    }
    .page-head.glass-header > div:last-child {
      width: 100%;
      justify-content: space-between;
    }
    .page-head h1 { font-size: 1.35rem; }
    .glass-card { padding: 18px 16px !important; }
    .section-title { font-size: .95rem; margin-bottom: 14px; }
  }

  /* =======================================================
     RESPONSIVE — HP (<= 600px)
     ======================================================= */
  @media (max-width: 600px) {
    .quick-menu-grid { grid-template-columns: 1fr; }
    .stat-grid { grid-template-columns: 1fr !important; gap: 12px; }
    .admin-alert-grid { grid-template-columns: 1fr; }
    .admin-alert-card { padding: 18px 16px; }
    .admin-insight-grid { grid-template-columns: 1fr; }
    .chart-wrap { height: 240px; }

    .page-head h1 { font-size: 1.15rem; gap: 8px; }
    .page-head h1 .ic { width: 18px; height: 18px; }
    .dash-status { font-size: .72rem; padding: 7px 12px; }

    .stat-card-modern { padding: 16px !important; }
    .stat-value { font-size: 1.25rem !important; }
    .stat-label { font-size: .72rem !important; }

    .table-glass-container table { min-width: 480px; font-size: .82rem; }
    .status-pill { font-size: .68rem; padding: 4px 10px; }

    .quick-actions-head { margin-bottom: 4px; }
  }

  /* =======================================================
     RESPONSIVE — HP kecil (<= 380px)
     ======================================================= */
  @media (max-width: 380px) {
    .container { padding-left: 12px !important; padding-right: 12px !important; }
    .page-head h1 { font-size: 1.05rem; }
    .dash-status { font-size: .68rem; padding: 6px 10px; }
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
      <a href="transaksi.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/></svg></span><span>Transaksi</span></a>
      <a href="kunjungan.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/></svg></span><span>Kunjungan</span></a>
      <a href="petugas.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="3"/><path d="M4 19.5c0-3 2.2-5 5-5s5 2 5 5"/><path d="M14.5 9.2h5M17 6.7v5"/></svg></span><span>Petugas</span></a>
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
          <div class="stat-label" >Buku Sedang Terlambat</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon gold"><?= dashIcon('coin') ?></div>
        <div>
          <div class="stat-value" >Rp<?= number_format($totalDendaTercatat, 0, ',', '.') ?></div>
          <div class="stat-label" >Total Denda Tercatat</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon" style="background:rgba(248,113,113,.15); color:#dc2626;"><?= dashIcon('coin') ?></div>
        <div>
          <div class="stat-value" >Rp<?= number_format($totalDendaBelumLunas, 0, ',', '.') ?></div>
          <div class="stat-label" >Denda Belum Lunas</div>
        </div>
      </div>
      <div class="stat-card-modern">
        <div class="stat-icon" style="background:rgba(16,185,129,.10); color:#059669;"><?= dashIcon('calendar2') ?></div>
        <div>
          <div class="stat-value" ><?= $kunjunganHariIni ?></div>
          <div class="stat-label" >Kunjungan Hari Ini</div>
        </div>
      </div>
    </div>

    <!-- Informasi yang Perlu Ditangani -->
    <div class="glass-card admin-alert-card">
      <h3 class="section-title"><?= dashIcon('alert') ?> Informasi Penting Hari Ini</h3>
      <div class="admin-alert-grid">
        <a href="transaksi.php" class="admin-alert-item danger">
          <span class="admin-alert-icon"><?= dashIcon('clock') ?></span>
          <span class="admin-alert-content">
            <strong><?= (int)$terlambat ?></strong>
            <span>Buku sedang terlambat</span>
          </span>
        </a>
        <a href="transaksi.php" class="admin-alert-item warning">
          <span class="admin-alert-icon"><?= dashIcon('repeat') ?></span>
          <span class="admin-alert-content">
            <strong><?= (int)$menungguKonfirmasi ?></strong>
            <span>Pengembalian menunggu konfirmasi</span>
          </span>
        </a>
        <a href="transaksi.php" class="admin-alert-item warning">
          <span class="admin-alert-icon"><?= dashIcon('coin') ?></span>
          <span class="admin-alert-content">
            <strong>Rp<?= number_format($totalDendaBelumLunas, 0, ',', '.') ?></strong>
            <span>Denda belum lunas</span>
          </span>
        </a>
        <a href="transaksi.php" class="admin-alert-item danger">
          <span class="admin-alert-icon"><?= dashIcon('book') ?></span>
          <span class="admin-alert-content">
            <strong><?= (int)$bukuStokHabis ?></strong>
            <span>Buku stok habis</span>
          </span>
        </a>
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
              $tanggalAcuanAktivitas = ($t['status'] === 'menunggu_konfirmasi' && !empty($t['tanggal_pengajuan_kembali']))
                  ? $t['tanggal_pengajuan_kembali']
                  : $today;
              $telat = in_array($t['status'], ['dipinjam', 'menunggu_konfirmasi'], true)
                    && !empty($t['tanggal_jatuh_tempo'])
                    && $tanggalAcuanAktivitas > $t['tanggal_jatuh_tempo'];
            ?>
            <tr>
              <td><?= htmlspecialchars($t['judul'] ?? 'Buku tidak ditemukan') ?><?php if (!empty($t['deleted_at'])): ?> <span class="badge badge-habis">Diarsipkan</span><?php endif; ?></td>
              <td><?= htmlspecialchars($t['nama_lengkap']) ?></td>
              <td><?= htmlspecialchars($t['tanggal_pinjam']) ?></td>
              <td><?= htmlspecialchars($t['tanggal_jatuh_tempo']) ?></td>
              <td>
                <?php if ($t['status'] === 'terlambat' && !empty($t['tanggal_kembali'])): ?>
                  <span class="status-pill telat-kembali">Dikembalikan — Terlambat</span>
                <?php elseif ($telat): ?>
                  <span class="status-pill telat">Terlambat</span>
                <?php elseif ($t['status'] === 'dipinjam'): ?>
                  <span class="status-pill dipinjam">Dipinjam</span>
                <?php elseif ($t['status'] === 'menunggu_konfirmasi'): ?>
                  <span class="status-pill menunggu">Menunggu Konfirmasi</span>
                <?php elseif ($t['status'] === 'terlambat'): ?>
                  <span class="status-pill telat">Terlambat</span>
                <?php else: ?>
                  <span class="status-pill selesai">Dikembalikan</span>
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

    <!-- Insight yang membantu admin mengambil tindakan -->
    <div class="admin-insight-grid">
      <div class="glass-card" style="padding:24px 28px; margin-bottom:0;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:4px;">
          <h3 class="section-title" style="margin-bottom:0;"><?= dashIcon('library') ?> Buku Paling Sering Dipinjam</h3>
          <a href="transaksi.php" class="admin-link-inline">Lihat transaksi</a>
        </div>
        <?php if ($bukuTerlaris): ?>
        <div class="table-glass-container" style="border:0;">
          <table class="admin-mini-table">
            <thead>
              <tr><th>Buku</th><th style="text-align:right;">Jumlah</th></tr>
            </thead>
            <tbody>
            <?php foreach ($bukuTerlaris as $b): ?>
              <tr>
                <td><div class="admin-mini-title"><?= htmlspecialchars($b['judul']) ?></div></td>
                <td style="text-align:right;"><span class="admin-number-badge"><?= (int)$b['jumlah_dipinjam'] ?>x</span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="admin-no-data">Belum ada data peminjaman buku.</div>
        <?php endif; ?>
      </div>

      <div class="glass-card" style="padding:24px 28px; margin-bottom:0;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:4px;">
          <h3 class="section-title" style="margin-bottom:0;"><?= dashIcon('clock') ?> Anggota dengan Buku Terlambat</h3>
          <a href="transaksi.php" class="admin-link-inline">Lihat</a>
        </div>
        <?php if ($bukuSedangTerlambatDetail): ?>
        <div class="table-glass-container" style="border:0;">
          <table class="admin-mini-table">
            <thead>
              <tr><th>Anggota / Buku</th><th style="text-align:right;">Terlambat</th></tr>
            </thead>
            <tbody>
            <?php foreach ($bukuSedangTerlambatDetail as $d): ?>
              <tr>
                <td>
                  <div class="admin-mini-title"><?= htmlspecialchars($d['nama_lengkap']) ?></div>
                  <div class="admin-mini-sub"><?= htmlspecialchars($d['judul'] ?? 'Buku tidak ditemukan') ?></div>
                </td>
                <td style="text-align:right;"><span class="admin-days-badge"><?= (int)$d['hari_terlambat'] ?> hari</span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="admin-no-data">Tidak ada buku yang sedang terlambat.</div>
        <?php endif; ?>
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

    <!-- Tindakan Cepat -->
    <div class="glass-card quick-actions-card">
      <div class="quick-actions-head">
        <h3 class="section-title"><?= dashIcon('stack') ?> <span>Tindakan Cepat</span></h3>
      </div>

      <div class="quick-menu-grid">
        <a href="transaksi.php" class="menu-card-item">
          <span class="quick-action-icon"><?= dashIcon('repeat') ?></span>
          <span class="quick-action-content">
            <strong>Data Transaksi Peminjaman</strong>
            <small>Kelola peminjaman dan pengembalian</small>
          </span>
          <span class="quick-action-arrow" aria-hidden="true">&rarr;</span>
        </a>

        <a href="kunjungan.php" class="menu-card-item">
          <span class="quick-action-icon"><?= dashIcon('calendar') ?></span>
          <span class="quick-action-content">
            <strong>Daftar Kunjungan</strong>
            <small>Kelola data kunjungan perpustakaan</small>
          </span>
          <span class="quick-action-arrow" aria-hidden="true">&rarr;</span>
        </a>
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