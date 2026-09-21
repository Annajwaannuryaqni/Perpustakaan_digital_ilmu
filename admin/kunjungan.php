<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

date_default_timezone_set('Asia/Jakarta');

$daftarKunjungan = $koneksi->query("
    SELECT k.*, a.nama_lengkap, a.kelas
    FROM kunjungan k
    JOIN anggota a ON a.id_anggota = k.id_anggota
    ORDER BY k.waktu_kunjungan DESC
")->fetchAll();

$totalKunjungan = count($daftarKunjungan);
$kunjunganHariIni = 0;
foreach ($daftarKunjungan as $k) {
    if (date('Y-m-d', strtotime($k['waktu_kunjungan'])) === date('Y-m-d')) {
        $kunjunganHariIni++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Kunjungan | Perpustakaan Digital Ilmu</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.kunjungan-topbar{min-height:86px!important;padding:0 38px 0 210px!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:20px}
.topbar-info{display:flex;flex-direction:column;gap:3px}
.topbar-brand{font-size:17px;font-weight:800;color:#fff;letter-spacing:-.2px}
.topbar-page{font-size:13px;color:rgba(255,255,255,.72)}
.kunjungan-wrapper{max-width:1200px;margin:26px auto 50px}
.kunjungan-hero{padding:26px 28px;border-radius:18px;background:linear-gradient(135deg,#fff,#f8fbff);border:1px solid #e2e8f0;box-shadow:0 8px 25px rgba(15,23,42,.05)}
.kunjungan-hero h1{margin:0 0 7px;font-size:25px;color:#0f172a;letter-spacing:-.5px}
.kunjungan-hero p{margin:0;color:#64748b;font-size:14px;line-height:1.6}
.summary-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:13px;margin-top:22px}
.summary-card{min-width:0;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;display:flex;align-items:center;gap:12px;box-shadow:0 4px 14px rgba(15,23,42,.035)}
.summary-icon{width:42px;height:42px;flex:0 0 42px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:#eff6ff;color:#0284c7}
.summary-icon svg{width:21px;height:21px}
.summary-card.accent .summary-icon{background:#ecfdf5;color:#059669}
.summary-content{min-width:0}
.summary-content strong{display:block;color:#0f172a;font-size:19px;line-height:1.2}
.summary-content span{display:block;margin-top:3px;color:#94a3b8;font-size:11px}
.report-section{margin-top:25px}
.section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:13px}
.section-heading h2{margin:0;font-size:17px;color:#0f172a}
.section-heading span{font-size:11px;color:#94a3b8}
.report-table{overflow:hidden;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 6px 20px rgba(15,23,42,.045)}
.report-table table{width:100%;border-collapse:collapse}
.report-table th{padding:13px 14px;background:#f8fafc;color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.3px;text-align:left;border-bottom:1px solid #e2e8f0}
.report-table td{padding:14px;color:#475569;font-size:12px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.report-table tr:last-child td{border-bottom:0}
.empty-report{padding:55px 20px;text-align:center}
.empty-icon-custom{width:58px;height:58px;margin:0 auto 13px;display:flex;align-items:center;justify-content:center;border-radius:16px;background:#f1f5f9;color:#94a3b8}
.empty-icon-custom svg{width:29px;height:29px}
.empty-report strong{display:block;color:#334155;font-size:14px}
.empty-report span{display:block;margin-top:5px;color:#94a3b8;font-size:11px}
@media(max-width:700px){
.kunjungan-topbar{min-height:76px!important;padding:0 16px 0 88px!important}
.topbar-brand{font-size:14px}
.topbar-page{font-size:11px}
.kunjungan-wrapper{margin:18px auto 35px;padding-left:14px;padding-right:14px}
.kunjungan-hero{padding:21px 18px;border-radius:16px}
.kunjungan-hero h1{font-size:22px}
.kunjungan-hero p{font-size:12px}
.summary-grid{grid-template-columns:1fr;gap:9px;margin-top:17px}
.summary-card{padding:12px}
.summary-icon{width:38px;height:38px;flex-basis:38px}
.summary-content strong{font-size:17px}
.summary-content span{font-size:10px}
.report-section{margin-top:21px}
.section-heading h2{font-size:15px}
.report-table{border-radius:14px}
.report-table table,.report-table thead,.report-table tbody,.report-table tr,.report-table th,.report-table td{display:block}
.report-table thead{display:none}
.report-table tr{padding:15px;border-bottom:1px solid #e2e8f0}
.report-table tr:last-child{border-bottom:0}
.report-table td{position:relative;display:flex;align-items:center;justify-content:space-between;gap:15px;padding:7px 0;border:0;text-align:right}
.report-table td:before{content:attr(data-label);font-size:10px;font-weight:700;color:#94a3b8;text-align:left;flex:0 0 90px}
.report-table td[data-label="Nama Siswa"]{display:block;padding-bottom:11px;text-align:left;font-size:13px}
.report-table td[data-label="Nama Siswa"]:before{display:none}
}
</style>
</head>
<body class="admin-page">
  <button class="admin-menu-toggle" type="button" aria-label="Buka menu" onclick="document.body.classList.toggle('admin-menu-open')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="14" y2="17"/></svg></button>
  <div class="admin-sidebar-overlay" onclick="document.body.classList.remove('admin-menu-open')"></div>
  <aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
      <div class="admin-brand-mark">P</div>
      <div><strong>Perpustakaan</strong><small>Panel Admin</small></div>
    </div>
    <nav class="admin-side-nav" aria-label="Navigasi admin">
      <div class="admin-side-label">MENU UTAMA</div>
      <a href="dashboard.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="8" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="14.5" width="7" height="6" rx="1.5"/></svg></span><span>Dashboard</span></a>
      <a href="transaksi.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/></svg></span><span>Transaksi</span></a>
      <a href="kunjungan.php" class="admin-side-link active"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/></svg></span><span>Kunjungan</span></a>
      <a href="petugas.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="3"/><path d="M4 19.5c0-3 2.2-5 5-5s5 2 5 5"/><path d="M14.5 9.2h5M17 6.7v5"/></svg></span><span>Petugas</span></a>
    </nav>
    <div class="admin-sidebar-bottom">
      <div class="admin-side-user"><span class="admin-avatar">A</span><span><strong>Admin</strong><small>Pengelola Perpustakaan</small></span></div>
      <a href="logout.php" class="admin-logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/></svg><span>Keluar</span></a>
    </div>
  </aside>

  <main class="admin-main">

  <div class="topbar kunjungan-topbar">
    <div class="topbar-info">
      <span class="topbar-brand">Perpustakaan Digital Ilmu</span>
      <span class="topbar-page">Daftar Kunjungan</span>
    </div>
  </div>

  <div class="container">
  <div class="kunjungan-wrapper">

  <div class="kunjungan-hero">
    <h1>Daftar Kunjungan (Baca di Tempat)</h1>
    <p>Rekap siswa yang berkunjung dan membaca di perpustakaan tanpa meminjam buku.</p>

    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3.5" y="5" width="17" height="15.5" rx="2"/>
            <line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/>
            <line x1="8" y1="3" x2="8" y2="6.5"/>
            <line x1="16" y1="3" x2="16" y2="6.5"/>
          </svg>
        </div>
        <div class="summary-content">
          <strong><?= $totalKunjungan ?></strong>
          <span>Total Kunjungan Tercatat</span>
        </div>
      </div>

      <div class="summary-card accent">
        <div class="summary-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9.5"/>
            <polyline points="12 7 12 12 15.5 14"/>
          </svg>
        </div>
        <div class="summary-content">
          <strong><?= $kunjunganHariIni ?></strong>
          <span>Kunjungan Hari Ini</span>
        </div>
      </div>
    </div>
  </div>

  <div class="report-section">
    <div class="section-heading">
      <h2>Riwayat Kunjungan</h2>
      <span><?= $totalKunjungan ?> data</span>
    </div>

    <div class="report-table">
      <?php if ($daftarKunjungan): ?>
      <table>
        <thead>
          <tr>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <th>Tanggal</th>
            <th>Jam</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarKunjungan as $k): ?>
          <tr>
            <td data-label="Nama Siswa" style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($k['nama_lengkap']) ?></td>
            <td data-label="Kelas"><?= htmlspecialchars($k['kelas']) ?></td>
            <td data-label="Tanggal"><?= date('d-m-Y', strtotime($k['waktu_kunjungan'])) ?></td>
            <td data-label="Jam"><?= date('H:i', strtotime($k['waktu_kunjungan'])) ?> WIB</td>
            <td data-label="Keterangan"><?= htmlspecialchars($k['keterangan']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-report">
        <div class="empty-icon-custom">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
        </div>
        <strong>Belum ada data kunjungan</strong>
        <span>Riwayat kunjungan siswa akan muncul di sini.</span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  </div>
  </div>
  </main>
</body>
</html>