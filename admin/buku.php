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
<body class="admin-page">
  <button class="admin-menu-toggle" type="button" aria-label="Buka menu" onclick="document.body.classList.toggle('admin-menu-open')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="14" y2="17"/></svg></button>
  <div class="admin-sidebar-overlay" onclick="document.body.classList.remove('admin-menu-open')"></div>
  <aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
      <div class="admin-brand-mark">P</div>
      <div><strong>Perpustakaan</strong><small>Panel Admin</small></div>
    </div>
    <nav class="admin-side-nav" aria-label="Navigasi admin">
      <div class="admin-side-label">MENU UTAMA</div>
      <a href="dashboard.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="8" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="14.5" width="7" height="6" rx="1.5"/></svg></span><span>Dashboard</span></a>
      <a href="buku.php" class="admin-side-link active"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/></svg></span><span>Buku</span></a>
      <a href="anggota.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.3c2.4.3 4 2.2 4 5.2"/></svg></span><span>Anggota</span></a>
      <a href="transaksi.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/></svg></span><span>Transaksi</span></a>
      <a href="kunjungan.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/></svg></span><span>Kunjungan</span></a>
      <a href="petugas.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="3"/><path d="M4 19.5c0-3 2.2-5 5-5s5 2 5 5"/><path d="M14.5 9.2h5M17 6.7v5"/></svg></span><span>Petugas</span></a>
    </nav>
    <div class="admin-sidebar-bottom">
      <div class="admin-side-user"><span class="admin-avatar">A</span><span><strong>Admin</strong><small>Pengelola Perpustakaan</small></span></div>
      <a href="logout.php" class="admin-logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/></svg><span>Keluar</span></a>
    </div>
  </aside>
  <main class="admin-main">



  <div class="container">
    <div class="page-head">
      <div>
        <h1>Kelola Data Buku</h1>
        <p>Total <?= count($daftarBuku) ?> buku ditampilkan<?= $keyword !== '' ? ' untuk pencarian "' . htmlspecialchars($keyword) . '"' : '' ?>.</p>
      </div>
      <a href="tambah_buku.php" class="btn">+ Tambah Buku Baru</a>
    </div>

    <div class="card">
      <form method="GET" action="buku.php" class="search-form">
        <input type="text" name="q" placeholder="Cari kode, judul, pengarang, atau genre..."
               value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit" class="btn btn-outline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Cari</button>
        <?php if ($keyword !== ''): ?>
          <a href="buku.php" class="btn-link">Reset</a>
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
              <form method="POST" action="hapus_buku.php" style="display:inline;" onsubmit="return confirm('Yakin hapus buku ini?')">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $b['id_buku'] ?>">
                <button type="submit" class="btn-link" style="color:var(--coral); background:none; border:none; cursor:pointer; padding:0; font:inherit;">Hapus</button>
              </form>
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
  </div>
  </main>
</body>
</html>