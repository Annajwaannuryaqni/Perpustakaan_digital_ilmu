<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';
require_once '../config/constants.php';

date_default_timezone_set('Asia/Jakarta');

$pesan = $_GET['pesan'] ?? '';

// Halaman ini khusus untuk PETUGAS mengonfirmasi pengembalian
// yang sebelumnya sudah diajukan oleh siswa.
// Status yang boleh muncul di sini hanya: menunggu_konfirmasi.
$daftarPengembalian = $koneksi->query("
    SELECT
        t.id_transaksi,
        t.id_buku,
        t.id_anggota,
        t.tanggal_pinjam,
        t.tanggal_jatuh_tempo,
        t.tanggal_pengajuan_kembali,
        t.status,
        a.nama_lengkap AS nama_anggota,
        a.nis,
        a.kelas,
        b.judul,
        b.pengarang,
        b.deleted_at,
        DATEDIFF(
            COALESCE(t.tanggal_pengajuan_kembali, CURDATE()),
            t.tanggal_jatuh_tempo
        ) AS hari_terlambat
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    LEFT JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.status = 'menunggu_konfirmasi'
    ORDER BY t.tanggal_pengajuan_kembali ASC, t.id_transaksi ASC
")->fetchAll();

$totalMenunggu = count($daftarPengembalian);
$totalTerlambat = 0;
$totalEstimasiDenda = 0;

foreach ($daftarPengembalian as $p) {
    $hariTerlambat = max(0, (int)$p['hari_terlambat']);
    if ($hariTerlambat > 0) {
        $totalTerlambat++;
        $totalEstimasiDenda += min(
            $hariTerlambat * TARIF_DENDA_PER_HARI,
            TARIF_DENDA_MAKSIMUM
        );
    }
}

$activeMenu = 'pengembalian';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengembalian Buku - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .return-summary {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
    margin-bottom:20px;
  }
  .return-summary-card {
    padding:16px 18px;
    border:1px solid var(--border);
    border-radius:14px;
    background:#fff;
    box-shadow:var(--shadow);
  }
  .return-summary-card strong {
    display:block;
    color:var(--navy);
    font-size:1.35rem;
    line-height:1.2;
  }
  .return-summary-card span {
    display:block;
    margin-top:5px;
    color:var(--muted);
    font-size:.76rem;
  }
  .return-table-wrap {
    overflow-x:auto;
    border:1px solid var(--border);
    border-radius:14px;
    background:#fff;
  }
  .return-table-wrap table {
    width:100%;
    min-width:900px;
    border-collapse:collapse;
  }
  .return-table-wrap th {
    padding:13px 14px;
    background:#f8fafc;
    color:var(--muted);
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.03em;
    text-align:left;
    border-bottom:1px solid var(--border);
  }
  .return-table-wrap td {
    padding:14px;
    color:var(--text);
    font-size:.82rem;
    border-bottom:1px solid #eef2f7;
    vertical-align:middle;
  }
  .return-table-wrap tr:last-child td { border-bottom:0; }
  .member-name,
  .book-name {
    display:block;
    color:var(--navy);
    font-weight:700;
  }
  .member-meta,
  .book-meta {
    display:block;
    margin-top:3px;
    color:var(--muted);
    font-size:.72rem;
  }
  .status-waiting {
    display:inline-flex;
    align-items:center;
    padding:6px 9px;
    border-radius:999px;
    background:#fff7ed;
    color:#c2410c;
    font-size:.7rem;
    font-weight:700;
    white-space:nowrap;
  }
  .late-badge {
    display:inline-flex;
    padding:6px 9px;
    border-radius:999px;
    background:#fef2f2;
    color:#b91c1c;
    font-size:.7rem;
    font-weight:700;
    white-space:nowrap;
  }
  .on-time-badge {
    display:inline-flex;
    padding:6px 9px;
    border-radius:999px;
    background:#ecfdf5;
    color:#047857;
    font-size:.7rem;
    font-weight:700;
    white-space:nowrap;
  }
  .return-action-form { margin:0; }
  .return-action-form .btn { white-space:nowrap; }
  .empty-return {
    padding:50px 20px;
    text-align:center;
    color:var(--muted);
  }
  .empty-return strong {
    display:block;
    color:var(--navy);
    font-size:.95rem;
    margin-bottom:5px;
  }
  .alert-success {
    padding:12px 15px;
    margin-bottom:18px;
    border-radius:10px;
    background:#ecfdf5;
    color:#047857;
    border:1px solid #a7f3d0;
    font-size:.84rem;
    font-weight:600;
  }
  @media (max-width: 800px) {
    .return-summary { grid-template-columns:1fr; }
  }
</style>
</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Pengembalian Buku</h1>
        <p>Konfirmasi pengembalian buku yang sudah diajukan oleh siswa.</p>
      </div>
    </div>

    <?php if ($pesan === 'sukses'): ?>
      <div class="alert-success">✓ Pengembalian buku berhasil dikonfirmasi.</div>
    <?php endif; ?>

    <div class="return-summary">
      <div class="return-summary-card">
        <strong><?= $totalMenunggu ?></strong>
        <span>Menunggu Konfirmasi</span>
      </div>
      <div class="return-summary-card">
        <strong><?= $totalTerlambat ?></strong>
        <span>Pengajuan Terlambat</span>
      </div>
      <div class="return-summary-card">
        <strong>Rp<?= number_format($totalEstimasiDenda, 0, ',', '.') ?></strong>
        <span>Estimasi Total Denda</span>
      </div>
    </div>

    <div class="card">
      <div style="margin-bottom:15px;">
        <h3 style="margin:0 0 5px;">Daftar Pengajuan Pengembalian</h3>
        <p style="margin:0; color:var(--muted); font-size:.8rem;">
          Hanya pengembalian dengan status <strong>Menunggu Konfirmasi</strong> yang ditampilkan.
        </p>
      </div>

      <?php if ($daftarPengembalian): ?>
        <div class="return-table-wrap">
          <table>
            <thead>
              <tr>
                <th>Anggota</th>
                <th>Buku</th>
                <th>Tgl Pinjam</th>
                <th>Jatuh Tempo</th>
                <th>Diajukan Kembali</th>
                <th>Status</th>
                <th>Denda</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($daftarPengembalian as $p):
                $hariTerlambat = max(0, (int)$p['hari_terlambat']);
                $estimasiDenda = $hariTerlambat > 0
                    ? min($hariTerlambat * TARIF_DENDA_PER_HARI, TARIF_DENDA_MAKSIMUM)
                    : 0;
              ?>
                <tr>
                  <td>
                    <span class="member-name"><?= htmlspecialchars($p['nama_anggota']) ?></span>
                    <span class="member-meta">NIS <?= htmlspecialchars($p['nis']) ?> · Kelas <?= htmlspecialchars($p['kelas']) ?></span>
                  </td>
                  <td>
                    <span class="book-name"><?= htmlspecialchars($p['judul'] ?? 'Buku tidak ditemukan') ?></span>
                    <span class="book-meta"><?= htmlspecialchars($p['pengarang'] ?? '-') ?><?php if (!empty($p['deleted_at'])): ?> · Diarsipkan<?php endif; ?></span>
                  </td>
                  <td><?= htmlspecialchars($p['tanggal_pinjam']) ?></td>
                  <td><?= htmlspecialchars($p['tanggal_jatuh_tempo']) ?></td>
                  <td><?= htmlspecialchars($p['tanggal_pengajuan_kembali'] ?? '-') ?></td>
                  <td><span class="status-waiting">Menunggu Konfirmasi</span></td>
                  <td>
                    <?php if ($hariTerlambat > 0): ?>
                      <span class="late-badge"><?= $hariTerlambat ?> hari · Rp<?= number_format($estimasiDenda, 0, ',', '.') ?></span>
                    <?php else: ?>
                      <span class="on-time-badge">Tidak ada denda</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" action="proses_kembali.php" class="return-action-form"
                          onsubmit="return confirm('Konfirmasi pengembalian buku ini?<?= $estimasiDenda > 0 ? ' Estimasi denda: Rp' . number_format($estimasiDenda, 0, ',', '.') . '.' : ' Tidak ada denda.' ?>');">
                      <?= csrfField() ?>
                      <input type="hidden" name="id_transaksi" value="<?= (int)$p['id_transaksi'] ?>">
                      <button type="submit" class="btn">Konfirmasi</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-return">
          <strong>Belum ada pengajuan pengembalian</strong>
          <span>Pengajuan dari siswa akan muncul di halaman ini setelah siswa mengajukan pengembalian.</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
  </main>
</body>
</html>
