<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$daftarTransaksi = $koneksi->query("
    SELECT t.*, a.nama_lengkap, a.kelas, b.judul
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    JOIN buku b ON b.id_buku = t.id_buku
    ORDER BY t.id_transaksi DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Transaksi</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  @media print {
    .page-head a, .btn, .back-link, .no-print, th:last-child, td:last-child { display: none !important; }
    body { background: #fff; }
    .container { max-width: 100%; margin: 0; padding: 0; }
    .card { box-shadow: none; border: none; padding: 0; }
    .print-header { display: block !important; }

    /* Paksa tabel tetap horizontal (lawan CSS responsive mode HP) */
    table { display: table !important; width: 100% !important; border-collapse: collapse !important; }
    table thead { display: table-header-group !important; }
    table tbody { display: table-row-group !important; }
    table tr {
      display: table-row !important;
      background: none !important;
      border: none !important;
      margin: 0 !important;
      padding: 0 !important;
      box-shadow: none !important;
      page-break-inside: avoid;
    }
    table th, table td {
      display: table-cell !important;
      text-align: left !important;
      border: 1px solid #cbd5e1 !important;
      padding: 8px 10px !important;
      font-size: 11px !important;
    }
    table td::before { content: none !important; }
    table thead th {
      background: #f1f5f9 !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
      font-size: 10.5px;
      text-transform: uppercase;
      color: #334155 !important;
    }
    .badge { border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 6px; background: none !important; color: #1e293b !important; }
  }
  .print-header { display: none; text-align: center; margin-bottom: 16px; }
  .print-header h2 { margin: 0 0 4px; }
  .print-header p { margin: 0; color: #64748b; font-size: 13px; }
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
      <a href="anggota.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.3c2.4.3 4 2.2 4 5.2"/></svg></span><span>Anggota</span></a>
      <a href="transaksi.php" class="admin-side-link active"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/></svg></span><span>Transaksi</span></a>
      <a href="kunjungan.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/></svg></span><span>Kunjungan</span></a>
    </nav>
    <div class="admin-sidebar-bottom">
      <div class="admin-side-user"><span class="admin-avatar">A</span><span><strong>Admin</strong><small>Pengelola Perpustakaan</small></span></div>
      <a href="logout.php" class="admin-logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/></svg><span>Keluar</span></a>
    </div>
  </aside>
  <main class="admin-main">



  <div class="container">
    <div class="print-header">
      <h2>Laporan Data Transaksi Peminjaman</h2>
      <p>Perpustakaan Digital Sekolah — dicetak <?= date('d-m-Y H:i') ?> WIB</p>
    </div>

    <div class="page-head">
      <div>
        <h1>Data Transaksi Peminjaman</h1>
        <p>Total <?= count($daftarTransaksi) ?> transaksi tercatat.</p>
      </div>
      <button onclick="window.print()" class="btn no-print" type="button">Cetak Laporan</button>
    </div>

    <div class="card">
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
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarTransaksi as $t): ?>
          <tr>
            <td data-label="Nama Siswa" style="font-weight:600;"><?= htmlspecialchars($t['nama_lengkap']) ?></td>
            <td data-label="Kelas"><?= htmlspecialchars($t['kelas']) ?></td>
            <td data-label="Judul Buku"><?= htmlspecialchars($t['judul']) ?></td>
            <td data-label="Tgl Pinjam"><?= $t['tanggal_pinjam'] ?></td>
            <td data-label="Jatuh Tempo"><?= $t['tanggal_jatuh_tempo'] ?></td>
            <td data-label="Tgl Kembali"><?= $t['tanggal_kembali'] ?? '-' ?></td>
            <td data-label="Status">
              <?php if ($t['status'] === 'dipinjam'): ?>
                <span class="badge badge-pending">Dipinjam</span>
              <?php else: ?>
                <span class="badge badge-ok"><?= htmlspecialchars(ucfirst($t['status'])) ?></span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <form method="POST" action="hapus_transaksi.php" class="no-print" style="display:inline;" onsubmit="return confirm('Hapus data transaksi ini?')">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $t['id_transaksi'] ?>">
                <button type="submit" class="btn-link" style="color:var(--coral); background:none; border:none; cursor:pointer; padding:0; font:inherit;">Hapus</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarTransaksi): ?>
          <tr><td colspan="8" style="text-align:center;">Belum ada transaksi</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
  </div>
  </main>
</body>
</html>