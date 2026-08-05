<?php
session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $koneksi->prepare("
        SELECT *
        FROM anggota
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->execute([$username]);

    $anggota = $stmt->fetch();

    if ($anggota && password_verify($password, $anggota['password'])) {

        if ($anggota['status'] === 'nonaktif') {

            $error = "Akun sedang dinonaktifkan. Hubungi admin perpustakaan.";

        } else {

            $_SESSION['anggota_id'] = $anggota['id_anggota'];
            $_SESSION['anggota_nama'] = $anggota['nama_lengkap'];

            $_SESSION['flash_notif'] = [

                'title'   => 'Login Berhasil',

                'message' => 'Selamat datang kembali, selamat membaca 😊',

                'type'    => 'success',

                'icon'    => 'fa-circle-check',

                'color'   => '#22c55e'

            ];

            header("Location: dashboard.php");
            exit;

        }

    } else {

        $error = "Username atau Password salah.";

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

<title>Login Siswa</title>

<link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<div class="login-wrap">

<div class="login-box">

<h2>🎓 Login Siswa</h2>

<?php if($error): ?>

<p class="alert alert-gagal">
<?= htmlspecialchars($error) ?>
</p>
<?php endif; ?>
<form method="POST">
<label>Username</label>
<input
type="text"
name="username"
required
autofocus>
<label>Password</label>
<input
type="password"
name="password"
required>
<button
type="submit"
class="btn"
style="width:100%;margin-top:18px;">
Masuk
</button>
</form>
<br>
<a
href="daftar.php"
class="btn-link">
Belum punya akun? Daftar
</a>
<br><br>
<a
href="../index.php"
class="back-link">
← Kembali
</a>
</div>
</div>
</body>
</html>