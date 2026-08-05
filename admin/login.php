<?php
session_start();
require_once '../config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $koneksi->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id_admin'];
        $_SESSION['admin_nama'] = $admin['nama_lengkap'];
        $_SESSION['flash_notif'] = [
            'title'   => 'Login Berhasil',
            'message' => 'Selamat, Anda berhasil login sebagai Admin.',
            'type'    => 'success',
            'icon'    => 'fa-circle-check',
            'color'   => '#22c55e',
        ];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login Admin</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div class="login-wrap">
    <div class="login-box">
      <h2> Login Admin</h2>

      <?php if ($error): ?>
        <p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST">
        <label>Username</label>
        <input type="text" name="username" required autofocus>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit" class="btn" style="width:100%; margin-top:16px;">Masuk</button>
      </form>
      <br>
      <a href="../index.php" class="back-link">&larr; Kembali</a>
    </div>
  </div>
</body>
</html>