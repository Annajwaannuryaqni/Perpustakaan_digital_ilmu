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
<body>


  <div class="container">
    <div class="page-head">
      <div>
        <h1>✏️ Edit Buku</h1>
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
          <button type="submit" class="btn">💾 Update</button>
          <a href="buku.php" class="btn btn-outline">Batal</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>