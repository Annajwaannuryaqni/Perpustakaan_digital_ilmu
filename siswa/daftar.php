<?php
session_start();
require_once '../config/database.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nis            = trim($_POST['nis'] ?? '');
    $nama_lengkap   = trim($_POST['nama_lengkap'] ?? '');
    $kelas          = trim($_POST['kelas'] ?? '');
    $no_hp          = trim($_POST['no_hp'] ?? '');
    $alamat         = trim($_POST['alamat'] ?? '');
    $username       = trim($_POST['username'] ?? '');
    $password       = $_POST['password'] ?? '';
    $konfirmasi     = $_POST['konfirmasi_password'] ?? '';

    if ($password !== $konfirmasi) {

        $error = "Password dan konfirmasi password tidak sama.";

    } else {

        $cek = $koneksi->prepare("
            SELECT id_anggota
            FROM anggota
            WHERE nis = ?
            OR username = ?
        ");

        $cek->execute([$nis, $username]);

        if ($cek->fetch()) {

            $error = "NIS atau Username sudah terdaftar.";

        } else {

            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $koneksi->prepare("
                INSERT INTO anggota
                (
                    nis,
                    nama_lengkap,
                    kelas,
                    no_hp,
                    alamat,
                    username,
                    password
                )
                VALUES
                (
                    ?,?,?,?,?,?,?
                )
            ");

            $stmt->execute([
                $nis,
                $nama_lengkap,
                $kelas,
                $no_hp,
                $alamat,
                $username,
                $hash
            ]);

            $success = true;

        }

    }

}
?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Daftar Anggota</title>

<link rel="stylesheet" href="../assets/style.css">

<link rel="stylesheet"
href="../assets/css/notification.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<body>

<div class="login-wrap">

<div
class="login-box"
style="max-width:450px;">

<h2>Daftar Anggota Perpustakaan</h2>

<?php if(!$success): ?>

<?php if($error): ?>

<p class="alert alert-gagal">

<?= htmlspecialchars($error) ?>

</p>

<?php endif; ?>

<form method="POST">

<label>NIS</label>

<input
type="text"
name="nis"
required>

<label>Nama Lengkap</label>

<input
type="text"
name="nama_lengkap"
required>

<label>Kelas</label>

<input
type="text"
name="kelas"
required>

<label>No HP</label>

<input
type="text"
name="no_hp">

<label>Alamat</label>

<textarea
name="alamat"
rows="3"></textarea>

<label>Username</label>

<input
type="text"
name="username"
required>

<label>Password</label>

<input
type="password"
name="password"
required>

<label>Konfirmasi Password</label>

<input
type="password"
name="konfirmasi_password"
required>

<button
type="submit"
class="btn"
style="width:100%;margin-top:18px;">

Daftar

</button>

</form>

<br>

<a
href="../index.php"
class="back-link">

← Kembali

</a>

<?php endif; ?>

</div>
</div>
<div id="toast-container"></div>
<audio id="notificationSound" preload="auto">
    <source src="../assets/sound/notification.mp3" type="audio/mpeg">
</audio>
<script src="../assets/js/notification.js"></script>
<?php if($success): ?>
<script>
document.addEventListener("DOMContentLoaded",function(){
    if(typeof showToast==="function"){
        showToast(
            "Registrasi Berhasil",
            "Selamat! Akun berhasil dibuat. Anda akan diarahkan ke halaman login.",
            "success",
            "fa-circle-check",
            "#22c55e"
        );

    }
    setTimeout(function(){
        window.location.href="login.php"
    },3000);
});
</script>
<?php endif; ?>
</body>
</html>