<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

// Ambil id buku yang mau diedit dari URL, misal: edit_buku.php?id=1
$id = $_GET['id'] ?? null;
if (!$id) { header('Location: buku.php'); exit; }

$error = '';

// Ambil data buku yang mau diedit
$stmt = $koneksi->prepare("SELECT * FROM buku WHERE id_buku = ?");
$stmt->execute([$id]);
$buku = $stmt->fetch();

if (!$buku) { header('Location: buku.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $kode_buku    = trim($_POST['kode_buku']);
    $judul        = trim($_POST['judul']);
    $pengarang    = trim($_POST['pengarang']);
    $penerbit     = trim($_POST['penerbit']);
    $tahun_terbit = trim($_POST['tahun_terbit']);
    $id_kategori  = $_POST['id_kategori'];
    $stok         = (int) $_POST['stok'];
    $lokasi_rak   = trim($_POST['lokasi_rak']);
    $deskripsi    = trim($_POST['deskripsi']);
    $nama_file_cover = $buku['cover']; // default: pakai cover lama

    // ---- Cek dulu apakah kode_buku dipakai buku LAIN (bukan dirinya sendiri) ----
    $cekKode = $koneksi->prepare("SELECT id_buku FROM buku WHERE kode_buku = ? AND id_buku != ?");
    $cekKode->execute([$kode_buku, $id]);
    if ($cekKode->fetch()) {
        $error = 'Kode buku sudah dipakai buku lain, gunakan kode lain.';
    }

    // Kalau admin upload cover baru, ganti cover lama
    if (!$error && isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $ekstensiOk = ['jpg', 'jpeg', 'png'];
        $ekstensi = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));

        if (in_array($ekstensi, $ekstensiOk)) {
            $nama_file_cover = 'cover_' . time() . '_' . rand(100,999) . '.' . $ekstensi;
            move_uploaded_file($_FILES['cover']['tmp_name'], '../uploads/' . $nama_file_cover);
        } else {
            $error = 'Format file cover harus JPG atau PNG.';
        }
    }

    if (!$error) {
        $stmt = $koneksi->prepare("
            UPDATE buku SET
                kode_buku = ?, judul = ?, pengarang = ?, penerbit = ?, tahun_terbit = ?,
                id_kategori = ?, stok = ?, lokasi_rak = ?, deskripsi = ?, cover = ?
            WHERE id_buku = ?
        ");
        $stmt->execute([$kode_buku, $judul, $pengarang, $penerbit, $tahun_terbit, $id_kategori, $stok, $lokasi_rak, $deskripsi, $nama_file_cover, $id]);

        header('Location: buku.php');
        exit;
    }
}

$kategoriList = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Buku</title>
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
      <a href="buku.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/></svg></span><span>Buku</span></a>
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
        <h1>Edit Buku</h1>
        <p>Perbarui detail buku <b><?= htmlspecialchars($buku['judul']) ?></b>.</p>
      </div>
    </div>

    <div class="card form-card">
      <?php if ($error): ?>
        <p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <?php if ($buku['cover']): ?>
        <div style="text-align:center; margin-bottom:18px;">
          <p style="color:var(--text-muted); margin-bottom:8px; font-size:.8rem;">Cover saat ini</p>
          <img src="../uploads/<?= htmlspecialchars($buku['cover']) ?>" width="100"
               style="border-radius:10px; box-shadow:var(--shadow);">
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>

        <label>Kode Buku</label>
        <input type="text" name="kode_buku" value="<?= htmlspecialchars($buku['kode_buku']) ?>" required>

        <label>Judul</label>
        <input type="text" name="judul" value="<?= htmlspecialchars($buku['judul']) ?>" required>

        <label>Pengarang</label>
        <input type="text" name="pengarang" value="<?= htmlspecialchars($buku['pengarang']) ?>" required>

        <label>Penerbit</label>
        <input type="text" name="penerbit" value="<?= htmlspecialchars($buku['penerbit']) ?>">

        <label>Tahun Terbit</label>
        <input type="number" name="tahun_terbit" value="<?= htmlspecialchars($buku['tahun_terbit']) ?>">

        <label>Genre</label>
        <select name="id_kategori">
          <option value="">-- pilih genre --</option>
          <?php foreach ($kategoriList as $k): ?>
            <option value="<?= $k['id_kategori'] ?>" <?= ($k['id_kategori'] == $buku['id_kategori']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($k['nama_kategori']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label>Stok</label>
        <input type="number" name="stok" value="<?= $buku['stok'] ?>" required>

        <label>Lokasi Rak</label>
        <input type="text" name="lokasi_rak" value="<?= htmlspecialchars($buku['lokasi_rak']) ?>">

        <label>Deskripsi</label>
        <textarea name="deskripsi" rows="4"><?= htmlspecialchars($buku['deskripsi']) ?></textarea>

        <label>Ganti Cover (kosongkan jika tidak diganti)</label>
        <input type="file" name="cover" accept=".jpg,.jpeg,.png">

        <div style="display:flex; gap:10px; margin-top:20px;">
          <button type="submit" class="btn">Update</button>
          <a href="buku.php" class="btn btn-outline">Batal</a>
        </div>
      </form>
    </div>
  </div>
  </main>
</body>
</html>