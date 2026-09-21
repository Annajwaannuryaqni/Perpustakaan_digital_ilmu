<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';
require_once '../config/constants.php'; // TARIF_DENDA_PER_HARI — satu sumber tarif denda untuk seluruh aplikasi

// Tanggal acuan keterlambatan:
//  - 'menunggu_konfirmasi' -> tanggal siswa mengajukan pengembalian (sama dengan proses_kembali.php)
//  - 'dipinjam'            -> hari ini (buku masih di tangan siswa)
// COALESCE ke CURDATE() hanya untuk transaksi lama yang kolom pengajuannya masih NULL.
$daftarTerlambat = $koneksi->query("
    SELECT t.*, a.nama_lengkap AS nama_anggota, a.nis, a.kelas, b.judul, b.deleted_at,
           DATEDIFF(
               CASE WHEN t.status = 'menunggu_konfirmasi'
                    THEN COALESCE(t.tanggal_pengajuan_kembali, CURDATE())
                    ELSE CURDATE() END,
               t.tanggal_jatuh_tempo
           ) AS hari_terlambat
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    LEFT JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.status IN ('dipinjam','menunggu_konfirmasi')
    HAVING hari_terlambat > 0
    ORDER BY t.tanggal_jatuh_tempo ASC
")->fetchAll();

$activeMenu = 'terlambat';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buku Terlambat - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Buku Terlambat</h1>
        <p>Total <?= count($daftarTerlambat) ?> transaksi peminjaman yang melewati jatuh tempo.</p>
      </div>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Anggota</th>
            <th>Kelas</th>
            <th>Judul Buku</th>
            <th>Jatuh Tempo</th>
            <th>Hari Terlambat</th>
            <th>Estimasi Denda</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarTerlambat as $d):
            $denda = min((int)$d['hari_terlambat'] * TARIF_DENDA_PER_HARI, TARIF_DENDA_MAKSIMUM);
          ?>
          <tr>
            <td data-label="Anggota" style="font-weight:600;"><?= htmlspecialchars($d['nama_anggota']) ?> <br><small style="color:var(--muted); font-weight:400;">NIS <?= htmlspecialchars($d['nis']) ?></small></td>
            <td data-label="Kelas"><?= htmlspecialchars($d['kelas']) ?></td>
            <td data-label="Judul Buku"><?= htmlspecialchars($d['judul'] ?? 'Buku tidak ditemukan') ?><?php if (!empty($d['deleted_at'])): ?> <span class="badge badge-habis">Diarsipkan</span><?php endif; ?></td>
            <td data-label="Jatuh Tempo"><?= $d['tanggal_jatuh_tempo'] ?></td>
            <td data-label="Hari Terlambat"><span class="badge badge-habis"><?= (int)$d['hari_terlambat'] ?> hari</span></td>
            <td data-label="Estimasi Denda">Rp<?= number_format($denda, 0, ',', '.') ?></td>
            <td data-label="Aksi">
              <?php if ($d['status'] === 'menunggu_konfirmasi'): ?>
              <form method="POST" action="proses_kembali.php" onsubmit="return confirm('Konfirmasi pengembalian buku ini? Denda: Rp<?= number_format($denda, 0, ',', '.') ?>')" style="margin:0;">
                <?= csrfField() ?>
                <input type="hidden" name="id_transaksi" value="<?= $d['id_transaksi'] ?>">
                <button type="submit" class="btn">Konfirmasi</button>
              </form>
              <?php else: ?>
              <span style="color:var(--text-muted); font-size:.78rem;">Menunggu pengajuan siswa</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarTerlambat): ?>
          <tr><td colspan="7" style="text-align:center;">Tidak ada buku yang terlambat.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </main>
</body>
</html>