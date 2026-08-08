<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$error = '';

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
    $nama_file_cover = null;

    // ---- Validasi: genre wajib dipilih ----
    if ($id_kategori === '') {
        $error = 'Genre wajib dipilih.';
    }

    // ---- Cek dulu apakah kode_buku sudah dipakai buku lain ----
    if (!$error) {
        $cekKode = $koneksi->prepare("SELECT id_buku FROM buku WHERE kode_buku = ?");
        $cekKode->execute([$kode_buku]);
        if ($cekKode->fetch()) {
            $error = 'Kode buku sudah dipakai, gunakan kode lain.';
        }
    }
    // ---- Proses upload cover (kalau ada file yang dipilih) ----
    if (!$error && isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $ekstensiOk = ['jpg', 'jpeg', 'png'];
        $ekstensi = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));

        if (in_array($ekstensi, $ekstensiOk)) {
            // Bikin nama file unik biar tidak bentrok antar buku
            $nama_file_cover = 'cover_' . time() . '_' . rand(100,999) . '.' . $ekstensi;
            $tujuan = '../uploads/' . $nama_file_cover;
            move_uploaded_file($_FILES['cover']['tmp_name'], $tujuan);
        } else {
            $error = 'Format file cover harus JPG atau PNG.';
        }
    }

    if (!$error) {
        $stmt = $koneksi->prepare("
            INSERT INTO buku (kode_buku, judul, pengarang, penerbit, tahun_terbit, id_kategori, stok, lokasi_rak, deskripsi, cover)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$kode_buku, $judul, $pengarang, $penerbit, $tahun_terbit, $id_kategori, $stok, $lokasi_rak, $deskripsi, $nama_file_cover]);

        header('Location: buku.php');
        exit;
    }
}

// Ambil daftar kategori buat pilihan dropdown
$kategoriList = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Buku</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>


  <div class="container">
    <div class="page-head">
      <div>
        <h1>➕ Tambah Buku</h1>
        <p>Lengkapi detail buku baru untuk ditambahkan ke katalog.</p>
      </div>
    </div>

    <div class="card form-card">
      <?php if ($error): ?>
        <p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>

        <label>Kode Buku</label>
        <input type="text" name="kode_buku" value="<?= htmlspecialchars($_POST['kode_buku'] ?? '') ?>" required>

        <label>Judul</label>
        <input type="text" name="judul" value="<?= htmlspecialchars($_POST['judul'] ?? '') ?>" required>

        <label>Pengarang</label>
        <input type="text" name="pengarang" value="<?= htmlspecialchars($_POST['pengarang'] ?? '') ?>" required>

        <label>Penerbit</label>
        <input type="text" name="penerbit" value="<?= htmlspecialchars($_POST['penerbit'] ?? '') ?>">

        <label>Tahun Terbit</label>
        <input type="number" name="tahun_terbit" value="<?= htmlspecialchars($_POST['tahun_terbit'] ?? '') ?>">

        <label>Genre</label>
        <select name="id_kategori">
          <option value="">-- pilih genre --</option>
          <?php foreach ($kategoriList as $k): ?>
            <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
          <?php endforeach; ?>
        </select>

        <label>Stok</label>
        <input type="number" name="stok" value="<?= htmlspecialchars($_POST['stok'] ?? '1') ?>" required>

        <label>Lokasi Rak</label>
        <input type="text" name="lokasi_rak" value="<?= htmlspecialchars($_POST['lokasi_rak'] ?? '') ?>">

        <label>Deskripsi</label>
        <textarea name="deskripsi" rows="4"><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>

        <label>Cover Buku (JPG/PNG)</label>
        <input type="file" name="cover" accept=".jpg,.jpeg,.png">

        <div style="display:flex; gap:10px; margin-top:20px;">
          <button type="submit" class="btn">💾 Simpan</button>
          <a href="buku.php" class="btn btn-outline">Batal</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>