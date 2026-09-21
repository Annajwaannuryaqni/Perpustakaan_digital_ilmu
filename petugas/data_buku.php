<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

$keyword = trim($_GET['q'] ?? '');
$mode = ($_GET['mode'] ?? 'aktif') === 'arsip' ? 'arsip' : 'aktif';

if ($mode === 'arsip') {
    $baseWhere = 'b.deleted_at IS NOT NULL';
} else {
    $baseWhere = 'b.deleted_at IS NULL';
}

if ($keyword !== '') {
    $stmt = $koneksi->prepare("
        SELECT b.*, k.nama_kategori
        FROM buku b
        LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
        WHERE {$baseWhere} AND (b.kode_buku LIKE :kw OR b.judul LIKE :kw OR b.pengarang LIKE :kw OR k.nama_kategori LIKE :kw)
        ORDER BY b.judul ASC
    ");
    $stmt->execute(['kw' => '%' . $keyword . '%']);
    $daftarBuku = $stmt->fetchAll();
} else {
    $daftarBuku = $koneksi->query("
        SELECT b.*, k.nama_kategori
        FROM buku b
        LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
        WHERE {$baseWhere}
        ORDER BY b.judul ASC
    ")->fetchAll();
}

$pesan = $_GET['pesan'] ?? '';
$pesanGagalHapus = '';
$pesanBerhasilHapus = '';
if ($pesan === 'berhasil_hapus') {
    $pesanBerhasilHapus = 'Buku diarsipkan. Riwayat peminjaman tetap tersimpan.';
}
if ($pesan === 'gagal_terpakai') {
    $pesanGagalHapus = 'Buku ini tidak bisa dihapus karena masih memiliki riwayat transaksi peminjaman yang tercatat.';
} elseif ($pesan === 'gagal_lain') {
    $pesanGagalHapus = 'Terjadi kesalahan saat menghapus data buku. Silakan coba lagi.';
}

$activeMenu = 'data_buku';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Buku - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .buku-thumb {
    width: 40px; height: 54px; border-radius: 6px; overflow: hidden;
    background: var(--slate-100); display: flex; align-items: center;
    justify-content: center; flex-shrink: 0;
  }
  .buku-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .buku-thumb .no-cover-ic { color: var(--muted); }
  .buku-thumb .no-cover-ic svg { width: 18px; height: 18px; }
  .buku-title-cell { display: flex; align-items: center; gap: 10px; }
  .aksi-cell { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Data Buku</h1>
        <p>Total <?= count($daftarBuku) ?> judul buku ditampilkan<?= $keyword !== '' ? ' untuk pencarian "' . htmlspecialchars($keyword) . '"' : '' ?>.</p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
        <a href="data_buku.php?mode=aktif" class="btn">Buku Aktif</a>
        <a href="data_buku.php?mode=arsip" class="btn">Buku Diarsipkan</a>
        <?php if ($mode === 'aktif'): ?><a href="tambah_buku.php" class="btn">+ Tambah Buku</a><?php endif; ?>
      </div>
    </div>

    <?php if ($pesanBerhasilHapus): ?>
      <p class="alert alert-sukses"><?= htmlspecialchars($pesanBerhasilHapus) ?></p>
    <?php endif; ?>

    <?php if ($pesanGagalHapus): ?>
      <p class="alert alert-gagal"><?= htmlspecialchars($pesanGagalHapus) ?></p>
    <?php endif; ?>

    <div class="card">
      <form method="GET" action="data_buku.php" class="search-form">
        <input type="text" name="q" placeholder="Cari kode, judul, pengarang, atau genre..." value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit" class="btn btn-outline">Cari</button>
        <?php if ($keyword !== ''): ?><a href="data_buku.php" class="btn-link">Reset</a><?php endif; ?>
      </form>

      <table>
        <thead>
          <tr>
            <th>Kode</th>
            <th>Buku</th>
            <th>Genre</th>
            <th>Stok</th>
            <th>Kualitas</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarBuku as $b): ?>
          <tr>
            <td data-label="Kode"><?= htmlspecialchars($b['kode_buku']) ?></td>
            <td data-label="Buku">
              <div class="buku-title-cell">
                <div class="buku-thumb">
                  <?php if (!empty($b['cover'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($b['cover']) ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
                  <?php else: ?>
                    <span class="no-cover-ic">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/></svg>
                    </span>
                  <?php endif; ?>
                </div>
                <div>
                  <div style="font-weight:600;"><?= htmlspecialchars($b['judul']) ?></div>
                  <small style="color:var(--muted);">oleh <?= htmlspecialchars($b['pengarang']) ?></small>
                </div>
              </div>
            </td>
            <td data-label="Genre"><?= htmlspecialchars($b['nama_kategori'] ?? 'Lainnya') ?></td>
            <td data-label="Stok">
              <?php if ((int)$b['stok'] > 0): ?>
                <span class="badge badge-ok"><?= (int)$b['stok'] ?> tersedia</span>
              <?php else: ?>
                <span class="badge badge-habis">Habis</span>
              <?php endif; ?>
            </td>
            <td data-label="Kualitas">
              <?php if ($b['kualitas'] === 'Baik'): ?>
                <span class="badge badge-ok"><?= htmlspecialchars($b['kualitas']) ?></span>
              <?php elseif ($b['kualitas'] === 'Cukup'): ?>
                <span class="badge badge-pending"><?= htmlspecialchars($b['kualitas']) ?></span>
              <?php else: ?>
                <span class="badge badge-habis"><?= htmlspecialchars($b['kualitas']) ?></span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <div class="aksi-cell">
                <?php if ($mode === 'arsip'): ?>
                  <form method="POST" action="pulihkan_buku.php" onsubmit="return confirm('Pulihkan buku ini? Buku akan kembali menjadi buku aktif.');" style="margin:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $b['id_buku'] ?>">
                    <button type="submit" class="btn">Pulihkan Buku</button>
                  </form>
                <?php else: ?>
                  <a href="edit_buku.php?id=<?= $b['id_buku'] ?>" class="btn btn-outline">Edit</a>
                  <form method="POST" action="hapus_buku.php" onsubmit="return confirm('Arsipkan buku ini? Buku akan disembunyikan dari katalog tetapi riwayat transaksi tetap tersimpan.');" style="margin:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $b['id_buku'] ?>">
                    <button type="submit" class="btn btn-danger">Arsipkan</button>
                  </form>
                <?php endif; ?>              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarBuku): ?>
          <tr><td colspan="6" style="text-align:center;">
            <?= $keyword !== '' ? 'Tidak ada buku yang cocok dengan pencarian "' . htmlspecialchars($keyword) . '"' : 'Belum ada data buku' ?>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </main>
</body>
</html>