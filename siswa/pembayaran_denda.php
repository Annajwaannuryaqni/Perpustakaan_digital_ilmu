<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$id_anggota = $_SESSION['anggota_id'];
$pesan = $_GET['pesan'] ?? '';

$stmt = $koneksi->prepare("\n    SELECT\n        t.id_transaksi, t.denda, t.status_denda, t.tanggal_kembali,\n        b.judul, b.pengarang, b.deleted_at,\n        p.id_pembayaran, p.metode_pembayaran, p.file_bukti, p.uploaded_at, p.status AS status_bukti, p.catatan\n    FROM transaksi t\n    LEFT JOIN buku b ON b.id_buku = t.id_buku\n    LEFT JOIN pembayaran_denda p ON p.id_transaksi = t.id_transaksi\n    WHERE t.id_anggota = ?\n      AND t.denda > 0\n      AND t.status_denda = 'Belum Lunas'\n    ORDER BY t.id_transaksi DESC\n");
$stmt->execute([$id_anggota]);
$daftarDenda = $stmt->fetchAll();

$totalDenda = 0;
foreach ($daftarDenda as $row) {
    $totalDenda += (float)$row['denda'];
}

$activeMenu = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pembayaran Denda - Perpustakaan Digital</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .payment-card{border:1px solid var(--border);border-radius:16px;background:#fff;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;}
  .payment-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
  .payment-book{font-weight:800;color:var(--navy);font-size:1rem;}
  .payment-meta{color:var(--muted);font-size:.78rem;margin-top:4px;}
  .payment-amount{font-size:1.15rem;font-weight:800;color:var(--navy);white-space:nowrap;}
  .method-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;}
  .method-option{display:block;border:1px solid var(--border);border-radius:12px;padding:14px;cursor:pointer;background:#fff;}
  .method-option:hover{border-color:#93c5fd;background:#f8fbff;}
  .method-option input{margin-right:8px;}
  .method-title{font-weight:800;color:var(--navy);}
  .method-desc{font-size:.72rem;color:var(--muted);margin-top:5px;line-height:1.5;}
  .qris-box{display:flex;gap:18px;align-items:center;padding:16px;border:1px solid var(--border);border-radius:14px;background:#f8fafc;margin-top:16px;}
  .qris-image{width:190px;max-width:100%;border:1px solid var(--border);border-radius:14px;background:#fff;padding:8px;}
  .qris-title{margin:0 0 8px;color:var(--navy);}
  .qris-text{margin:0;color:var(--muted);font-size:.82rem;line-height:1.6;}
  .status-proof{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:.72rem;font-weight:700;}
  .status-proof.waiting{background:#fff7ed;color:#c2410c;}
  .status-proof.rejected{background:#fef2f2;color:#b91c1c;}
  .status-proof.cash{background:#eff6ff;color:#1d4ed8;}
  .upload-box{margin-top:16px;padding:14px;border:1px dashed var(--border);border-radius:12px;background:#f8fafc;}
  .upload-box label{display:block;font-size:.78rem;font-weight:700;color:var(--navy);margin-bottom:8px;}
  .upload-help{font-size:.7rem;color:var(--muted);margin-top:6px;}
  .alert-success{padding:12px 15px;margin-bottom:18px;border-radius:10px;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;font-size:.84rem;font-weight:600;}
  .alert-error{padding:12px 15px;margin-bottom:18px;border-radius:10px;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;font-size:.84rem;font-weight:600;}
  @media(max-width:760px){.method-grid{grid-template-columns:1fr}.qris-box{display:block}.qris-image{width:220px;margin-bottom:12px}.payment-head{display:block}.payment-amount{margin-top:10px}}
</style>
</head>
<body class="admin-page">
<?php require_once '../includes/siswa_sidebar.php'; ?>
<div class="container">
  <div class="page-head">
    <div>
      <h1>Pembayaran Denda</h1>
      <p>Pilih metode pembayaran: <strong>Cash</strong> atau <strong>QRIS</strong>.</p>
    </div>
  </div>

  <?php if ($pesan === 'submitted_cash'): ?>
    <div class="alert-success">✓ Permintaan pembayaran cash berhasil dikirim. Silakan lakukan pembayaran langsung kepada petugas dan tunggu konfirmasi.</div>
  <?php elseif ($pesan === 'uploaded'): ?>
    <div class="alert-success">✓ Bukti pembayaran QRIS berhasil dikirim dan menunggu verifikasi petugas.</div>
  <?php elseif ($pesan === 'invalid'): ?>
    <div class="alert-error">✕ Data pembayaran tidak valid. Untuk QRIS, gunakan JPG, PNG, atau PDF maksimal 2 MB.</div>
  <?php elseif ($pesan === 'gagal'): ?>
    <div class="alert-error">✕ Pengajuan pembayaran gagal. Silakan coba lagi.</div>
  <?php endif; ?>

  <?php if ($daftarDenda): ?>
  <div class="payment-card">
    <div class="payment-head">
      <div>
        <div class="payment-book">Total Denda Belum Lunas</div>
        <div class="payment-meta">Seluruh denda yang masih harus dibayar.</div>
      </div>
      <div class="payment-amount">Rp<?= number_format($totalDenda,0,',','.') ?></div>
    </div>
  </div>

  <?php foreach ($daftarDenda as $d): ?>
  <div class="payment-card">
    <div class="payment-head">
      <div>
        <div class="payment-book"><?= htmlspecialchars($d['judul'] ?? 'Buku tidak ditemukan') ?><?php if (!empty($d['deleted_at'])): ?> <span class="badge badge-habis">Diarsipkan</span><?php endif; ?></div>
        <div class="payment-meta">Transaksi #<?= (int)$d['id_transaksi'] ?> · Dikembalikan: <?= htmlspecialchars($d['tanggal_kembali'] ?? '-') ?></div>
      </div>
      <div class="payment-amount">Rp<?= number_format((float)$d['denda'],0,',','.') ?></div>
    </div>

    <?php if (!empty($d['id_pembayaran'])): ?>
      <div style="margin-top:14px;">
        <span class="status-proof <?= $d['metode_pembayaran'] === 'cash' ? 'cash' : ($d['status_bukti'] === 'ditolak' ? 'rejected' : 'waiting') ?>">
          <?php if ($d['metode_pembayaran'] === 'cash'): ?>
            <?= $d['status_bukti'] === 'diterima' ? 'Cash Dikonfirmasi' : 'Menunggu Konfirmasi Cash' ?>
          <?php else: ?>
            <?= $d['status_bukti'] === 'ditolak' ? 'Bukti QRIS Ditolak' : ($d['status_bukti'] === 'diterima' ? 'QRIS Terverifikasi' : 'Bukti QRIS Menunggu Verifikasi') ?>
          <?php endif; ?>
        </span>
        <?php if (!empty($d['catatan'])): ?>
          <div class="payment-meta" style="margin-top:8px;">Catatan petugas: <?= htmlspecialchars($d['catatan']) ?></div>
        <?php endif; ?>
        <?php if ($d['metode_pembayaran'] === 'qris' && !empty($d['file_bukti'])): ?>
          <div style="margin-top:10px;"><a href="../uploads/bukti_pembayaran/<?= rawurlencode(basename($d['file_bukti'])) ?>" target="_blank" rel="noopener" class="btn btn-outline">Lihat Bukti QRIS</a></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php
      $bolehAjukan = empty($d['id_pembayaran']) || $d['status_bukti'] === 'ditolak';
    ?>
    <?php if ($bolehAjukan): ?>
    <form method="POST" action="proses_pembayaran_denda.php" enctype="multipart/form-data" class="upload-box" id="form_<?= (int)$d['id_transaksi'] ?>">
      <?= csrfField() ?>
      <input type="hidden" name="id_transaksi" value="<?= (int)$d['id_transaksi'] ?>">

      <label>Pilih Metode Pembayaran</label>
      <div class="method-grid">
        <label class="method-option">
          <input type="radio" name="metode_pembayaran" value="cash" required onchange="togglePayment(<?= (int)$d['id_transaksi'] ?>)">
          <span class="method-title">Cash</span>
          <div class="method-desc">Bayar langsung kepada petugas. Tidak perlu upload bukti.</div>
        </label>
        <label class="method-option">
          <input type="radio" name="metode_pembayaran" value="qris" required onchange="togglePayment(<?= (int)$d['id_transaksi'] ?>)">
          <span class="method-title">QRIS</span>
          <div class="method-desc">Scan QRIS, lalu upload bukti pembayaran untuk diverifikasi.</div>
        </label>
      </div>

      <div class="qris-box" id="qris_box_<?= (int)$d['id_transaksi'] ?>" style="display:none;">
        <img src="../assets/qris_pembayaran.jpeg" class="qris-image" alt="QRIS pembayaran perpustakaan">
        <div>
          <h3 class="qris-title">Scan QRIS</h3>
          <p class="qris-text">Bayar sebesar <strong>Rp<?= number_format((float)$d['denda'],0,',','.') ?></strong>, lalu simpan bukti transaksi dan upload pada form di bawah.</p>
        </div>
      </div>

      <div id="bukti_box_<?= (int)$d['id_transaksi'] ?>" style="display:none;margin-top:16px;">
        <label for="bukti_<?= (int)$d['id_transaksi'] ?>">Upload Bukti Pembayaran QRIS</label>
        <input id="bukti_<?= (int)$d['id_transaksi'] ?>" type="file" name="bukti" accept="image/jpeg,image/png,application/pdf">
        <div class="upload-help">Format: JPG/PNG/PDF · maksimal 2 MB.</div>
      </div>

      <button type="submit" class="btn" style="margin-top:12px;">Kirim Pilihan Pembayaran</button>
    </form>
    <?php elseif ($d['metode_pembayaran'] === 'cash'): ?>
      <div class="payment-meta" style="margin-top:10px;">Pembayaran cash sudah diajukan. Silakan serahkan uang kepada petugas untuk diverifikasi.</div>
    <?php else: ?>
      <div class="payment-meta" style="margin-top:10px;">Bukti QRIS sudah dikirim. Tunggu petugas memverifikasi pembayaran.</div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php else: ?>
    <div class="card" style="text-align:center; padding:40px 20px; color:var(--muted);">Tidak ada denda yang belum lunas.</div>
  <?php endif; ?>
</div>
<script>
function togglePayment(id) {
  const form = document.getElementById('form_' + id);
  if (!form) return;
  const method = form.querySelector('input[name="metode_pembayaran"]:checked')?.value || '';
  const qrisBox = document.getElementById('qris_box_' + id);
  const buktiBox = document.getElementById('bukti_box_' + id);
  const bukti = document.getElementById('bukti_' + id);
  const isQris = method === 'qris';
  qrisBox.style.display = isQris ? 'flex' : 'none';
  buktiBox.style.display = isQris ? 'block' : 'none';
  if (bukti) bukti.required = isQris;
}
</script>
</body>
</html>
