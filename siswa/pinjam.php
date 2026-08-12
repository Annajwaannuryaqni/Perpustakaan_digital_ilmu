<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$pesan = $_GET['pesan'] ?? '';
$keyword = trim($_GET['q'] ?? '');

if ($keyword !== '') {
    $stmt = $koneksi->prepare("
        SELECT b.*, k.nama_kategori
        FROM buku b
        LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
        WHERE b.stok > 0
          AND (b.judul LIKE :kw OR b.pengarang LIKE :kw OR k.nama_kategori LIKE :kw)
        ORDER BY k.nama_kategori ASC, b.judul ASC
    ");
    $stmt->execute(['kw' => '%' . $keyword . '%']);
    $daftarBuku = $stmt->fetchAll();
} else {
    $daftarBuku = $koneksi->query("
        SELECT b.*, k.nama_kategori
        FROM buku b
        LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
        WHERE b.stok > 0
        ORDER BY k.nama_kategori ASC, b.judul ASC
    ")->fetchAll();
}

// Kelompokkan buku per genre untuk ditampilkan sebagai seksi terpisah
$bukuPerGenre = [];
foreach ($daftarBuku as $b) {
    $genre = $b['nama_kategori'] ?: 'Lainnya';
    $bukuPerGenre[$genre][] = $b;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peminjaman Buku</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-page">
  <?php $activeMenu = 'pinjam'; require '../includes/siswa_sidebar.php'; ?>
  <div class="topbar">
    <h1>Katalog &amp; Peminjaman Buku</h1>
  </div>

  <div class="container">
    <?php if ($pesan === 'sukses'): ?>
      <p class="alert alert-sukses">Peminjaman berhasil! Jangan lupa kembalikan tepat waktu.</p>
    <?php elseif ($pesan === 'gagal'): ?>
      <p class="alert alert-gagal">Peminjaman gagal, stok buku mungkin sudah habis.</p>
    <?php endif; ?>

    <div class="section-header">
      <h1>Katalog Buku</h1>
      <p>Temukan dan pinjam buku yang kamu butuhkan.</p>
    </div>

    <div class="card">
      <form method="GET" action="pinjam.php" class="search-hero">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" placeholder="Cari judul, pengarang, atau kategori..."
               value="<?= htmlspecialchars($keyword) ?>">
        <div class="search-hero-actions">
          <?php if ($keyword !== ''): ?>
            <a href="pinjam.php" class="btn-reset">Reset</a>
          <?php endif; ?>
          <button type="submit" class="btn">Cari</button>
        </div>
      </form>

      <?php if ($bukuPerGenre): ?>
      <div class="category-chips">
        <span class="category-chip active">
          Semua Genre <span class="count">(<?= count($daftarBuku) ?> buku)</span>
        </span>
        <?php foreach ($bukuPerGenre as $genre => $daftar): ?>
          <a href="#genre-<?= md5($genre) ?>" class="category-chip">
            <?= htmlspecialchars($genre) ?> <span class="count">(<?= count($daftar) ?>)</span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!$daftarBuku): ?>
        <div class="empty-state">
          <div class="empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </div>
          <strong>Buku tidak ditemukan</strong>
          <span><?= $keyword !== ''
              ? 'Tidak ada buku yang cocok dengan "' . htmlspecialchars($keyword) . '"'
              : 'Tidak ada buku yang tersedia saat ini' ?></span>
        </div>
      <?php else: ?>
        <?php foreach ($bukuPerGenre as $genre => $daftar): ?>
          <div class="genre-section" id="genre-<?= md5($genre) ?>">
            <div class="genre-title">
              <?= htmlspecialchars($genre) ?>
              <span class="count"><?= count($daftar) ?> buku</span>
            </div>

            <div class="book-grid">
              <?php foreach ($daftar as $b): ?>
                <div class="book-card" onclick="bukaModal('modal-<?= $b['id_buku'] ?>')">
                  <div class="cover-wrap">
                    <?php if ($b['cover']): ?>
                      <img src="../uploads/<?= htmlspecialchars($b['cover']) ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
                    <?php else: ?>
                      <span class="no-cover">(belum ada cover)</span>
                    <?php endif; ?>
                  </div>
                  <div class="info">
                    <div class="judul"><?= htmlspecialchars($b['judul']) ?></div>
                    <div class="pengarang"><?= htmlspecialchars($b['pengarang']) ?></div>
                    <div class="baris-bawah">
                      <span class="badge badge-ok"><?= (int)$b['stok'] ?> stok</span>
                      <a href="pinjam_konfirmasi.php?id=<?= $b['id_buku'] ?>" class="btn" onclick="event.stopPropagation()">Pinjam</a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== MODAL DETAIL (satu per buku) ===== -->
  <?php foreach ($daftarBuku as $b): ?>
    <div class="modal-overlay" id="modal-<?= $b['id_buku'] ?>">
      <div class="modal-book">
        <button class="close-btn" onclick="tutupModal('modal-<?= $b['id_buku'] ?>')" aria-label="Tutup">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <div class="cover-wrap">
          <?php if ($b['cover']): ?>
            <img class="modal-cover" src="../uploads/<?= htmlspecialchars($b['cover']) ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
          <?php else: ?>
            <span class="no-cover">(belum ada cover)</span>
          <?php endif; ?>
        </div>

        <div class="modal-body">
          <h2><?= htmlspecialchars($b['judul']) ?></h2>
          <div class="modal-pengarang">oleh <?= htmlspecialchars($b['pengarang']) ?></div>

          <div class="meta-grid">
            <div><span>Genre</span><strong><?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></strong></div>
            <div><span>Kode Buku</span><strong><?= htmlspecialchars($b['kode_buku'] ?? '-') ?></strong></div>
            <div><span>Penerbit</span><strong><?= htmlspecialchars($b['penerbit'] ?? '-') ?></strong></div>
            <div><span>Tahun Terbit</span><strong><?= htmlspecialchars($b['tahun_terbit'] ?? '-') ?></strong></div>
            <div><span>Lokasi Rak</span><strong><?= htmlspecialchars($b['lokasi_rak'] ?? '-') ?></strong></div>
            <div><span>Stok</span><strong><?= (int)$b['stok'] ?> tersedia</strong></div>
          </div>

          <div class="deskripsi"><?= nl2br(htmlspecialchars($b['deskripsi'] ?? '-')) ?></div>

          <div class="modal-footer">
            <?php if ((int)$b['stok'] > 0): ?>
              <a href="pinjam_konfirmasi.php?id=<?= $b['id_buku'] ?>" class="btn">Pinjam Buku Ini</a>
            <?php else: ?>
              <span class="badge badge-habis">Tidak Tersedia</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <script>
  function bukaModal(id){
    document.getElementById(id).classList.add('show');
    document.body.style.overflow='hidden';
  }
  function tutupModal(id){
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow='';
  }
  document.querySelectorAll('.modal-overlay').forEach(function(ov){
    ov.addEventListener('click', function(e){
      if(e.target === ov){
        ov.classList.remove('show');
        document.body.style.overflow='';
      }
    });
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
      document.querySelectorAll('.modal-overlay.show').forEach(function(ov){
        ov.classList.remove('show');
      });
      document.body.style.overflow='';
    }
  });
  </script>
</main>
</body>
</html>