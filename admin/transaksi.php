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
<body>


  <div class="container">
    <div class="print-header">
      <h2>Laporan Data Transaksi Peminjaman</h2>
      <p>Perpustakaan Digital Sekolah — dicetak <?= date('d-m-Y H:i') ?> WIB</p>
    </div>

    <div class="page-head">
      <div>
        <h1>🔄 Data Transaksi Peminjaman</h1>
        <p>Total <?= count($daftarTransaksi) ?> transaksi tercatat.</p>
      </div>
      <button onclick="window.print()" class="btn no-print" type="button">🖨️ Cetak Laporan</button>
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
</body>
</html>