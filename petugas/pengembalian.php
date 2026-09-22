<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';
require_once '../config/constants.php';

date_default_timezone_set('Asia/Jakarta');

$pesan = $_GET['pesan'] ?? '';

$today = date('Y-m-d');

// Semua petugas aktif dapat membantu memproses pengembalian buku siswa.
// Siswa boleh lebih dulu mengajukan pengembalian (menunggu_konfirmasi),
// atau petugas dapat langsung memproses transaksi yang masih dipinjam saat
// siswa datang membawa buku. Tidak ada role baru; seluruh petugas memakai
// hak akses yang sama.
$stmtPengembalian = $koneksi->prepare("
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
            CASE
                WHEN t.status = 'menunggu_konfirmasi'
                    THEN COALESCE(t.tanggal_pengajuan_kembali, ?)
                ELSE ?
            END,
            t.tanggal_jatuh_tempo
        ) AS hari_terlambat
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    LEFT JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.status IN ('dipinjam', 'menunggu_konfirmasi')
    ORDER BY
        CASE WHEN t.status = 'menunggu_konfirmasi' THEN 0 ELSE 1 END,
        t.tanggal_jatuh_tempo ASC,
        t.id_transaksi ASC
");
$stmtPengembalian->execute([$today, $today]);
$daftarPengembalian = $stmtPengembalian->fetchAll();

$totalMenunggu = 0;
$totalAktif = 0;
$totalTerlambat = 0;
$totalEstimasiDenda = 0;

