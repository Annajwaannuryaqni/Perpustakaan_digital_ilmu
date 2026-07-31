<?php
session_start();
require_once '../config/database.php';

$error = '';
$sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis          = trim($_POST['nis']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $kelas        = trim($_POST['kelas']);
    $no_hp        = trim($_POST['no_hp']);
    $alamat       = trim($_POST['alamat']);
    $username     = trim($_POST['username']);
    $password     = $_POST['password'];
    $konfirmasi   = $_POST['konfirmasi_password'];

    if ($password !== $konfirmasi) {
        $error = 'Password dan konfirmasi password tidak sama.';
    } else {
        // Cek NIS/username sudah dipakai atau belum
        $cek = $koneksi->prepare("SELECT id_anggota FROM anggota WHERE nis = ? OR username = ?");
        $cek->execute([$nis, $username]);

        if ($cek->fetch()) {
            $error = 'NIS atau Username sudah terdaftar. Coba yang lain, atau langsung login jika sudah pernah daftar.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $koneksi->prepare("
                INSERT INTO anggota (nis, nama_lengkap, kelas, no_hp, alamat, username, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nis, $nama_lengkap, $kelas, $no_hp, $alamat, $username, $hash]);
            $sukses = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Daftar Anggota</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div class="login-wrap">
    <div class="login-box" style="max-width:420px;">
      <h2> Daftar Anggota Perpustakaan</h2>

      <?php if ($sukses): ?>
        <p class="alert alert-sukses">Pendaftaran berhasil! Silakan login menggunakan username &amp; password yang baru dibuat.</p>
        <a href="login.php" class="btn" style="display:inline-block; text-decoration:none;">Login Sekarang &rarr;</a>
      <?php else: ?>

        <?php if ($error): ?>
          <p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" style="text-align:left;">
          <label>NIS</label>
          <input type="text" name="nis" required>

          <label>Nama Lengkap</label>
          <input type="text" name="nama_lengkap" required>

          <label>Kelas</label>
          <input type="text" name="kelas" required>

          <label>No HP</label>
          <input type="text" name="no_hp">

          <label>Alamat</label>
          <textarea name="alamat" rows="3" style="width:100%;"></textarea>

          <label>Username</label>
          <input type="text" name="username" required>

          <label>Password</label>
          <input type="password" name="password" required>

          <label>Konfirmasi Password</label>
          <input type="password" name="konfirmasi_password" required>

          <button type="submit" class="btn" style="width:100%; margin-top:16px;">Daftar</button>
        </form>
        <br>
        <a href="../index.php" class="back-link">&larr; Kembali</a>

      <?php endif; ?>
    </div>
  </div>
</body>
</html>