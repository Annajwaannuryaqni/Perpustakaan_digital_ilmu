<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis          = trim($_POST['nis']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $kelas        = trim($_POST['kelas']);
    $no_hp        = trim($_POST['no_hp']);
    $alamat       = trim($_POST['alamat']);
    $username     = trim($_POST['username']);
    $password     = $_POST['password'];

    // Cek dulu apakah NIS atau username sudah dipakai anggota lain
    $cek = $koneksi->prepare("SELECT id_anggota FROM anggota WHERE nis = ? OR username = ?");
    $cek->execute([$nis, $username]);

    if ($cek->fetch()) {
        $error = 'NIS atau Username sudah terdaftar, gunakan yang lain.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $koneksi->prepare("
            INSERT INTO anggota (nis, nama_lengkap, kelas, no_hp, alamat, username, password)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nis, $nama_lengkap, $kelas, $no_hp, $alamat, $username, $hash]);

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
<title>Tambah Anggota</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>


  <div class="container">
    <div class="page-head">
      <div>
        <h1>➕ Tambah Anggota</h1>
        <p>Daftarkan siswa baru sebagai anggota perpustakaan.</p>
      </div>
    </div>

    <div class="card form-card">
      <?php if ($error): ?>
        <p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST">
        <label>NIS</label>
        <input type="text" name="nis" required>

        <label>Nama Lengkap</label>
        <input type="text" name="nama_lengkap" required>

        <label>Kelas</label>
        <input type="text" name="kelas" required>

        <label>No HP</label>
        <input type="text" name="no_hp">

        <label>Alamat</label>
        <textarea name="alamat" rows="3"></textarea>

        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <div style="display:flex; gap:10px; margin-top:20px;">
          <button type="submit" class="btn">💾 Simpan</button>
          <a href="anggota.php" class="btn btn-outline">Batal</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>