foreach ($daftarPengembalian as $p) {
    if ($p['status'] === 'menunggu_konfirmasi') {
        $totalMenunggu++;
    } else {
        $totalAktif++;
    }
    $hariTerlambat = max(0, (int)$p['hari_terlambat']);
    if ($hariTerlambat > 0) {
        $totalTerlambat++;
        $totalEstimasiDenda += $hariTerlambat * TARIF_DENDA_PER_HARI;
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
    grid-template-columns:repeat(4,1fr);
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
  .condition-select, .condition-note { width:100%; box-sizing:border-box; border:1px solid #dbe3ef; border-radius:9px; padding:8px 9px; background:#fff; color:#0f172a; font-size:.74rem; }
  .condition-note { margin-top:6px; min-height:54px; resize:vertical; font-family:inherit; }
  .condition-help { margin-top:5px; color:var(--muted); font-size:.68rem; line-height:1.45; }
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
        <p>Semua petugas aktif dapat membantu mengembalikan buku siswa, baik dari pengajuan siswa maupun langsung saat buku dibawa ke perpustakaan.</p>
      </div>
    </div>

    <?php if ($pesan === 'sukses'): ?>
      <div class="alert-success">✓ Pengembalian buku berhasil dikonfirmasi.</div>
    <?php elseif ($pesan === 'kondisi_tidak_valid'): ?>
      <div class="alert-error" style="margin-bottom:18px;">Kondisi buku wajib dipilih: Baik, Rusak, atau Hilang.</div>
    <?php endif; ?>

    <div class="return-summary">
      <div class="return-summary-card">
        <strong><?= $totalAktif ?></strong>
        <span>Masih Dipinjam</span>
      </div>
      <div class="return-summary-card">
        <strong><?= $totalMenunggu ?></strong>
        <span>Menunggu Konfirmasi</span>
      </div>
      <div class="return-summary-card">
        <strong><?= $totalTerlambat ?></strong>
        <span>Sedang Terlambat</span>
      </div>
      <div class="return-summary-card">
        <strong>Rp<?= number_format($totalEstimasiDenda, 0, ',', '.') ?></strong>
        <span>Estimasi Total Denda</span>
      </div>
    </div>

    <div class="card">
      <div style="margin-bottom:15px;">
        <h3 style="margin:0 0 5px;">Daftar Buku yang Dapat Dikembalikan</h3>
        <p style="margin:0; color:var(--muted); font-size:.8rem;">
          Petugas aktif mana pun dapat memproses buku yang masih <strong>Dipinjam</strong> maupun yang sudah <strong>Menunggu Konfirmasi</strong>.
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
                <th>Kondisi Buku</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($daftarPengembalian as $p):
                $hariTerlambat = max(0, (int)$p['hari_terlambat']);
                $estimasiDenda = $hariTerlambat > 0
                    ? $hariTerlambat * TARIF_DENDA_PER_HARI
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
                  <td>
                    <?php if ($p['status'] === 'menunggu_konfirmasi'): ?>
                      <span class="status-waiting">Menunggu Konfirmasi</span>
                    <?php else: ?>
                      <span class="on-time-badge">Masih Dipinjam</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($hariTerlambat > 0): ?>
                      <span class="late-badge"><?= $hariTerlambat ?> hari · Rp<?= number_format($estimasiDenda, 0, ',', '.') ?></span>
                    <?php else: ?>
                      <span class="on-time-badge">Tidak ada denda</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" action="proses_kembali.php" class="return-action-form"
                          data-late-fine="<?= (int)$estimasiDenda ?>"
                          onsubmit="return konfirmasiPengembalian(this);">
                      <?= csrfField() ?>
                      <input type="hidden" name="id_transaksi" value="<?= (int)$p['id_transaksi'] ?>">
                      <select name="kondisi_buku" class="condition-select" required onchange="updateDendaKondisi(this)">
                        <option value="">Pilih kondisi...</option>
                        <option value="Baik">Baik</option>
                        <option value="Rusak">Rusak (+Rp<?= number_format(DENDA_BUKU_RUSAK, 0, ',', '.') ?>)</option>
                        <option value="Hilang">Hilang (+Rp<?= number_format(DENDA_BUKU_HILANG, 0, ",", ".") ?>)</option>
                      </select>
                      <textarea name="catatan_kondisi" class="condition-note" maxlength="255" placeholder="Catatan kondisi (opsional)"></textarea>
                      <div class="condition-help">Rusak menambah denda Rp<?= number_format(DENDA_BUKU_RUSAK, 0, ',', '.') ?>, Hilang menambah denda Rp<?= number_format(DENDA_BUKU_HILANG, 0, ',', '.') ?>. Buku Rusak/Hilang tidak menambah stok otomatis.</div>
                      <div class="condition-help estimate-denda">Denda keterlambatan: Rp<?= number_format($estimasiDenda, 0, ',', '.') ?></div>
                      <button type="submit" class="btn" style="margin-top:7px;"><?= $p['status'] === 'menunggu_konfirmasi' ? 'Konfirmasi' : 'Proses Pengembalian' ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-return">
          <strong>Belum ada buku yang dapat dikembalikan</strong>
          <span>Buku yang masih dipinjam atau menunggu konfirmasi pengembalian akan muncul di halaman ini.</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
  </main>

<script>
function updateDendaKondisi(select){
  const form=select.closest('form'); if(!form) return;
  const late=Number(form.dataset.lateFine||0);
  const damage=select.value==='Rusak' ? <?= (int)DENDA_BUKU_RUSAK ?> : (select.value==='Hilang' ? <?= (int)DENDA_BUKU_HILANG ?> : 0);
  const total=late+damage;
  const el=form.querySelector('.estimate-denda');
  if(el) el.textContent = total>0 ? 'Estimasi total denda: Rp'+total.toLocaleString('id-ID') : 'Tidak ada denda';
}
function konfirmasiPengembalian(form){
  const kondisi=form.querySelector('[name="kondisi_buku"]').value;
  if(!kondisi){ alert('Pilih kondisi buku terlebih dahulu.'); return false; }
  const late=Number(form.dataset.lateFine||0);
  const damage=kondisi==='Rusak' ? <?= (int)DENDA_BUKU_RUSAK ?> : (kondisi==='Hilang' ? <?= (int)DENDA_BUKU_HILANG ?> : 0);
  const total=late+damage;
  const denda=total>0 ? ' Total denda: Rp'+total.toLocaleString('id-ID')+'.' : ' Tidak ada denda.';
  const stok=kondisi==='Baik' ? ' Stok buku akan kembali tersedia.' : ' Buku '+kondisi.toLowerCase()+' tidak menambah stok otomatis.';
  return confirm('Konfirmasi pengembalian dengan kondisi '+kondisi+'?'+denda+stok);
}
</script>
</body>
</html>
