<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$keyword = trim($_GET['q'] ?? '');

if ($keyword !== '') {
    $stmt = $koneksi->prepare("
        SELECT * FROM anggota
        WHERE nama_lengkap LIKE :kw OR nis LIKE :kw OR kelas LIKE :kw OR username LIKE :kw
        ORDER BY nama_lengkap ASC
    ");
    $stmt->execute(['kw' => '%' . $keyword . '%']);
    $daftarAnggota = $stmt->fetchAll();
} else {
    $daftarAnggota = $koneksi->query("SELECT * FROM anggota ORDER BY nama_lengkap ASC")->fetchAll();
}

$totalAktif    = count(array_filter($daftarAnggota, fn($a) => $a['status'] === 'aktif'));
$totalNonaktif = count($daftarAnggota) - $totalAktif;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Anggota | Perpustakaan Digital Ilmu</title>
<link rel="stylesheet" href="../assets/style.css">

<style>
/* ===== TOPBAR (selaras dengan halaman lain) ===== */
.anggota-topbar{min-height:86px!important;padding:0 38px 0 210px!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:20px}
.topbar-info{display:flex;flex-direction:column;gap:3px}
.topbar-brand{font-size:17px;font-weight:800;color:#fff;letter-spacing:-.2px}
.topbar-page{font-size:13px;color:rgba(255,255,255,.72)}

/* ===== WRAPPER + HERO ===== */
.anggota-wrapper{max-width:1200px;margin:26px auto 50px}
.anggota-hero{padding:26px 28px;border-radius:18px;background:linear-gradient(135deg,#fff,#f8fbff);border:1px solid #e2e8f0;box-shadow:0 8px 25px rgba(15,23,42,.05)}
.anggota-hero-top{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap}
.anggota-hero h1{margin:0 0 7px;font-size:25px;color:#0f172a;letter-spacing:-.5px}
.anggota-hero p{margin:0;color:#64748b;font-size:14px;line-height:1.6}
.tambah-btn{flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;padding:12px 20px;border:0;border-radius:11px;background:#0284c7;color:#fff;font-size:13px;font-weight:700;transition:.2s;white-space:nowrap}
.tambah-btn:hover{background:#0369a1;transform:translateY(-1px);box-shadow:0 8px 18px rgba(2,132,199,.22)}

/* ===== SUMMARY (kartu statistik) ===== */
.summary-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:13px;margin-top:22px}
.summary-card{min-width:0;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;display:flex;align-items:center;gap:12px;box-shadow:0 4px 14px rgba(15,23,42,.035)}
.summary-icon{width:42px;height:42px;flex:0 0 42px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:#eff6ff;color:#0284c7}
.summary-icon svg{width:21px;height:21px}
.summary-card.success .summary-icon{background:#ecfdf5;color:#059669}
.summary-card.danger .summary-icon{background:#fef2f2;color:#dc2626}
.summary-content{min-width:0}
.summary-content strong{display:block;color:#0f172a;font-size:19px;line-height:1.2}
.summary-content span{display:block;margin-top:3px;color:#94a3b8;font-size:11px}

/* ===== REPORT SECTION ===== */
.report-section{margin-top:25px}
.section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:13px}
.section-heading h2{margin:0;font-size:17px;color:#0f172a}
.section-heading span{font-size:11px;color:#94a3b8}
.report-card{padding:22px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 6px 20px rgba(15,23,42,.045)}

/* ===== SEARCH FORM ===== */
.search-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
.search-form input[type=text]{flex:1 1 220px;min-width:0;box-sizing:border-box;}
.search-form .btn{display:inline-flex;align-items:center;gap:6px;flex:0 0 auto;white-space:nowrap;}
.search-form .btn svg{width:16px;height:16px;flex:0 0 auto;display:block;}

/* ===== TABLE SCROLL WRAPPER (tablet-safe) ===== */
.table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch;}
.table-responsive table{min-width:640px;}

@media(max-width:700px){
.anggota-topbar{min-height:76px!important;padding:0 16px 0 88px!important}
.topbar-brand{font-size:14px}
.topbar-page{font-size:11px}
.anggota-wrapper{margin:18px auto 35px;padding-left:14px;padding-right:14px}
.anggota-hero{padding:21px 18px;border-radius:16px}
.anggota-hero-top{display:block}
.anggota-hero h1{font-size:22px}
.anggota-hero p{font-size:12px}
.tambah-btn{width:100%;margin-top:16px}
.summary-grid{grid-template-columns:1fr;gap:9px;margin-top:17px}
.summary-card{padding:12px}
.summary-icon{width:38px;height:38px;flex-basis:38px}
.summary-content strong{font-size:17px}
.summary-content span{font-size:10px}
.report-section{margin-top:21px}
.section-heading h2{font-size:15px}
.report-card{padding:16px;border-radius:14px}
}

@media(max-width:600px){
  .search-form{gap:8px;}
  .search-form input[type=text]{flex:1 1 100%;}
  .search-form .btn{flex:1 1 auto;justify-content:center;padding:11px 14px!important;}
  .search-form .btn svg{width:15px;height:15px;}
  .search-form .btn-link{flex:0 0 auto;}
}
@media(max-width:400px){
  .search-form{flex-direction:column;align-items:stretch;}
  .search-form .btn{width:100%;}
}

/* ===== MOBILE CARD VIEW FOR TABLE ===== */
@media(max-width:700px){
  .table-responsive{overflow-x:visible!important;}
  .table-responsive table{min-width:0!important;width:100%!important;}
  .report-card table thead{display:none!important;}
  .report-card table, .report-card table tbody{display:block!important;width:100%!important;}
  .report-card table tr{
    display:grid!important;
    grid-template-columns:1fr 1fr!important;
    grid-template-areas:
      "nama nama"
      "nis kelas"
      "hp username"
      "status status"
      "aksi aksi"!important;
    gap:10px 14px!important;
    background:#fff!important;border:1px solid #e2e8f0!important;border-radius:14px!important;
    padding:15px 16px!important;margin-bottom:12px!important;
    box-shadow:0 10px 24px -18px rgba(15,23,42,.22)!important;
  }
  .report-card table tr:last-child{margin-bottom:0!important;}
  .report-card table td{
    display:block!important;border:none!important;padding:0!important;text-align:left!important;
    font-size:.82rem!important;color:#1e293b!important;position:static!important;background:none!important;
  }
  /* reset label bawaan style.css dulu, baru tambah label kita sendiri secara selektif */
  .report-card table td::before{content:none!important;}
  .report-card table td[data-label="Nama"]{grid-area:nama!important;font:700 .98rem Poppins,sans-serif!important;color:#0f172a!important;
    padding-bottom:8px!important;border-bottom:1px solid #f1f5f9!important;margin-bottom:2px!important;}
  .report-card table td[data-label="NIS"]{grid-area:nis!important;}
  .report-card table td[data-label="Kelas"]{grid-area:kelas!important;}
  .report-card table td[data-label="No HP"]{grid-area:hp!important;}
  .report-card table td[data-label="Username"]{grid-area:username!important;}
  .report-card table td[data-label="Status"]{grid-area:status!important;}
  .report-card table td[data-label="Aksi"]{grid-area:aksi!important;display:flex!important;align-items:center!important;gap:16px!important;
    border-top:1px solid #f1f5f9!important;padding-top:10px!important;margin-top:2px!important;}
  .report-card table td[data-label="NIS"]::before,
  .report-card table td[data-label="Kelas"]::before,
  .report-card table td[data-label="No HP"]::before,
  .report-card table td[data-label="Username"]::before{
    content:attr(data-label)!important;display:block!important;font-size:.62rem!important;font-weight:700!important;
    text-transform:uppercase!important;letter-spacing:.04em!important;color:#94a3b8!important;margin-bottom:3px!important;
  }
  .report-card table td[colspan]{display:block!important;padding:20px 0!important;text-align:center!important;}
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
      <a href="buku.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/></svg></span><span>Buku</span></a>
      <a href="anggota.php" class="admin-side-link active"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.3c2.4.3 4 2.2 4 5.2"/></svg></span><span>Anggota</span></a>
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

  <div class="topbar anggota-topbar">
    <div class="topbar-info">
      <span class="topbar-brand">Perpustakaan Digital Ilmu</span>
      <span class="topbar-page">Kelola Anggota</span>
    </div>
  </div>

  <div class="container">
  <div class="anggota-wrapper">

  <div class="anggota-hero">
    <div class="anggota-hero-top">
      <div>
        <h1>Kelola Anggota</h1>
        <p>Total <?= count($daftarAnggota) ?> anggota ditampilkan<?= $keyword !== '' ? ' untuk pencarian "' . htmlspecialchars($keyword) . '"' : '' ?>.</p>
      </div>
      <a href="tambah_anggota.php" class="tambah-btn">+ Tambah Anggota Baru</a>
    </div>

    <div class="summary-grid">
      <div class="summary-card success">
        <div class="summary-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9.5"/>
            <polyline points="8 12.5 11 15.5 16 9"/>
          </svg>
        </div>
        <div class="summary-content">
          <strong><?= $totalAktif ?></strong>
          <span>Anggota Aktif</span>
        </div>
      </div>

      <div class="summary-card danger">
        <div class="summary-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9.5"/>
            <line x1="8.5" y1="8.5" x2="15.5" y2="15.5"/>
            <line x1="15.5" y1="8.5" x2="8.5" y2="15.5"/>
          </svg>
        </div>
        <div class="summary-content">
          <strong><?= $totalNonaktif ?></strong>
          <span>Nonaktif</span>
        </div>
      </div>
    </div>
  </div>

  <div class="report-section">
    <div class="section-heading">
      <h2>Daftar Anggota</h2>
      <span><?= count($daftarAnggota) ?> data</span>
    </div>

    <div class="report-card">
      <form method="GET" action="anggota.php" class="search-form">
        <input type="text" name="q" placeholder="Cari NIS, nama, kelas, atau username..."
               value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit" class="btn btn-outline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Cari</button>
        <?php if ($keyword !== ''): ?>
          <a href="anggota.php" class="btn-link">Reset</a>
        <?php endif; ?>
      </form>

      <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>NIS</th>
            <th>Nama</th>
            <th>Kelas</th>
            <th>No HP</th>
            <th>Username</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarAnggota as $a): ?>
          <tr>
            <td data-label="NIS"><?= htmlspecialchars($a['nis']) ?></td>
            <td data-label="Nama" style="font-weight:600;"><?= htmlspecialchars($a['nama_lengkap']) ?></td>
            <td data-label="Kelas"><?= htmlspecialchars($a['kelas']) ?></td>
            <td data-label="No HP"><?= htmlspecialchars($a['no_hp']) ?></td>
            <td data-label="Username"><?= htmlspecialchars($a['username']) ?></td>
            <td data-label="Status">
              <?php if ($a['status'] === 'aktif'): ?>
                <span class="badge badge-ok"><?= htmlspecialchars($a['status']) ?></span>
              <?php else: ?>
                <span class="badge badge-habis"><?= htmlspecialchars($a['status']) ?></span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <a href="edit_anggota.php?id=<?= $a['id_anggota'] ?>" class="btn-link">Edit</a> ·
              <form method="POST" action="hapus_anggota.php" style="display:inline;" onsubmit="return confirm('Yakin hapus anggota ini?')">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $a['id_anggota'] ?>">
                <button type="submit" class="btn-link" style="color:var(--coral); background:none; border:none; cursor:pointer; padding:0; font:inherit;">Hapus</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarAnggota): ?>
          <tr><td colspan="7" style="text-align:center;">
            <?= $keyword !== '' ? 'Tidak ada anggota yang cocok dengan pencarian "' . htmlspecialchars($keyword) . '"' : 'Belum ada data anggota' ?>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  </div>
  </div>
  </main>
</body>
</html>