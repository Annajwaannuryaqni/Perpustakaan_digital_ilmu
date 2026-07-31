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
<style>
  /* ===== Halaman peminjaman: card per genre + modal detail ===== */
  .genre-section{margin-bottom:36px;}
  .genre-title{
    display:flex;align-items:center;gap:10px;
    font-size:1.1rem;font-weight:700;color:#173a7a;
    margin:0 0 14px;
  }
  .genre-title .count{
    background:#dbeafe;color:#173a7a;
    font-size:.75rem;font-weight:700;
    padding:3px 10px;border-radius:999px;
  }
  .buku-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
    gap:18px;
  }
  .buku-item{
    background:#fff;border-radius:14px;overflow:hidden;
    box-shadow:0 2px 10px rgba(23,58,122,.08);
    display:flex;flex-direction:column;cursor:pointer;
    transition:transform .15s ease, box-shadow .15s ease;
  }
  .buku-item:hover{transform:translateY(-4px);box-shadow:0 10px 22px rgba(23,58,122,.16);}
  .buku-item .cover-wrap{
    width:100%;aspect-ratio:3/4;background:#e5edfb;
    display:flex;align-items:center;justify-content:center;
    overflow:hidden;
  }
  .buku-item .cover-wrap img{width:100%;height:100%;object-fit:cover;}
  .buku-item .cover-wrap .no-cover{font-size:.78rem;color:#7d8bab;}
  .buku-item .info{padding:12px 14px;display:flex;flex-direction:column;gap:5px;flex:1;}
  .buku-item .judul{font-weight:700;font-size:.95rem;line-height:1.3;color:#1e2a4a;}
  .buku-item .pengarang{font-size:.82rem;color:#64748b;}
  .buku-item .baris-bawah{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:8px;}
  .buku-item form{margin:0;}
  .buku-item .btn{padding:7px 12px;font-size:.82rem;}
  .empty-state{text-align:center;padding:50px 20px;color:#64748b;}

  /* modal */
  .overlay{
    display:none;position:fixed;inset:0;z-index:100;
    background:rgba(15,26,55,.55);
    align-items:center;justify-content:center;padding:20px;
  }
  .overlay.show{display:flex;}
  .modal-buku{
    background:#fff;border-radius:18px;max-width:700px;width:100%;
    max-height:88vh;min-height:340px;position:relative;
    display:flex;overflow:hidden;
  }
  .modal-buku .cover-wrap{
    flex:0 0 200px;align-self:stretch;overflow:hidden;
    height:auto;aspect-ratio:auto;
    background:#e5edfb;display:flex;align-items:center;justify-content:center;
  }
  .modal-buku .modal-cover{
    width:100%;height:100%;object-fit:contain;display:block;
  }
  .modal-buku .modal-body{padding:24px 26px;overflow-y:auto;flex:1;min-width:0;}
  .modal-buku .close-btn{
    position:absolute;top:12px;right:14px;
    background:rgba(0,0,0,.35);color:#fff;border:none;
    border-radius:50%;width:28px;height:28px;cursor:pointer;z-index:2;
  }
  .modal-buku h2{margin:0 0 4px;font-size:1.25rem;color:#1e2a4a;}
  .modal-buku .modal-pengarang{color:#64748b;margin-bottom:14px;}
  .modal-buku .meta-grid{
    display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;
    margin-bottom:14px;font-size:.86rem;
  }
  .modal-buku .meta-grid div span{display:block;color:#64748b;font-size:.72rem;}
  .modal-buku .deskripsi{font-size:.9rem;line-height:1.6;color:#1e2a4a;}
  .modal-buku .modal-footer{margin-top:18px;display:flex;justify-content:flex-end;}
  @media (max-width:600px){
    .modal-buku{flex-direction:column;max-height:92vh;}
    .modal-buku .cover-wrap{flex:0 0 200px;width:100%;}
  }
</style>
</head>
<body>
  <div class="topbar">
    <h1>📖 Peminjaman Buku</h1>
  </div>

  <div class="container">
    <?php if ($pesan === 'sukses'): ?>
      <p class="alert alert-sukses">Peminjaman berhasil! Jangan lupa kembalikan tepat waktu.</p>
    <?php elseif ($pesan === 'gagal'): ?>
      <p class="alert alert-gagal">Peminjaman gagal, stok buku mungkin sudah habis.</p>
    <?php endif; ?>

    <div class="card">
      <form method="GET" action="pinjam.php" class="search-form">
        <input type="text" name="q" placeholder="Cari judul, pengarang, atau genre..."
               value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit" class="btn">Cari</button>
        <?php if ($keyword !== ''): ?>
          <a href="pinjam.php" class="back-link">✕ Reset</a>
        <?php endif; ?>
      </form>

      <?php if (!$daftarBuku): ?>
        <div class="empty-state">
          <?= $keyword !== ''
              ? 'Tidak ada buku yang cocok dengan "' . htmlspecialchars($keyword) . '"'
              : 'Tidak ada buku yang tersedia saat ini' ?>
        </div>
      <?php else: ?>
        <?php foreach ($bukuPerGenre as $genre => $daftar): ?>
          <div class="genre-section">
            <div class="genre-title">
              <?= htmlspecialchars($genre) ?>
              <span class="count"><?= count($daftar) ?> buku</span>
            </div>

            <div class="buku-grid">
              <?php foreach ($daftar as $b): ?>
                <div class="buku-item" onclick="bukaModal('modal-<?= $b['id_buku'] ?>')">
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
                      <form method="POST" action="proses_pinjam.php" onsubmit="return confirm('Pinjam buku ini?')" onclick="event.stopPropagation()">
                        <input type="hidden" name="id_buku" value="<?= $b['id_buku'] ?>">
                        <button type="submit" class="btn">Pinjam</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
  </div>

  <!-- ===== MODAL DETAIL (satu per buku) ===== -->
  <?php foreach ($daftarBuku as $b): ?>
    <div class="overlay" id="modal-<?= $b['id_buku'] ?>">
      <div class="modal-buku">
        <button class="close-btn" onclick="tutupModal('modal-<?= $b['id_buku'] ?>')">✕</button>

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
            <div><span>Genre</span><?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></div>
            <div><span>Kode Buku</span><?= htmlspecialchars($b['kode_buku'] ?? '-') ?></div>
            <div><span>Penerbit</span><?= htmlspecialchars($b['penerbit'] ?? '-') ?></div>
            <div><span>Tahun Terbit</span><?= htmlspecialchars($b['tahun_terbit'] ?? '-') ?></div>
            <div><span>Lokasi Rak</span><?= htmlspecialchars($b['lokasi_rak'] ?? '-') ?></div>
            <div><span>Stok</span><?= (int)$b['stok'] ?> tersedia</div>
          </div>

          <div class="deskripsi"><?= nl2br(htmlspecialchars($b['deskripsi'] ?? '-')) ?></div>

          <div class="modal-footer">
            <form method="POST" action="proses_pinjam.php" onsubmit="return confirm('Pinjam buku ini?')">
              <input type="hidden" name="id_buku" value="<?= $b['id_buku'] ?>">
              <button type="submit" class="btn">Pinjam Buku Ini</button>
            </form>
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
  document.querySelectorAll('.overlay').forEach(function(ov){
    ov.addEventListener('click', function(e){
      if(e.target === ov){
        ov.classList.remove('show');
        document.body.style.overflow='';
      }
    });
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
      document.querySelectorAll('.overlay.show').forEach(function(ov){
        ov.classList.remove('show');
      });
      document.body.style.overflow='';
    }
  });
  </script>
</body>
</html>