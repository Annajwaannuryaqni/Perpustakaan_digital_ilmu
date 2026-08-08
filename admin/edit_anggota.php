<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) { header('Location: anggota.php'); exit; }

$error = '';

$stmt = $koneksi->prepare("SELECT * FROM anggota WHERE id_anggota = ?");
$stmt->execute([$id]);
$anggota = $stmt->fetch();

if (!$anggota) { header('Location: anggota.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $nis          = trim($_POST['nis']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $kelas        = trim($_POST['kelas']);
    $no_hp        = trim($_POST['no_hp']);
    $alamat       = trim($_POST['alamat']);
    $username     = trim($_POST['username']);
    $status       = $_POST['status'];
    $password_baru = $_POST['password_baru'];

    // Cek NIS/username dipakai anggota LAIN (bukan dirinya sendiri)
    $cek = $koneksi->prepare("SELECT id_anggota FROM anggota WHERE (nis = ? OR username = ?) AND id_anggota != ?");
    $cek->execute([$nis, $username, $id]);

    if ($cek->fetch()) {
        $error = 'NIS atau Username sudah dipakai anggota lain.';
    } else {
        if (!empty($password_baru)) {
            // Admin mengisi password baru -> update sekalian passwordnya
            $hash = password_hash($password_baru, PASSWORD_BCRYPT);
            $stmt = $koneksi->prepare("
                UPDATE anggota SET nis=?, nama_lengkap=?, kelas=?, no_hp=?, alamat=?, username=?, status=?, password=?
                WHERE id_anggota=?
            ");
            $stmt->execute([$nis, $nama_lengkap, $kelas, $no_hp, $alamat, $username, $status, $hash, $id]);
        } else {
            // Password dikosongkan -> tidak diubah
            $stmt = $koneksi->prepare("
                UPDATE anggota SET nis=?, nama_lengkap=?, kelas=?, no_hp=?, alamat=?, username=?, status=?
                WHERE id_anggota=?
            ");
            $stmt->execute([$nis, $nama_lengkap, $kelas, $no_hp, $alamat, $username, $status, $id]);
        }

        header('Location: anggota.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Anggota</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>


  <div class="container">
    <div class="page-head">
      <div>
        <h1>✏️ Edit Anggota</h1>
        <p>Perbarui data <b><?= htmlspecialchars($anggota['nama_lengkap']) ?></b>.</p>
      </div>
    </div>

    <div class="card form-card">
      <?php if ($error): ?>
        <p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>

        <label>NIS</label>
        <input type="text" name="nis" value="<?= htmlspecialchars($anggota['nis']) ?>" required>

        <label>Nama Lengkap</label>
        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($anggota['nama_lengkap']) ?>" required>

        <label>Kelas</label>
        <input type="text" name="kelas" value="<?= htmlspecialchars($anggota['kelas']) ?>" required>

        <label>No HP</label>
        <input type="text" name="no_hp" value="<?= htmlspecialchars($anggota['no_hp']) ?>">

        <label>Alamat</label>
        <textarea name="alamat" rows="3"><?= htmlspecialchars($anggota['alamat']) ?></textarea>

        <label>Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($anggota['username']) ?>" required>

        <label>Status</label>
        <select name="status">
          <option value="aktif" <?= $anggota['status']=='aktif' ? 'selected' : '' ?>>Aktif</option>
          <option value="nonaktif" <?= $anggota['status']=='nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
        </select>

        <label>Password Baru (kosongkan jika tidak diganti)</label>
        <input type="password" name="password_baru">

        <div style="display:flex; gap:10px; margin-top:20px;">
          <button type="submit" class="btn">💾 Update</button>
          <a href="anggota.php" class="btn btn-outline">Batal</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>