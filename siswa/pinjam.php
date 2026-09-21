<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$pesan = $_GET['pesan'] ?? '';
$keyword = trim($_GET['q'] ?? '');

if ($keyword !== '') {
    $stmt = $koneksi->prepare("SELECT b.*,k.nama_kategori FROM buku b LEFT JOIN kategori k ON k.id_kategori=b.id_kategori WHERE b.stok>0 AND (b.judul LIKE :kw OR b.pengarang LIKE :kw OR k.nama_kategori LIKE :kw) ORDER BY k.nama_kategori ASC,b.judul ASC");
    $stmt->execute(['kw'=>'%'.$keyword.'%']);
    $daftarBuku = $stmt->fetchAll();
} else {
    $daftarBuku = $koneksi->query("SELECT b.*,k.nama_kategori FROM buku b LEFT JOIN kategori k ON k.id_kategori=b.id_kategori WHERE b.stok>0 ORDER BY k.nama_kategori ASC,b.judul ASC")->fetchAll();
}

$bukuPerGenre = [];
foreach ($daftarBuku as $b) {
    $genre = $b['nama_kategori'] ?: 'Lainnya';
    $bukuPerGenre[$genre][] = $b;
}
$totalBuku = count($daftarBuku);
$totalGenre = count($bukuPerGenre);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Katalog & Peminjaman Buku</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.pinjam-topbar{min-height:86px!important;padding:0 38px 0 210px!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:20px}
.topbar-info{display:flex;flex-direction:column;gap:3px}
.topbar-brand{font-size:17px;font-weight:800;color:#fff;letter-spacing:-.2px}
.topbar-page{font-size:13px;color:rgba(255,255,255,.72)}
.pinjam-page .container{max-width:1180px;margin:26px auto 50px;padding:0 18px}
.pinjam-page .catalog-card{background:#fff;border:1px solid #dfe7f1;border-radius:20px;padding:28px 30px;box-shadow:0 8px 25px rgba(31,55,90,.06)}
.pinjam-page .catalog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:22px}
.pinjam-page .catalog-head h1{margin:0;font-size:25px;color:#10213d;font-weight:800}
.pinjam-page .catalog-head p{margin:8px 0 0;color:#66809f;font-size:15px}
.pinjam-page .catalog-stats{display:flex;gap:10px}
.pinjam-page .catalog-stat{min-width:92px;padding:11px 15px;border:1px solid #dce5ef;border-radius:12px;text-align:center;background:#fbfdff}
.pinjam-page .catalog-stat strong{display:block;font-size:20px;color:#0878bd}
.pinjam-page .catalog-stat span{display:block;font-size:11px;color:#7890ad;margin-top:2px}
.pinjam-page .search-box{height:56px;border:1px solid #d2ddea;border-radius:13px;display:flex;align-items:center;padding:0 7px 0 17px;background:#fff}
.pinjam-page .search-box svg{width:20px;height:20px;color:#91a5bf;flex:none}
.pinjam-page .search-box input{flex:1;border:0;outline:0;background:transparent;padding:0 14px;font-size:14px;color:#243954;min-width:0}
.pinjam-page .search-box button{height:42px;padding:0 22px;border:0;border-radius:10px;background:#2864df;color:#fff;font-weight:700;font-size:14px;cursor:pointer}
.pinjam-page .search-box button:hover{background:#1954cb}
.pinjam-page .category-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.pinjam-page .category-chip{display:inline-flex;align-items:center;gap:3px;text-decoration:none;padding:9px 13px;border:1px solid #dce5ef;border-radius:10px;background:#fff;color:#4d6684;font-size:12px;font-weight:600}
.pinjam-page .category-chip:hover{border-color:#9fc9ee;background:#f5faff;color:#0878bd}
.pinjam-page .category-chip.active{background:#eef7ff;border-color:#abd4f5;color:#0878bd}
.pinjam-page .count{font-size:11px;color:#86a0bd}
.pinjam-page .available-head{display:flex;justify-content:space-between;align-items:center;margin:28px 0 25px}
.pinjam-page .available-head h2{font-size:18px;color:#12233e;margin:0;font-weight:800}
.pinjam-page .available-head span{font-size:12px;color:#8298b2}
.pinjam-page .genre-section{margin-bottom:34px}
.pinjam-page .genre-title{display:flex;align-items:center;gap:10px;font-size:17px;font-weight:800;color:#14233d;margin-bottom:15px;padding-left:12px;border-left:4px solid #159bd3;line-height:24px}
.pinjam-page .genre-title .count{background:#edf5fc;color:#7a97b6;padding:4px 9px;border-radius:20px;font-size:11px;font-weight:600}
.pinjam-page .book-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
.pinjam-page .book-card{background:#fff;border:1px solid #dce5ef;border-radius:16px;overflow:hidden;cursor:pointer;transition:.2s ease;display:flex;flex-direction:column;min-width:0;box-shadow:0 4px 13px rgba(31,55,90,.04)}
.pinjam-page .book-card:hover{transform:translateY(-3px);box-shadow:0 12px 25px rgba(31,55,90,.10);border-color:#cbd9e8}
.pinjam-page .book-cover{height:315px;background:#f3f6fa;display:flex;align-items:center;justify-content:center;padding:12px;border-bottom:1px solid #edf1f5}
.pinjam-page .book-cover img{width:100%;height:100%;object-fit:contain;display:block}
.pinjam-page .no-cover{color:#9aabc0;font-size:12px;text-align:center}
.pinjam-page .book-info{padding:14px 14px 13px;display:flex;flex-direction:column;flex:1}
.pinjam-page .book-title{font-size:14px;font-weight:800;color:#172944;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:38px}
.pinjam-page .book-author{font-size:12px;color:#6f88a5;margin-top:9px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pinjam-page .book-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:15px}
.pinjam-page .stock-badge{display:inline-flex;align-items:center;gap:5px;background:#effbf6;color:#087f61;border-radius:8px;padding:8px 9px;font-size:11px;font-weight:700;white-space:nowrap}
.pinjam-page .stock-dot{width:5px;height:5px;border-radius:50%;background:#16b886}
.pinjam-page .borrow-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;background:#2864df;color:#fff;border-radius:9px;padding:9px 14px;font-size:12px;font-weight:700;white-space:nowrap}
.pinjam-page .borrow-btn:hover{background:#1954cb}
.pinjam-page .empty-state{padding:55px 20px;text-align:center;color:#7890ad}
.pinjam-page .empty-state strong{display:block;color:#243954;margin-bottom:6px}
.pinjam-page .empty-state span{font-size:13px}
.pinjam-page .modal-overlay{display:none;position:fixed;inset:0;background:rgba(11,24,45,.58);z-index:9999;align-items:center;justify-content:center;padding:20px}
.pinjam-page .modal-overlay.show{display:flex}
.pinjam-page .modal-book{position:relative;background:#fff;width:min(760px,100%);max-height:90vh;overflow:auto;border-radius:20px;display:grid;grid-template-columns:260px 1fr;box-shadow:0 25px 70px rgba(0,0,0,.2)}
.pinjam-page .modal-cover-wrap{background:#f3f6fa;display:flex;align-items:center;justify-content:center;padding:25px}
.pinjam-page .modal-cover{width:100%;height:390px;object-fit:contain}
.pinjam-page .modal-body{padding:30px}
.pinjam-page .modal-body h2{margin:0;color:#152743;font-size:23px}
.pinjam-page .modal-author{color:#7189a5;font-size:13px;margin-top:7px}
.pinjam-page .meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:22px}
.pinjam-page .meta-grid div{background:#f7f9fc;border-radius:10px;padding:10px 12px}
.pinjam-page .meta-grid span{display:block;font-size:10px;color:#8ba0b8;margin-bottom:4px}
.pinjam-page .meta-grid strong{font-size:12px;color:#263b58}
.pinjam-page .description{margin-top:18px;font-size:13px;line-height:1.6;color:#617995}
.pinjam-page .modal-footer{margin-top:22px}
.pinjam-page .close-btn{position:absolute;right:15px;top:15px;width:34px;height:34px;border:0;border-radius:50%;background:#fff;box-shadow:0 3px 12px rgba(0,0,0,.12);cursor:pointer;z-index:2}
.pinjam-page .close-btn svg{width:17px;height:17px}
.pinjam-page .alert{margin-bottom:18px}
@media(max-width:1000px){
.pinjam-page .book-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
}
@media(max-width:760px){
.pinjam-topbar{min-height:76px!important;padding:0 16px 0 88px!important}
.topbar-brand{font-size:14px}
.topbar-page{font-size:11px}
.pinjam-page .container{margin:18px auto 35px;padding:0 12px}
.pinjam-page .catalog-card{padding:20px 16px;border-radius:16px}
.pinjam-page .catalog-head{display:block}
.pinjam-page .catalog-head h1{font-size:21px}
.pinjam-page .catalog-head p{font-size:13px;line-height:1.5}
.pinjam-page .catalog-stats{margin-top:16px}
.pinjam-page .catalog-stat{flex:1;min-width:0;padding:9px}
.pinjam-page .catalog-stat strong{font-size:18px}
.pinjam-page .catalog-stat span{font-size:10px}
.pinjam-page .search-box{height:50px}
.pinjam-page .search-box button{height:38px;padding:0 15px}
.pinjam-page .available-head{margin:23px 0 18px}
.pinjam-page .available-head h2{font-size:16px}
.pinjam-page .book-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.pinjam-page .book-cover{height:235px;padding:9px}
.pinjam-page .book-info{padding:11px 10px}
.pinjam-page .book-title{font-size:12px;min-height:32px}
.pinjam-page .book-author{font-size:10px;margin-top:6px}
.pinjam-page .book-bottom{margin-top:10px;gap:5px}
.pinjam-page .stock-badge{font-size:9px;padding:7px 6px}
.pinjam-page .borrow-btn{font-size:10px;padding:8px 9px}
.pinjam-page .modal-book{grid-template-columns:1fr;max-height:92vh}
.pinjam-page .modal-cover-wrap{padding:20px}
.pinjam-page .modal-cover{height:280px}
.pinjam-page .modal-body{padding:20px}
}
@media(max-width:400px){
.pinjam-page .book-cover{height:205px}
.pinjam-page .book-bottom{align-items:stretch;flex-direction:column}
.pinjam-page .borrow-btn{width:100%}
.pinjam-page .stock-badge{justify-content:center}
}
</style>
</head>
<body class="admin-page pinjam-page">
<?php $activeMenu='pinjam';require '../includes/siswa_sidebar.php'; ?>

<div class="topbar pinjam-topbar">
<div class="topbar-info">
<span class="topbar-brand">Perpustakaan Digital Ilmu</span>
<span class="topbar-page">Katalog & Peminjaman Buku</span>
</div>
</div>

<div class="container">
<?php if($pesan==='sukses'): ?>
<p class="alert alert-sukses">Peminjaman berhasil! Jangan lupa kembalikan tepat waktu.</p>
<?php elseif($pesan==='gagal'): ?>
<p class="alert alert-gagal">Peminjaman gagal, stok buku mungkin sudah habis.</p>
<?php elseif($pesan==='gagal_duplikat'): ?>
<p class="alert alert-gagal">Kamu masih memiliki pinjaman aktif untuk buku ini. Kembalikan dulu sebelum meminjam lagi.</p>
<?php elseif($pesan==='ada_denda'): ?>
<p class="alert alert-gagal">Kamu masih punya denda yang belum lunas. Selesaikan pembayaran denda ke petugas sebelum meminjam buku baru.</p>
<?php elseif($pesan==='tutup'): ?>
<p class="alert alert-gagal">Perpustakaan sedang tutup. Peminjaman hanya bisa dilakukan Senin-Kamis 07:30-15:30 dan Jumat 07:30-15:00.</p>
<?php endif; ?>

<div class="catalog-card">
<div class="catalog-head">
<div>
<h1>Katalog Buku</h1>
<p>Temukan buku yang kamu butuhkan dan ajukan peminjaman dengan mudah.</p>
</div>
<div class="catalog-stats">
<div class="catalog-stat"><strong><?= $totalBuku ?></strong><span>Buku Tersedia</span></div>
<div class="catalog-stat"><strong><?= $totalGenre ?></strong><span>Genre</span></div>
</div>
</div>

<form method="GET" action="pinjam.php" class="search-box">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
<input type="text" name="q" placeholder="Cari judul, pengarang, atau kategori..." value="<?= htmlspecialchars($keyword) ?>">
<button type="submit">Cari</button>
</form>

<?php if($bukuPerGenre): ?>
<div class="category-chips">
<a href="pinjam.php" class="category-chip active">Semua Buku <span class="count">(<?= $totalBuku ?>)</span></a>
<?php foreach($bukuPerGenre as $genre=>$daftar): ?>
<a href="#genre-<?= md5($genre) ?>" class="category-chip"><?= htmlspecialchars($genre) ?> <span class="count">(<?= count($daftar) ?>)</span></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<div class="available-head">
<h2>Buku Tersedia</h2>
<span><?= $totalBuku ?> buku ditemukan</span>
</div>

<?php if(!$daftarBuku): ?>
<div class="catalog-card">
<div class="empty-state">
<strong>Buku tidak ditemukan</strong>
<span><?= $keyword!==''?'Tidak ada buku yang cocok dengan "'.htmlspecialchars($keyword).'"':'Tidak ada buku yang tersedia saat ini' ?></span>
</div>
</div>
<?php else: ?>

<?php foreach($bukuPerGenre as $genre=>$daftar): ?>
<div class="genre-section" id="genre-<?= md5($genre) ?>">
<div class="genre-title"><?= htmlspecialchars($genre) ?><span class="count"><?= count($daftar) ?> buku</span></div>
<div class="book-grid">

<?php foreach($daftar as $b): ?>
<div class="book-card" onclick="bukaModal('modal-<?= $b['id_buku'] ?>')">
<div class="book-cover">
<?php if($b['cover']): ?>
<img src="../uploads/<?= htmlspecialchars($b['cover']) ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
<?php else: ?>
<span class="no-cover">Belum ada cover</span>
<?php endif; ?>
</div>
<div class="book-info">
<div class="book-title"><?= htmlspecialchars($b['judul']) ?></div>
<div class="book-author"><?= htmlspecialchars($b['pengarang']) ?></div>
<div class="book-bottom">
<span class="stock-badge"><i class="stock-dot"></i><?= (int)$b['stok'] ?> stok</span>
<a href="pinjam_konfirmasi.php?id=<?= $b['id_buku'] ?>" class="borrow-btn" onclick="event.stopPropagation()">Pinjam</a>
</div>
</div>
</div>
<?php endforeach; ?>

</div>
</div>
<?php endforeach; ?>

<?php endif; ?>
</div>

<?php foreach($daftarBuku as $b): ?>
<div class="modal-overlay" id="modal-<?= $b['id_buku'] ?>">
<div class="modal-book">
<button class="close-btn" onclick="tutupModal('modal-<?= $b['id_buku'] ?>')" aria-label="Tutup">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
</button>

<div class="modal-cover-wrap">
<?php if($b['cover']): ?>
<img class="modal-cover" src="../uploads/<?= htmlspecialchars($b['cover']) ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
<?php else: ?>
<span class="no-cover">Belum ada cover</span>
<?php endif; ?>
</div>

<div class="modal-body">
<h2><?= htmlspecialchars($b['judul']) ?></h2>
<div class="modal-author">oleh <?= htmlspecialchars($b['pengarang']) ?></div>

<div class="meta-grid">
<div><span>Genre</span><strong><?= htmlspecialchars($b['nama_kategori']??'-') ?></strong></div>
<div><span>Kode Buku</span><strong><?= htmlspecialchars($b['kode_buku']??'-') ?></strong></div>
<div><span>Penerbit</span><strong><?= htmlspecialchars($b['penerbit']??'-') ?></strong></div>
<div><span>Tahun Terbit</span><strong><?= htmlspecialchars($b['tahun_terbit']??'-') ?></strong></div>
<div><span>Lokasi Rak</span><strong><?= htmlspecialchars($b['lokasi_rak']??'-') ?></strong></div>
<div><span>Stok</span><strong><?= (int)$b['stok'] ?> tersedia</strong></div>
</div>

<div class="description"><?= nl2br(htmlspecialchars($b['deskripsi']??'-')) ?></div>

<div class="modal-footer">
<a href="pinjam_konfirmasi.php?id=<?= $b['id_buku'] ?>" class="borrow-btn">Pinjam Buku Ini</a>
</div>
</div>
</div>
</div>
<?php endforeach; ?>

<script>
function bukaModal(id){
const el=document.getElementById(id);
if(el){el.classList.add('show');document.body.style.overflow='hidden';}
}
function tutupModal(id){
const el=document.getElementById(id);
if(el){el.classList.remove('show');document.body.style.overflow='';}
}
document.querySelectorAll('.modal-overlay').forEach(function(ov){
ov.addEventListener('click',function(e){
if(e.target===ov){ov.classList.remove('show');document.body.style.overflow='';}
});
});
document.addEventListener('keydown',function(e){
if(e.key==='Escape'){
document.querySelectorAll('.modal-overlay.show').forEach(function(ov){ov.classList.remove('show');});
document.body.style.overflow='';
}
});
</script>
</main>
</body>
</html>