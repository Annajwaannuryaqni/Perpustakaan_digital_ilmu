<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$id_anggota = $_SESSION['anggota_id'];
$id_transaksi = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_transaksi) {
    header('Location: dashboard.php');
    exit;
}

// Siswa hanya boleh melihat bukti peminjaman miliknya sendiri
// (id_anggota diambil dari sesi, bukan dari input) — mencegah siswa lain
// mengintip bukti peminjaman orang lain hanya dengan mengganti angka di URL.
$stmt = $koneksi->prepare("
    SELECT t.*, b.judul, b.pengarang, k.nama_kategori, a.nama_lengkap, a.nis, a.kelas
    FROM transaksi t
    JOIN buku b ON b.id_buku = t.id_buku
    LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
    JOIN anggota a ON a.id_anggota = t.id_anggota
    WHERE t.id_transaksi = ? AND t.id_anggota = ?
");
$stmt->execute([$id_transaksi, $id_anggota]);
$data = $stmt->fetch();

if (!$data) {
    header('Location: dashboard.php');
    exit;
}

// Nomor transaksi yang mudah dibaca, tetap terhubung ke ID transaksi database
$nomorTransaksi = 'PJ-' . date('Ymd', strtotime($data['tanggal_pinjam'])) . '-' . str_pad($data['id_transaksi'], 4, '0', STR_PAD_LEFT);

$statusLabel = [
    'dipinjam' => 'Dipinjam',
];
$status = $statusLabel[$data['status']] ?? ucfirst($data['status']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bukti Peminjaman - <?= htmlspecialchars($nomorTransaksi) ?></title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .receipt-wrap { max-width: 480px; margin: 0 auto; }
  .success-banner {
    text-align: center; margin-bottom: 20px;
  }
  .success-banner .ic-ok {
    width: 52px; height: 52px; border-radius: 50%;
    background: rgba(22,163,74,.1); color: var(--success);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px;
  }
  .success-banner .ic-ok svg { width: 28px; height: 28px; }
  .success-banner h2 { color: var(--navy); margin-bottom: 4px; }
  .success-banner p { color: var(--muted); font-size: .88rem; margin: 0; }

  .receipt {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-card); box-shadow: var(--shadow-card);
    padding: 30px 28px;
  }
  .receipt-head { text-align: center; border-bottom: 2px dashed var(--border); padding-bottom: 16px; margin-bottom: 16px; }
  .receipt-head .lib-name { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.1rem; color: var(--navy); }
  .receipt-head .lib-sub { font-size: .78rem; color: var(--muted); margin-top: 2px; }
  .receipt-title { text-align: center; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; font-size: .82rem; color: var(--royal-600); margin-bottom: 16px; }

  .receipt-meta { display: flex; justify-content: space-between; font-size: .82rem; margin-bottom: 16px; color: var(--text); }
  .receipt-meta b { color: var(--navy); }

  .receipt-section { border-top: 1px dashed var(--border); padding-top: 14px; margin-top: 14px; }
  .receipt-section h4 {
    font-size: .72rem; text-transform: uppercase; letter-spacing: .05em;
    color: var(--muted); margin: 0 0 10px; font-weight: 700;
  }
  .receipt-row { display: flex; justify-content: space-between; gap: 12px; font-size: .86rem; padding: 3px 0; }
  .receipt-row span:first-child { color: var(--muted); }
  .receipt-row span:last-child { color: var(--navy); font-weight: 600; text-align: right; }

  .receipt-info {
    border-top: 1px dashed var(--border); margin-top: 16px; padding-top: 14px;
    font-size: .78rem; color: var(--muted); line-height: 1.6; text-align: center;
  }
  .receipt-footer { text-align: center; font-size: .78rem; color: var(--muted); margin-top: 14px; }

  .receipt-actions { display: flex; gap: 12px; margin-top: 24px; }
  .receipt-actions a, .receipt-actions button { flex: 1; }
  .receipt-actions .btn, .receipt-actions .btn-outline { width: 100%; }

  @media print {
    body * { visibility: hidden; }
    .receipt, .receipt * { visibility: visible; }
    .receipt { position: absolute; top: 0; left: 0; width: 100%; border: none; box-shadow: none; margin: 0; padding: 0; }
    .no-print, .success-banner, .receipt-actions { display: none !important; }
    .container { padding: 0 !important; }
    @page { size: A4; margin: 20mm; }
  }
</style>
</head>
<body>
  <div class="container receipt-wrap">
    <div class="success-banner">
      <div class="ic-ok">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><polyline points="8 12.5 11 15.5 16 9"/></svg>
      </div>
      <h2>Peminjaman Berhasil</h2>
      <p>Berikut bukti peminjaman kamu. Simpan atau cetak untuk arsip.</p>
    </div>

    <div class="receipt">
      <div class="receipt-head">
        <div class="lib-name">Perpustakaan Digital Sekolah</div>
        <div class="lib-sub">Bukti Peminjaman Buku</div>
      </div>

      <div class="receipt-title">Bukti Peminjaman</div>

      <div class="receipt-meta">
        <span>No. Transaksi<br><b><?= htmlspecialchars($nomorTransaksi) ?></b></span>
        <span style="text-align:right;">Tanggal<br><b><?= htmlspecialchars(date('d-m-Y', strtotime($data['tanggal_pinjam']))) ?></b></span>
      </div>

      <div class="receipt-section">
        <h4>Data Peminjam</h4>
        <div class="receipt-row"><span>Nama</span><span><?= htmlspecialchars($data['nama_lengkap']) ?></span></div>
        <div class="receipt-row"><span>ID Anggota</span><span><?= htmlspecialchars($data['nis']) ?></span></div>
        <div class="receipt-row"><span>Kelas</span><span><?= htmlspecialchars($data['kelas'] ?: '-') ?></span></div>
      </div>

      <div class="receipt-section">
        <h4>Data Buku</h4>
        <div class="receipt-row"><span>Judul</span><span><?= htmlspecialchars($data['judul']) ?></span></div>
        <div class="receipt-row"><span>Penulis</span><span><?= htmlspecialchars($data['pengarang']) ?></span></div>
        <div class="receipt-row"><span>Kategori</span><span><?= htmlspecialchars($data['nama_kategori'] ?? '-') ?></span></div>
      </div>

      <div class="receipt-section">
        <h4>Detail Peminjaman</h4>
        <div class="receipt-row"><span>Tanggal Pinjam</span><span><?= htmlspecialchars(date('d-m-Y', strtotime($data['tanggal_pinjam']))) ?></span></div>
        <div class="receipt-row"><span>Jatuh Tempo</span><span><?= htmlspecialchars(date('d-m-Y', strtotime($data['tanggal_jatuh_tempo']))) ?></span></div>
        <div class="receipt-row"><span>Status</span><span><?= htmlspecialchars($status) ?></span></div>
      </div>

      <div class="receipt-info">
        Harap mengembalikan buku sesuai tanggal jatuh tempo.<br>
        Denda keterlambatan mengikuti ketentuan perpustakaan.
      </div>

      <div class="receipt-footer">Perpustakaan Digital</div>
    </div>

    <div class="receipt-actions no-print">
      <button type="button" class="btn" onclick="window.print()">Cetak Bukti Peminjaman</button>
      <a href="dashboard.php" class="btn-outline btn">Kembali ke Dashboard</a>
    </div>
    <a href="kembali.php" class="back-link no-print" style="display:block; margin-top:16px; text-align:center;">Lihat Riwayat Peminjaman &rarr;</a>
  </div>
</body>
</html>
