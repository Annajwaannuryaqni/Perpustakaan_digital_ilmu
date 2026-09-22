<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';
require_once '../config/constants.php';

date_default_timezone_set('Asia/Jakarta');

$q = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
if (!in_array($statusFilter, ['', 'terbuka', 'selesai'], true)) {
    $statusFilter = '';
}

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(a.nama_lengkap LIKE ? OR a.nis LIKE ? OR b.judul LIKE ? OR b.kode_buku LIKE ? OR CAST(l.id_transaksi AS CHAR) LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

if ($statusFilter !== '') {
    $where[] = 'l.status_laporan = ?';
    $params[] = $statusFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT
        l.id_laporan,
        l.id_transaksi,
        l.id_anggota,
        l.id_buku,
        l.id_petugas,
        l.tanggal_laporan,
        l.denda_kondisi,
        l.catatan,
        l.status_laporan,
        t.tanggal_pinjam,
        t.tanggal_jatuh_tempo,
        t.tanggal_kembali,
        t.denda AS total_denda,
        t.denda_kondisi,
        t.status_denda,
        a.nama_lengkap AS nama_anggota,
        a.nis,
        a.kelas,
        b.judul,
        b.kode_buku,
        b.pengarang,
        p.nama_lengkap AS nama_petugas
    FROM laporan_buku_hilang l
    JOIN transaksi t ON t.id_transaksi = l.id_transaksi
    JOIN anggota a ON a.id_anggota = l.id_anggota
    LEFT JOIN buku b ON b.id_buku = l.id_buku
    LEFT JOIN petugas p ON p.id_petugas = l.id_petugas
    $whereSql
    ORDER BY l.tanggal_laporan DESC, l.id_laporan DESC
";
$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$daftarLaporan = $stmt->fetchAll();

$totalLaporan = count($daftarLaporan);
$totalTerbuka = 0;
$totalDendaKondisi = 0;
foreach ($daftarLaporan as $l) {
    if ($l['status_laporan'] === 'terbuka') {
        $totalTerbuka++;
    }
    $totalDendaKondisi += (int)$l['denda_kondisi'];
}

$pesan = $_GET['pesan'] ?? '';
$activeMenu = 'laporan_hilang';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Buku Hilang - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .report-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
  .report-summary-card{padding:16px 18px;border:1px solid var(--border);border-radius:14px;background:#fff;box-shadow:var(--shadow)}
  .report-summary-card strong{display:block;color:var(--navy);font-size:1.35rem;line-height:1.2}
  .report-summary-card span{display:block;margin-top:5px;color:var(--muted);font-size:.76rem}
  .report-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
  .report-toolbar input,.report-toolbar select{height:42px;border:1px solid var(--border);border-radius:10px;padding:0 12px;background:#fff;color:var(--text);font-family:inherit;font-size:.8rem}
  .report-toolbar input{flex:1;min-width:240px}
  .report-toolbar .btn{height:42px}
  .report-table-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:14px;background:#fff}
  .report-table-wrap table{width:100%;min-width:1050px;border-collapse:collapse}
  .report-table-wrap th{padding:13px 14px;background:#f8fafc;color:var(--muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;text-align:left;border-bottom:1px solid var(--border)}
  .report-table-wrap td{padding:14px;color:var(--text);font-size:.8rem;border-bottom:1px solid #eef2f7;vertical-align:top}
  .report-table-wrap tr:last-child td{border-bottom:0}
  .main-name{display:block;color:var(--navy);font-weight:700}
  .meta{display:block;margin-top:3px;color:var(--muted);font-size:.7rem;line-height:1.45}
  .badge-open{display:inline-flex;padding:6px 9px;border-radius:999px;background:#fff7ed;color:#c2410c;font-size:.69rem;font-weight:700;white-space:nowrap}
  .badge-done{display:inline-flex;padding:6px 9px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:.69rem;font-weight:700;white-space:nowrap}
  .loss-highlight{font-weight:800;color:#b91c1c}
  .empty-report{padding:55px 20px;text-align:center;color:var(--muted)}
  .empty-report strong{display:block;color:var(--navy);font-size:.95rem;margin-bottom:5px}
  .note{max-width:220px;white-space:normal;line-height:1.45}
  .print-btn{margin-left:auto}
  @media(max-width:800px){.report-summary{grid-template-columns:1fr}}
  @media print{
    .admin-sidebar,.admin-menu-toggle,.admin-sidebar-overlay,.report-toolbar,.print-btn{display:none!important}
    .admin-main{margin:0!important;padding:0!important}
    .container{max-width:none!important}
    .report-table-wrap{border:0}
  }
</style>
</head>
<body class="admin-page">
<?php require_once '../includes/petugas_sidebar.php'; ?>

<div class="container">
  <div class="page-head" style="display:flex;align-items:flex-start;gap:15px;">
    <div>
      <h1>Laporan Buku Hilang</h1>
      <p>Daftar buku yang dilaporkan hilang saat proses pengembalian.</p>
    </div>
    <button type="button" class="btn print-btn" onclick="window.print()">Cetak Laporan</button>
  </div>

  <?php if ($pesan === 'sukses'): ?>
    <div class="alert alert-sukses">Laporan buku hilang berhasil diperbarui.</div>
  <?php endif; ?>

  <div class="report-summary">
    <div class="report-summary-card">
      <strong><?= $totalLaporan ?></strong>
      <span>Total Laporan Hilang</span>
    </div>
    <div class="report-summary-card">
      <strong><?= $totalTerbuka ?></strong>
      <span>Laporan Masih Terbuka</span>
    </div>
    <div class="report-summary-card">
      <strong>Rp<?= number_format($totalDendaKondisi, 0, ',', '.') ?></strong>
      <span>Total Denda Kondisi Hilang</span>
    </div>
  </div>

  <div class="card">
    <form method="GET" class="report-toolbar">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari siswa, NIS, judul buku, kode buku, atau ID transaksi...">
      <select name="status">
        <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Semua Status</option>
        <option value="terbuka" <?= $statusFilter === 'terbuka' ? 'selected' : '' ?>>Terbuka</option>
        <option value="selesai" <?= $statusFilter === 'selesai' ? 'selected' : '' ?>>Selesai</option>
      </select>
      <button type="submit" class="btn">Cari</button>
      <?php if ($q !== '' || $statusFilter !== ''): ?>
        <a href="laporan_buku_hilang.php" class="btn" style="background:#f1f5f9;color:#334155;">Reset</a>
      <?php endif; ?>
    </form>

    <?php if ($daftarLaporan): ?>
      <div class="report-table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID / Tanggal</th>
              <th>Anggota</th>
              <th>Buku</th>
              <th>Pinjam / Jatuh Tempo</th>
              <th>Kembali</th>
              <th>Denda</th>
              <th>Petugas</th>
              <th>Status</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($daftarLaporan as $l): ?>
            <tr>
              <td>
                <span class="main-name">#<?= (int)$l['id_laporan'] ?></span>
                <span class="meta">Transaksi #<?= (int)$l['id_transaksi'] ?></span>
                <span class="meta"><?= htmlspecialchars($l['tanggal_laporan']) ?></span>
              </td>
              <td>
                <span class="main-name"><?= htmlspecialchars($l['nama_anggota']) ?></span>
                <span class="meta">NIS <?= htmlspecialchars($l['nis']) ?></span>
                <span class="meta">Kelas <?= htmlspecialchars($l['kelas']) ?></span>
              </td>
              <td>
                <span class="main-name"><?= htmlspecialchars($l['judul'] ?? 'Buku tidak ditemukan') ?></span>
                <span class="meta"><?= htmlspecialchars($l['kode_buku'] ?? '-') ?> · <?= htmlspecialchars($l['pengarang'] ?? '-') ?></span>
              </td>
              <td>
                <span class="meta">Pinjam: <?= htmlspecialchars($l['tanggal_pinjam']) ?></span>
                <span class="meta">Jatuh tempo: <?= htmlspecialchars($l['tanggal_jatuh_tempo']) ?></span>
              </td>
              <td><?= htmlspecialchars($l['tanggal_kembali'] ?? '-') ?></td>
              <td>
                <span class="loss-highlight">Kondisi: Rp<?= number_format((int)$l['denda_kondisi'], 0, ',', '.') ?></span>
                <span class="meta">Total transaksi: Rp<?= number_format((int)$l['total_denda'], 0, ',', '.') ?></span>
                <span class="meta">Status denda: <?= htmlspecialchars($l['status_denda'] ?? '-') ?></span>
              </td>
              <td><?= $l['nama_petugas'] ? htmlspecialchars($l['nama_petugas']) : '-' ?></td>
              <td>
                <?php if ($l['status_laporan'] === 'terbuka'): ?>
                  <span class="badge-open">Terbuka</span>
                <?php else: ?>
                  <span class="badge-done">Selesai</span>
                <?php endif; ?>
              </td>
              <td class="note"><?= $l['catatan'] ? htmlspecialchars($l['catatan']) : '-' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-report">
        <strong>Belum ada laporan buku hilang</strong>
        <span>Jika petugas memilih kondisi “Hilang” saat mengonfirmasi pengembalian, laporan akan dibuat otomatis di sini.</span>
      </div>
    <?php endif; ?>
  </div>
</div>
</main>
</body>
</html>
