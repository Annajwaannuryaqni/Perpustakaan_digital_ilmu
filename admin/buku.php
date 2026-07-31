<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$keyword = trim($_GET['q'] ?? '');

if ($keyword !== '') {
    $stmt = $koneksi->prepare("
        SELECT b.*, k.nama_kategori
        FROM buku b
        LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
        WHERE b.judul LIKE :kw OR b.pengarang LIKE :kw OR b.kode_buku LIKE :kw OR k.nama_kategori LIKE :kw
        ORDER BY b.judul ASC
    ");
    $stmt->execute(['kw' => '%' . $keyword . '%']);
    $daftarBuku = $stmt->fetchAll();
} else {
    $stmt = $koneksi->query("
        SELECT b.*, k.nama_kategori
        FROM buku b
        LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
        ORDER BY b.judul ASC
    ");
    $daftarBuku = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Data Buku</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>


  <div class="container">
    <div class="page-head">
      <div>
        <h1>📚 Kelola Data Buku</h1>
        <p>Total <?= count($daftarBuku) ?> buku ditampilkan<?= $keyword !== '' ? ' untuk pencarian "' . htmlspecialchars($keyword) . '"' : '' ?>.</p>
      </div>
      <a href="tambah_buku.php" class="btn">+ Tambah Buku Baru</a>
    </div>

    <div class="card">
      <form method="GET" action="buku.php" class="search-form">
        <input type="text" name="q" placeholder="Cari kode, judul, pengarang, atau genre..."
               value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit" class="btn btn-outline">🔍 Cari</button>
        <?php if ($keyword !== ''): ?>
          <a href="buku.php" class="btn-link">✕ Reset</a>
        <?php endif; ?>
      </form>

      <table>
        <thead>
          <tr>
            <th>Cover</th>
            <th>Kode</th>
            <th>Judul</th>
            <th>Pengarang</th>
            <th>Genre</th>
            <th>Stok</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarBuku as $b): ?>
          <tr>
            <td data-label="Cover">
              <?php if ($b['cover']): ?>
                <img src="../uploads/<?= htmlspecialchars($b['cover']) ?>" width="42" height="56"
                     style="object-fit:cover; border-radius:6px; box-shadow:var(--shadow-sm);">
              <?php else: ?>
                <span style="color:var(--text-muted); font-size:.75rem;">—</span>
              <?php endif; ?>
            </td>
            <td data-label="Kode"><?= htmlspecialchars($b['kode_buku']) ?></td>
            <td data-label="Judul" style="font-weight:600;"><?= htmlspecialchars($b['judul']) ?></td>
            <td data-label="Pengarang"><?= htmlspecialchars($b['pengarang']) ?></td>
            <td data-label="Genre"><?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></td>
            <td data-label="Stok">
              <?php if ($b['stok'] > 0): ?>
                <span class="badge badge-ok"><?= $b['stok'] ?> tersedia</span>
              <?php else: ?>
                <span class="badge badge-habis">Habis</span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <a href="edit_buku.php?id=<?= $b['id_buku'] ?>" class="btn-link">Edit</a> ·
              <a href="hapus_buku.php?id=<?= $b['id_buku'] ?>" class="btn-link" style="color:var(--coral);" onclick="return confirm('Yakin hapus buku ini?')">Hapus</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarBuku): ?>
          <tr><td colspan="7" style="text-align:center;">
            <?= $keyword !== '' ? 'Tidak ada buku yang cocok dengan pencarian "' . htmlspecialchars($keyword) . '"' : 'Belum ada data buku' ?>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
  </div>
</body>
</html>