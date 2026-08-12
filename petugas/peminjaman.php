<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

$pesan = $_GET['pesan'] ?? '';

$id_anggota = filter_input(INPUT_GET, 'anggota', FILTER_VALIDATE_INT);
$id_buku    = filter_input(INPUT_GET, 'buku', FILTER_VALIDATE_INT);
$q          = trim($_GET['q'] ?? '');

$anggotaTerpilih = null;
$bukuTerpilih = null;

if ($id_anggota) {
    $stmt = $koneksi->prepare("SELECT * FROM anggota WHERE id_anggota = ?");
    $stmt->execute([$id_anggota]);
    $anggotaTerpilih = $stmt->fetch();
    if (!$anggotaTerpilih) {
        header('Location: peminjaman.php');
        exit;
    }
}

if ($id_anggota && $id_buku) {
    $stmt = $koneksi->prepare("
        SELECT b.*, k.nama_kategori
        FROM buku b LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
        WHERE b.id_buku = ?
    ");
    $stmt->execute([$id_buku]);
    $bukuTerpilih = $stmt->fetch();
    if (!$bukuTerpilih) {
        header('Location: peminjaman.php?anggota=' . $id_anggota);
        exit;
    }
}

// ---- Tahap 1: cari anggota ----
$daftarAnggota = [];
if (!$id_anggota) {
    if ($q !== '') {
        $stmt = $koneksi->prepare("
            SELECT * FROM anggota
            WHERE status = 'aktif' AND (nama_lengkap LIKE :kw OR nis LIKE :kw OR kelas LIKE :kw OR username LIKE :kw)
            ORDER BY nama_lengkap ASC LIMIT 30
        ");
        $stmt->execute(['kw' => '%' . $q . '%']);
        $daftarAnggota = $stmt->fetchAll();
    } else {
        $daftarAnggota = $koneksi->query("SELECT * FROM anggota WHERE status = 'aktif' ORDER BY nama_lengkap ASC LIMIT 30")->fetchAll();
    }
}

// ---- Tahap 2: cari buku (tersedia saja) ----
$daftarBuku = [];
if ($id_anggota && !$id_buku) {
    if ($q !== '') {
        $stmt = $koneksi->prepare("
            SELECT b.*, k.nama_kategori
            FROM buku b LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
            WHERE b.stok > 0 AND (b.judul LIKE :kw OR b.pengarang LIKE :kw OR b.kode_buku LIKE :kw)
            ORDER BY b.judul ASC LIMIT 30
        ");
        $stmt->execute(['kw' => '%' . $q . '%']);
        $daftarBuku = $stmt->fetchAll();
    } else {
        $daftarBuku = $koneksi->query("
            SELECT b.*, k.nama_kategori FROM buku b
            LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
            WHERE b.stok > 0 ORDER BY b.judul ASC LIMIT 30
        ")->fetchAll();
    }
}

$tanggalPinjamPreview = date('Y-m-d');
$jatuhTempoPreview = date('Y-m-d', strtotime('+7 days'));

$activeMenu = 'peminjaman';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peminjaman - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .pick-list { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
  .pick-row {
    display: flex; align-items: center; justify-content: space-between; gap: 14px;
    padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px;
    text-decoration: none; color: inherit; transition: border-color .15s ease, box-shadow .15s ease;
  }
  .pick-row:hover { border-color: var(--royal-400); box-shadow: var(--shadow-hover); }
  .pick-row strong { display: block; color: var(--navy); font-size: .95rem; }
  .pick-row small { display: block; color: var(--muted); font-size: .8rem; margin-top: 2px; }
  .selected-card {
    display: flex; align-items: center; justify-content: space-between; gap: 14px;
    background: rgba(37,99,235,.06); border: 1px solid rgba(37,99,235,.18);
    border-radius: 14px; padding: 16px 18px; margin-bottom: 20px;
  }
  .kv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; font-size: .88rem; margin: 16px 0; }
  .kv-grid div span { display: block; color: var(--muted); font-size: .72rem; margin-bottom: 2px; }
</style>
</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Peminjaman Buku</h1>
        <p>Cari anggota, lalu pilih buku yang akan dipinjam.</p>
      </div>
    </div>

    <?php if ($pesan === 'gagal_stok'): ?>
      <p class="alert alert-gagal">Peminjaman gagal, stok buku sudah habis. Silakan pilih buku lain.</p>
    <?php elseif ($pesan === 'gagal'): ?>
      <p class="alert alert-gagal">Peminjaman gagal diproses. Silakan coba lagi.</p>
    <?php endif; ?>

    <?php if (!$id_anggota): ?>
      <!-- ===== TAHAP 1: PILIH ANGGOTA ===== -->
      <div class="card">
        <h3 style="margin-top:0;">1. Cari Anggota</h3>
        <form method="GET" action="peminjaman.php" class="search-form">
          <input type="text" name="q" placeholder="Cari NIS, nama, kelas, atau username..." value="<?= htmlspecialchars($q) ?>">
          <button type="submit" class="btn btn-outline">Cari</button>
          <?php if ($q !== ''): ?><a href="peminjaman.php" class="btn-link">Reset</a><?php endif; ?>
        </form>

        <div class="pick-list">
          <?php foreach ($daftarAnggota as $a): ?>
            <a href="peminjaman.php?anggota=<?= $a['id_anggota'] ?>" class="pick-row">
              <div>
                <strong><?= htmlspecialchars($a['nama_lengkap']) ?></strong>
                <small>NIS <?= htmlspecialchars($a['nis']) ?> &middot; Kelas <?= htmlspecialchars($a['kelas']) ?></small>
              </div>
              <span class="btn btn-outline" style="pointer-events:none;">Pilih</span>
            </a>
          <?php endforeach; ?>
          <?php if (!$daftarAnggota): ?>
            <p style="color:var(--muted); text-align:center; padding:20px 0;">Tidak ada anggota aktif yang cocok.</p>
          <?php endif; ?>
        </div>
      </div>

    <?php elseif (!$id_buku): ?>
      <!-- ===== TAHAP 2: PILIH BUKU ===== -->
      <div class="selected-card">
        <div>
          <strong style="display:block; color:var(--navy);"><?= htmlspecialchars($anggotaTerpilih['nama_lengkap']) ?></strong>
          <small style="color:var(--muted);">NIS <?= htmlspecialchars($anggotaTerpilih['nis']) ?> &middot; Kelas <?= htmlspecialchars($anggotaTerpilih['kelas']) ?></small>
        </div>
        <a href="peminjaman.php" class="btn-link">Ganti Anggota</a>
      </div>

      <div class="card">
        <h3 style="margin-top:0;">2. Cari Buku Tersedia</h3>
        <form method="GET" action="peminjaman.php" class="search-form">
          <input type="hidden" name="anggota" value="<?= $id_anggota ?>">
          <input type="text" name="q" placeholder="Cari judul, pengarang, atau kode buku..." value="<?= htmlspecialchars($q) ?>">
          <button type="submit" class="btn btn-outline">Cari</button>
          <?php if ($q !== ''): ?><a href="peminjaman.php?anggota=<?= $id_anggota ?>" class="btn-link">Reset</a><?php endif; ?>
        </form>

        <div class="pick-list">
          <?php foreach ($daftarBuku as $b): ?>
            <a href="peminjaman.php?anggota=<?= $id_anggota ?>&buku=<?= $b['id_buku'] ?>" class="pick-row">
              <div>
                <strong><?= htmlspecialchars($b['judul']) ?></strong>
                <small>oleh <?= htmlspecialchars($b['pengarang']) ?> &middot; <?= htmlspecialchars($b['nama_kategori'] ?? 'Lainnya') ?> &middot; Stok <?= (int)$b['stok'] ?></small>
              </div>
              <span class="btn" style="pointer-events:none;">Pilih</span>
            </a>
          <?php endforeach; ?>
          <?php if (!$daftarBuku): ?>
            <p style="color:var(--muted); text-align:center; padding:20px 0;">Tidak ada buku tersedia yang cocok.</p>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?>
      <!-- ===== TAHAP 3: KONFIRMASI ===== -->
      <div class="card" style="max-width:640px; margin:0 auto;">
        <h3 style="margin-top:0;">Konfirmasi Peminjaman</h3>

        <h4 style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); margin:18px 0 8px;">Data Anggota</h4>
        <div class="kv-grid">
          <div><span>Nama</span><?= htmlspecialchars($anggotaTerpilih['nama_lengkap']) ?></div>
          <div><span>NIS</span><?= htmlspecialchars($anggotaTerpilih['nis']) ?></div>
          <div><span>Kelas</span><?= htmlspecialchars($anggotaTerpilih['kelas']) ?></div>
        </div>

        <h4 style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); margin:18px 0 8px;">Data Buku</h4>
        <div class="kv-grid">
          <div><span>Judul</span><?= htmlspecialchars($bukuTerpilih['judul']) ?></div>
          <div><span>Pengarang</span><?= htmlspecialchars($bukuTerpilih['pengarang']) ?></div>
          <div><span>Genre</span><?= htmlspecialchars($bukuTerpilih['nama_kategori'] ?? '-') ?></div>
          <div><span>Stok Tersedia</span><?= (int)$bukuTerpilih['stok'] ?></div>
        </div>

        <h4 style="font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); margin:18px 0 8px;">Detail Peminjaman</h4>
        <div class="kv-grid">
          <div><span>Tanggal Pinjam</span><?= $tanggalPinjamPreview ?></div>
          <div><span>Jatuh Tempo</span><?= $jatuhTempoPreview ?></div>
        </div>

        <?php if ((int)$bukuTerpilih['stok'] < 1): ?>
          <p class="alert alert-gagal">Stok buku ini baru saja habis. Silakan pilih buku lain.</p>
          <a href="peminjaman.php?anggota=<?= $id_anggota ?>" class="btn btn-outline">Pilih Buku Lain</a>
        <?php else: ?>
          <div style="display:flex; gap:12px; margin-top:22px;">
            <a href="peminjaman.php?anggota=<?= $id_anggota ?>" class="btn btn-outline" style="flex:1; text-align:center;">Batalkan</a>
            <form method="POST" action="proses_pinjam.php" style="flex:1;">
              <?= csrfField() ?>
              <input type="hidden" name="id_anggota" value="<?= $id_anggota ?>">
              <input type="hidden" name="id_buku" value="<?= $id_buku ?>">
              <button type="submit" class="btn" style="width:100%;">Konfirmasi Peminjaman</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  </main>
</body>
</html>
