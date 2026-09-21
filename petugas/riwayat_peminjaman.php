<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(
        a.nama_lengkap LIKE :q
        OR a.nis LIKE :q
        OR b.judul LIKE :q
        OR b.kode_buku LIKE :q
        OR CAST(t.id_transaksi AS CHAR) LIKE :q
    )";
    $params['q'] = '%' . $q . '%';
}

$allowedStatuses = ['dipinjam', 'menunggu_konfirmasi', 'dikembalikan', 'terlambat'];
if (in_array($status, $allowedStatuses, true)) {
    $where[] = 't.status = :status';
    $params['status'] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Hitung total transaksi untuk pagination.
$countStmt = $koneksi->prepare("SELECT COUNT(*) FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    LEFT JOIN buku b ON b.id_buku = t.id_buku
    $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "SELECT
            t.id_transaksi,
            t.tanggal_pinjam,
            t.tanggal_jatuh_tempo,
            t.tanggal_pengajuan_kembali,
            t.tanggal_kembali,
            t.status,
            t.denda,
            t.status_denda,
            t.tanggal_bayar_denda,
            t.petugas_input,
            a.nama_lengkap,
            a.nis,
            b.judul,
            b.kode_buku,
            b.deleted_at
        FROM transaksi t
        JOIN anggota a ON a.id_anggota = t.id_anggota
        LEFT JOIN buku b ON b.id_buku = t.id_buku
        $whereSql
        ORDER BY t.tanggal_pinjam DESC, t.id_transaksi DESC
        LIMIT :limit OFFSET :offset";

$stmt = $koneksi->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$riwayat = $stmt->fetchAll();

// Ringkasan seluruh riwayat (mengikuti filter pencarian/status bila dipakai).
$summaryStmt = $koneksi->prepare("SELECT
    COUNT(*) AS total_transaksi,
    SUM(CASE WHEN t.status = 'dikembalikan' THEN 1 ELSE 0 END) AS total_selesai,
    SUM(CASE WHEN t.status IN ('dipinjam','menunggu_konfirmasi','terlambat') THEN 1 ELSE 0 END) AS total_aktif,
    COALESCE(SUM(t.denda), 0) AS total_denda
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    LEFT JOIN buku b ON b.id_buku = t.id_buku
    $whereSql");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: [];

$activeMenu = 'riwayat';

function riwayatStatusLabel($status) {
    return match ($status) {
        'dikembalikan' => 'Dikembalikan',
        'terlambat' => 'Terlambat',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        default => 'Dipinjam',
    };
}

function riwayatStatusClass($status) {
    return match ($status) {
        'dikembalikan' => 'returned',
        'terlambat' => 'late',
        'menunggu_konfirmasi' => 'waiting',
        default => 'borrowed',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Peminjaman - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .history-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:20px; }
  .history-stat { background:rgba(255,255,255,.82); border:1px solid rgba(148,163,184,.22); border-radius:16px; padding:16px 18px; box-shadow:0 8px 24px rgba(15,23,42,.05); }
  .history-stat small { display:block; color:#64748b; font-size:.75rem; margin-bottom:5px; }
  .history-stat strong { display:block; color:#0f172a; font-size:1.25rem; }
  .history-toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
  .history-search { flex:1 1 300px; display:flex; gap:8px; }
  .history-search input,.history-toolbar select { width:100%; min-height:42px; border:1px solid #dbe3ef; border-radius:11px; padding:0 13px; background:#fff; color:#0f172a; outline:none; }
  .history-search input:focus,.history-toolbar select:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.10); }
  .history-btn { min-height:42px; border:0; border-radius:11px; padding:0 16px; background:#2563eb; color:#fff; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
  .history-btn.secondary { background:#eef2f7; color:#334155; }
  .history-table-wrap { overflow-x:auto; }
  .history-table { width:100%; min-width:1050px; border-collapse:collapse; }
  .history-table th,.history-table td { padding:13px 14px; border-bottom:1px solid #edf1f6; text-align:left; vertical-align:middle; }
  .history-table th { color:#64748b; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
  .history-table td { color:#334155; font-size:.82rem; }
  .history-table td strong { color:#0f172a; }
  .history-table .muted { color:#94a3b8; }
  .status-badge,.archive-badge,.fine-badge { display:inline-flex; align-items:center; border-radius:999px; padding:5px 9px; font-size:.68rem; font-weight:700; white-space:nowrap; }
  .status-badge.borrowed { background:rgba(37,99,235,.10); color:#2563eb; }
  .status-badge.returned { background:rgba(22,163,74,.10); color:#15803d; }
  .status-badge.late { background:rgba(220,38,38,.10); color:#dc2626; }
  .status-badge.waiting { background:rgba(245,158,11,.12); color:#b45309; }
  .archive-badge { margin-top:5px; background:rgba(100,116,139,.10); color:#64748b; }
  .fine-badge { background:rgba(22,163,74,.10); color:#15803d; }
  .fine-badge.unpaid { background:rgba(220,38,38,.10); color:#dc2626; }
  .empty-history { text-align:center; padding:45px 20px !important; color:#64748b; }
  .pagination { display:flex; justify-content:flex-end; align-items:center; gap:7px; margin-top:18px; flex-wrap:wrap; }
  .pagination a,.pagination span { min-width:36px; height:36px; padding:0 10px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; font-size:.78rem; }
  .pagination a { background:#f1f5f9; color:#334155; }
  .pagination a.active { background:#2563eb; color:#fff; }
  .pagination span { color:#64748b; }
  @media (max-width:900px) { .history-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:560px) { .history-summary { grid-template-columns:1fr; } .history-search { flex-basis:100%; } }
</style>
</head>
<body class="admin-page">
<?php require_once '../includes/petugas_sidebar.php'; ?>

<div class="container">
  <div class="page-head">
    <div>
      <div class="breadcrumb">Panel Petugas &bull; Data</div>
      <h1>Riwayat Peminjaman</h1>
      <p>Melihat seluruh riwayat transaksi peminjaman dan pengembalian anggota.</p>
    </div>
  </div>

  <div class="history-summary">
    <div class="history-stat"><small>Total Transaksi</small><strong><?= number_format((int)($summary['total_transaksi'] ?? 0), 0, ',', '.') ?></strong></div>
    <div class="history-stat"><small>Sudah Dikembalikan</small><strong><?= number_format((int)($summary['total_selesai'] ?? 0), 0, ',', '.') ?></strong></div>
    <div class="history-stat"><small>Masih Berjalan</small><strong><?= number_format((int)($summary['total_aktif'] ?? 0), 0, ',', '.') ?></strong></div>
    <div class="history-stat"><small>Total Denda Tercatat</small><strong>Rp <?= number_format((int)($summary['total_denda'] ?? 0), 0, ',', '.') ?></strong></div>
  </div>

  <div class="glass-card" style="padding:22px 24px;">
    <form method="get" class="history-toolbar">
      <div class="history-search">
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama siswa, NIS, judul, kode buku, atau ID transaksi...">
        <button class="history-btn" type="submit">Cari</button>
      </div>
      <select name="status" onchange="this.form.submit()" aria-label="Filter status">
        <option value="">Semua Status</option>
        <?php foreach ($allowedStatuses as $option): ?>
          <option value="<?= htmlspecialchars($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= htmlspecialchars(riwayatStatusLabel($option)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($q !== '' || $status !== ''): ?>
        <a href="riwayat_peminjaman.php" class="history-btn secondary">Reset</a>
      <?php endif; ?>
    </form>

    <div class="history-table-wrap">
      <table class="history-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Anggota</th>
            <th>Buku</th>
            <th>Tanggal Pinjam</th>
            <th>Jatuh Tempo</th>
            <th>Tanggal Kembali</th>
            <th>Status</th>
            <th>Denda</th>
            <th>Petugas</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$riwayat): ?>
          <tr><td colspan="9" class="empty-history">Belum ada riwayat peminjaman yang sesuai.</td></tr>
        <?php else: ?>
          <?php foreach ($riwayat as $row): ?>
          <tr>
            <td><strong>#<?= (int)$row['id_transaksi'] ?></strong></td>
            <td>
              <strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong><br>
              <span class="muted"><?= htmlspecialchars($row['nis']) ?></span>
            </td>
            <td>
              <?php if ($row['judul'] !== null): ?>
                <strong><?= htmlspecialchars($row['judul']) ?></strong><br>
                <span class="muted"><?= htmlspecialchars($row['kode_buku'] ?? '-') ?></span>
                <?php if (!empty($row['deleted_at'])): ?><br><span class="archive-badge">Buku diarsipkan</span><?php endif; ?>
              <?php else: ?>
                <strong class="muted">Buku tidak tersedia</strong><br>
                <span class="archive-badge">Data buku tidak ditemukan</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['tanggal_pinjam']) ?></td>
            <td><?= htmlspecialchars($row['tanggal_jatuh_tempo']) ?></td>
            <td><?= htmlspecialchars($row['tanggal_kembali'] ?: '-') ?></td>
            <td><span class="status-badge <?= htmlspecialchars(riwayatStatusClass($row['status'])) ?>"><?= htmlspecialchars(riwayatStatusLabel($row['status'])) ?></span></td>
            <td>
              <?php if ((int)$row['denda'] > 0): ?>
                <strong>Rp <?= number_format((int)$row['denda'], 0, ',', '.') ?></strong><br>
                <span class="fine-badge <?= $row['status_denda'] === 'Lunas' ? '' : 'unpaid' ?>"><?= htmlspecialchars($row['status_denda']) ?></span>
              <?php else: ?>
                <span class="muted">Tidak ada</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['petugas_input'] ?: '-') ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(['q'=>$q,'status'=>$status,'page'=>$page-1]) ?>">&laquo;</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2): ?>
          <a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= http_build_query(['q'=>$q,'status'=>$status,'page'=>$i]) ?>"><?= $i ?></a>
        <?php elseif ($i === 2 && $page > 4): ?>
          <span>...</span>
        <?php elseif ($i === $totalPages - 1 && $page < $totalPages - 3): ?>
          <span>...</span>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(['q'=>$q,'status'=>$status,'page'=>$page+1]) ?>">&raquo;</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</main>
</body>
</html>
