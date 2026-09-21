<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

date_default_timezone_set('Asia/Jakarta');

$daftarTransaksi = $koneksi->query("
    SELECT t.*, a.nama_lengkap, a.kelas, b.judul, b.deleted_at
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    LEFT JOIN buku b ON b.id_buku = t.id_buku
    ORDER BY t.id_transaksi DESC
")->fetchAll();

$totalTransaksi = count($daftarTransaksi);
$totalDipinjam = 0;
$totalDikembalikan = 0;
$totalDendaBelumLunas = 0;
$totalDendaTerkumpul = 0;
foreach ($daftarTransaksi as $t) {
    if ($t['status'] === 'dipinjam' || $t['status'] === 'menunggu_konfirmasi') {
        $totalDipinjam++;
    } else {
        $totalDikembalikan++;
    }
    if ((float)$t['denda'] > 0 && $t['status_denda'] === 'Belum Lunas') {
        $totalDendaBelumLunas += (float)$t['denda'];
    } elseif ((float)$t['denda'] > 0 && $t['status_denda'] === 'Lunas') {
        $totalDendaTerkumpul += (float)$t['denda'];
    }
}

$pesanGagalHapus = '';
if (($_GET['pesan'] ?? '') === 'gagal_hapus') {
    $pesanGagalHapus = 'Terjadi kesalahan saat menghapus transaksi. Silakan coba lagi.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Transaksi | Perpustakaan Digital Ilmu</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.transaksi-topbar{min-height:86px!important;padding:0 38px 0 210px!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:20px}
.topbar-info{display:flex;flex-direction:column;gap:3px}
.topbar-brand{font-size:17px;font-weight:800;color:#fff;letter-spacing:-.2px}
.topbar-page{font-size:13px;color:rgba(255,255,255,.72)}
.transaksi-wrapper{max-width:1200px;margin:26px auto 50px}
.transaksi-hero{padding:26px 28px;border-radius:18px;background:linear-gradient(135deg,#fff,#f8fbff);border:1px solid #e2e8f0;box-shadow:0 8px 25px rgba(15,23,42,.05)}
.transaksi-hero-top{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap}
.transaksi-hero h1{margin:0 0 7px;font-size:25px;color:#0f172a;letter-spacing:-.5px}
.transaksi-hero p{margin:0;color:#64748b;font-size:14px;line-height:1.6}
.print-btn{flex-shrink:0;padding:12px 20px;border:0;border-radius:11px;background:#0284c7;color:#fff;font-size:13px;font-weight:700;cursor:pointer;transition:.2s}
.print-btn:hover{background:#0369a1;transform:translateY(-1px);box-shadow:0 8px 18px rgba(2,132,199,.22)}
.summary-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:13px;margin-top:22px}
.summary-card{min-width:0;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;display:flex;align-items:center;gap:12px;box-shadow:0 4px 14px rgba(15,23,42,.035)}
.summary-icon{width:42px;height:42px;flex:0 0 42px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:#eff6ff;color:#0284c7}
.summary-icon svg{width:21px;height:21px}
.summary-card.warning .summary-icon{background:#fffbeb;color:#d97706}
.summary-card.success .summary-icon{background:#ecfdf5;color:#059669}
.summary-card.danger .summary-icon{background:#fef2f2;color:#dc2626}
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
.hapus-link{color:#dc2626;background:none;border:none;cursor:pointer;padding:0;font:inherit;font-weight:700;font-size:11px}
.empty-report{padding:55px 20px;text-align:center}
.empty-icon-custom{width:58px;height:58px;margin:0 auto 13px;display:flex;align-items:center;justify-content:center;border-radius:16px;background:#f1f5f9;color:#94a3b8}
.empty-icon-custom svg{width:29px;height:29px}
.empty-report strong{display:block;color:#334155;font-size:14px}
.empty-report span{display:block;margin-top:5px;color:#94a3b8;font-size:11px}
.print-header{display:none;text-align:center;margin-bottom:16px}
.print-header h2{margin:0 0 4px}
.print-header p{margin:0;color:#64748b;font-size:13px}
@media(max-width:700px){
.transaksi-topbar{min-height:76px!important;padding:0 16px 0 88px!important}
.topbar-brand{font-size:14px}
.topbar-page{font-size:11px}
.transaksi-wrapper{margin:18px auto 35px;padding-left:14px;padding-right:14px}
.transaksi-hero{padding:21px 18px;border-radius:16px}
.transaksi-hero-top{display:block}
.transaksi-hero h1{font-size:22px}
.transaksi-hero p{font-size:12px}
.print-btn{width:100%;margin-top:16px}
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
.report-table td:before{content:attr(data-label);font-size:10px;font-weight:700;color:#94a3b8;text-align:left;flex:0 0 100px}
.report-table td[data-label="Judul Buku"]{display:block;padding-bottom:11px;text-align:left}
.report-table td[data-label="Judul Buku"]:before{display:none}
.report-table td[data-label="Aksi"]{padding-top:11px;margin-top:4px;border-top:1px solid #f1f5f9}
.report-table td[data-label="Aksi"]:before{display:none}
}
@media print {
    .admin-sidebar, .admin-menu-toggle, .admin-sidebar-overlay, .transaksi-topbar { display: none !important; }
    .no-print, .print-btn, th:last-child, td:last-child { display: none !important; }
    body { background: #fff; }
    .admin-main { margin-left: 0 !important; }
    .container { max-width: 100%; margin: 0; padding: 0; }
    .transaksi-wrapper { max-width: 100%; margin: 0; }
    .transaksi-hero { display: none !important; }
    .report-table { box-shadow: none; border: none; border-radius: 0; }
    .print-header { display: block !important; }

    /* Paksa tabel tetap horizontal (lawan CSS responsive mode HP) */
    .report-table table { display: table !important; width: 100% !important; border-collapse: collapse !important; }
    .report-table thead { display: table-header-group !important; }
    .report-table tbody { display: table-row-group !important; }
    .report-table tr {
      display: table-row !important;
      background: none !important;
      border: none !important;
      margin: 0 !important;
      padding: 0 !important;
      box-shadow: none !important;
      page-break-inside: avoid;
    }
    .report-table th, .report-table td {
      display: table-cell !important;
      text-align: left !important;
      border: 1px solid #cbd5e1 !important;
      padding: 8px 10px !important;
      font-size: 11px !important;
    }
    .report-table td::before { content: none !important; }
    .report-table thead th {
      background: #f1f5f9 !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
      font-size: 10.5px;
      text-transform: uppercase;
      color: #334155 !important;
    }
    .badge { border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 6px; background: none !important; color: #1e293b !important; }
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
      <a href="transaksi.php" class="admin-side-link active"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/></svg></span><span>Transaksi</span></a>
      <a href="kunjungan.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/></svg></span><span>Kunjungan</span></a>
      <a href="petugas.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="3"/><path d="M4 19.5c0-3 2.2-5 5-5s5 2 5 5"/><path d="M14.5 9.2h5M17 6.7v5"/></svg></span><span>Petugas</span></a>
    </nav>
    <div class="admin-sidebar-bottom">
      <div class="admin-side-user"><span class="admin-avatar">A</span><span><strong>Admin</strong><small>Pengelola Perpustakaan</small></span></div>
      <a href="logout.php" class="admin-logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/></svg><span>Keluar</span></a>
    </div>
  </aside>

  <main class="admin-main">

  <div class="topbar transaksi-topbar">
    <div class="topbar-info">
      <span class="topbar-brand">Perpustakaan Digital Ilmu</span>
      <span class="topbar-page">Data Transaksi Peminjaman</span>
    </div>
  </div>

  <div class="container">
  <div class="transaksi-wrapper">

  <?php if ($pesanGagalHapus): ?>
    <p class="alert alert-gagal" style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;"><?= htmlspecialchars($pesanGagalHapus) ?></p>
  <?php endif; ?>

  <div class="print-header">
    <h2>Laporan Data Transaksi Peminjaman</h2>
    <p>Perpustakaan Digital Sekolah — dicetak <?= date('d-m-Y H:i') ?> WIB</p>
  </div>

  <div class="transaksi-hero">
    <div class="transaksi-hero-top">
      <div>
        <h1>Data Transaksi Peminjaman</h1>
        <p>Pantau seluruh transaksi peminjaman dan pengembalian buku siswa.</p>
      </div>
      <button onclick="window.print()" class="print-btn no-print" type="button">Cetak Laporan</button>
    </div>

    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5V5a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v14"/>
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H19"/>
          </svg>
        </div>
        <div class="summary-content">
          <strong><?= $totalTransaksi ?></strong>
          <span>Total Transaksi</span>
        </div>
      </div>

      <div class="summary-card warning">
        <div class="summary-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9.5"/>
            <polyline points="12 7 12 12 15.5 14"/>
          </svg>
        </div>
        <div class="summary-content">
          <strong><?= $totalDipinjam ?></strong>
          <span>Sedang Dipinjam</span>
        </div>
      </div>

      <div class="summary-card success">
        <div class="summary-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9.5"/>
            <polyline points="8 12.5 11 15.5 16 9"/>
          </svg>
        </div>
        <div class="summary-content">
          <strong><?= $totalDikembalikan ?></strong>
          <span>Sudah Dikembalikan</span>
        </div>
      </div>

      <div class="summary-card danger">
        <div class="summary-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9.5"/>
            <path d="M12 7.5v5l3 2"/>
          </svg>
        </div>
        <div class="summary-content">
          <strong>Rp<?= number_format($totalDendaBelumLunas, 0, ',', '.') ?></strong>
          <span>Denda Belum Lunas</span>
        </div>
      </div>
    </div>
  </div>

  <div class="report-section">
    <div class="section-heading">
      <h2>Riwayat Transaksi</h2>
      <span><?= $totalTransaksi ?> data</span>
    </div>

    <div class="report-table">
      <?php if ($daftarTransaksi): ?>
      <table>
        <thead>
          <tr>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <th>Judul Buku</th>
            <th>Tgl Pinjam</th>
            <th>Jatuh Tempo</th>
            <th>Tgl Kembali</th>
            <th>Status</th>
            <th>Denda</th>
            <th>Status Bayar</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarTransaksi as $t): ?>
          <tr>
            <td data-label="Nama Siswa" style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($t['nama_lengkap']) ?></td>
            <td data-label="Kelas"><?= htmlspecialchars($t['kelas']) ?></td>
            <td data-label="Judul Buku"><?= htmlspecialchars($t['judul'] ?? 'Buku tidak ditemukan') ?><?php if (!empty($t['deleted_at'])): ?> <span class="badge badge-habis">Diarsipkan</span><?php endif; ?></td>
            <td data-label="Tgl Pinjam"><?= $t['tanggal_pinjam'] ?></td>
            <td data-label="Jatuh Tempo"><?= $t['tanggal_jatuh_tempo'] ?></td>
            <td data-label="Tgl Kembali"><?= $t['tanggal_kembali'] ?? '-' ?></td>
            <td data-label="Status">
              <?php if ($t['status'] === 'dipinjam'): ?>
                <span class="badge badge-pending">Dipinjam</span>
              <?php elseif ($t['status'] === 'menunggu_konfirmasi'): ?>
                <span class="badge badge-habis">Menunggu Konfirmasi</span>
              <?php else: ?>
                <span class="badge badge-ok"><?= htmlspecialchars(ucfirst($t['status'])) ?></span>
              <?php endif; ?>
            </td>
            <td data-label="Denda">
              <?php if ((float)$t['denda'] > 0): ?>
                Rp<?= number_format((float)$t['denda'], 0, ',', '.') ?>
              <?php else: ?>
                <span style="color:var(--text-muted);">-</span>
              <?php endif; ?>
            </td>
            <td data-label="Status Bayar">
              <?php if ((float)$t['denda'] <= 0): ?>
                <span style="color:var(--text-muted);">-</span>
              <?php elseif ($t['status_denda'] === 'Lunas'): ?>
                <span class="badge badge-ok">Lunas<?= $t['tanggal_bayar_denda'] ? ' (' . htmlspecialchars($t['tanggal_bayar_denda']) . ')' : '' ?></span>
              <?php else: ?>
                <span class="badge badge-habis">Belum Lunas</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-report">
        <div class="empty-icon-custom">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5V5a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v14"/>
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H19"/>
          </svg>
        </div>
        <strong>Belum ada transaksi</strong>
        <span>Transaksi peminjaman siswa akan muncul di sini.</span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  </div>
  </div>
  </main>
</body>
</html>