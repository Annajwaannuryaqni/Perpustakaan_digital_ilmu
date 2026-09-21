<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

// Ambil id buku yang mau diedit dari URL, misal: edit_buku.php?id=1
$id = $_GET['id'] ?? null;
if (!$id) { header('Location: data_buku.php'); exit; }

$error = '';

// Ambil data buku yang mau diedit
$stmt = $koneksi->prepare("SELECT * FROM buku WHERE id_buku = ?");
$stmt->execute([$id]);
$buku = $stmt->fetch();

if (!$buku) { header('Location: data_buku.php'); exit; }

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
    $kualitas     = in_array($_POST['kualitas'] ?? '', ['Baik', 'Cukup', 'Rusak']) ? $_POST['kualitas'] : 'Baik';
    $nama_file_cover = $buku['cover']; // default: pakai cover lama
    $coverLamaUntukDihapus = null; // diisi kalau ada cover baru yang berhasil disimpan, dihapus setelah UPDATE sukses

    // ---- Cek dulu apakah kode_buku dipakai buku LAIN (bukan dirinya sendiri) ----
    $cekKode = $koneksi->prepare("SELECT id_buku FROM buku WHERE kode_buku = ? AND id_buku != ?");
    $cekKode->execute([$kode_buku, $id]);
    if ($cekKode->fetch()) {
        $error = 'Kode buku sudah dipakai buku lain, gunakan kode lain.';
    }

    // Kalau admin upload cover baru, ganti cover lama
    if (!$error && isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $mimeKeEkstensi = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
        ];
        $maksUkuranBytes = 2 * 1024 * 1024; // batas 2MB

        if ($_FILES['cover']['size'] > $maksUkuranBytes) {
            $error = 'Ukuran file cover maksimal 2MB.';
        } else {
            // Jangan percaya ekstensi nama file dari browser — cek isi file
            // sungguhan pakai getimagesize(). Ini mencegah file yang bukan
            // gambar asli (mis. file berbahaya yang cuma diganti nama jadi
            // .jpg) lolos tersimpan di server.
            $infoGambar = @getimagesize($_FILES['cover']['tmp_name']);

            if ($infoGambar === false || !isset($mimeKeEkstensi[$infoGambar['mime']])) {
                $error = 'File yang diupload bukan gambar JPG/PNG yang valid.';
            } else {
                $ekstensi = $mimeKeEkstensi[$infoGambar['mime']];
                $nama_file_cover_baru = 'cover_' . bin2hex(random_bytes(8)) . '.' . $ekstensi;

                if (!move_uploaded_file($_FILES['cover']['tmp_name'], '../uploads/' . $nama_file_cover_baru)) {
                    // Upload gagal disimpan ke server — cover lama tetap dipakai,
                    // jangan sampai data buku ikut ter-update dengan cover yang salah.
                    $error = 'Gagal menyimpan file cover ke server. Silakan coba lagi.';
                } else {
                    // Simpan nama cover lama dulu untuk dihapus nanti — tapi baru
                    // benar-benar dihapus SETELAH UPDATE ke DB berhasil, supaya
                    // kalau UPDATE-nya gagal, cover lama tidak ikut hilang sia-sia.
                    if (!empty($buku['cover']) && $buku['cover'] !== $nama_file_cover_baru) {
                        $coverLamaUntukDihapus = $buku['cover'];
                    }
                    $nama_file_cover = $nama_file_cover_baru;
                }
            }
        }
    }

    if (!$error) {
        $stmt = $koneksi->prepare("
            UPDATE buku SET
                kode_buku = ?, judul = ?, pengarang = ?, penerbit = ?, tahun_terbit = ?,
                id_kategori = ?, stok = ?, lokasi_rak = ?, deskripsi = ?, cover = ?, kualitas = ?
            WHERE id_buku = ?
        ");
        $stmt->execute([$kode_buku, $judul, $pengarang, $penerbit, $tahun_terbit, $id_kategori, $stok, $lokasi_rak, $deskripsi, $nama_file_cover, $kualitas, $id]);

        // UPDATE berhasil — baru sekarang aman hapus file cover lama dari server.
        if ($coverLamaUntukDihapus) {
            $pathCoverLama = '../uploads/' . $coverLamaUntukDihapus;
            if (is_file($pathCoverLama)) {
                @unlink($pathCoverLama);
            }
        }

        header('Location: data_buku.php');
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
  <?php $activeMenu = 'data_buku'; require_once '../includes/petugas_sidebar.php'; ?>



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

        <label>Kualitas Buku</label>
        <?php $kualitasBuku = $buku['kualitas'] ?? 'Baik'; ?>
        <select name="kualitas">
          <option value="Baik" <?= $kualitasBuku === 'Baik' ? 'selected' : '' ?>>Baik</option>
          <option value="Cukup" <?= $kualitasBuku === 'Cukup' ? 'selected' : '' ?>>Cukup</option>
          <option value="Rusak" <?= $kualitasBuku === 'Rusak' ? 'selected' : '' ?>>Rusak</option>
        </select>

        <label>Deskripsi</label>
        <textarea name="deskripsi" rows="4"><?= htmlspecialchars($buku['deskripsi']) ?></textarea>

        <label>Ganti Cover (kosongkan jika tidak diganti)</label>
        <input type="file" name="cover" accept=".jpg,.jpeg,.png">

        <div style="display:flex; gap:10px; margin-top:20px;">
          <button type="submit" class="btn">Update</button>
          <a href="data_buku.php" class="btn btn-outline">Batal</a>
        </div>
      </form>
    </div>
  </div>
  </main>
</body>
</html>