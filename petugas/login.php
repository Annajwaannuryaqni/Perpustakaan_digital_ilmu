<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Jika Petugas sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['petugas_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $koneksi->prepare("SELECT * FROM petugas WHERE username = ?");
    $stmt->execute([$username]);
    $petugas = $stmt->fetch();

    if ($petugas && password_verify($password, $petugas['password'])) {
        if ($petugas['status'] === 'nonaktif') {
            $error = 'Akun Petugas sedang dinonaktifkan. Hubungi Administrator.';
        } else {
            // Session Petugas terpisah dari session Administrator/Siswa,
            // sehingga tidak akan saling menimpa.
            session_regenerate_id(true);
            $_SESSION['petugas_id']   = $petugas['id_petugas'];
            $_SESSION['petugas_nama'] = $petugas['nama_lengkap'];
            $_SESSION['flash_notif'] = [
                'title'   => 'Login Berhasil',
                'message' => 'Selamat datang, ' . $petugas['nama_lengkap'] . '.',
                'type'    => 'success',
                'icon'    => 'fa-circle-check',
                'color'   => '#22c55e',
            ];
            header('Location: dashboard.php');
            exit;
        }
    } else {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Petugas - Perpustakaan Digital</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div class="login-wrap">
    <div class="login-box">
      <div class="admin-brand-mark">P</div>
      <h2>Login Petugas</h2>
      <p style="text-align:center; color:var(--muted); margin-top:-10px; margin-bottom:18px; font-size:.88rem;">
        Masuk untuk mengelola aktivitas perpustakaan.
      </p>

      <?php if ($error): ?>
        <p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>
        <label>Username</label>
        <input type="text" name="username" required autofocus>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit" class="btn" style="width:100%; margin-top:16px;">Masuk</button>
      </form>
      <br>
      <a href="../index.php" class="back-link">&larr; Kembali ke Beranda</a>
    </div>
  </div>
</body>
</html>
