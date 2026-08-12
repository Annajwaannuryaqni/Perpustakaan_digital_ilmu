<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

$id_petugas = $_SESSION['petugas_id'];

$stmt = $koneksi->prepare("SELECT * FROM petugas WHERE id_petugas = ?");
$stmt->execute([$id_petugas]);
$petugas = $stmt->fetch();

if (!$petugas) {
    header('Location: logout.php');
    exit;
}

$error = '';
$sukses = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if ($password_baru === '' || $konfirmasi === '') {
        $error = 'Password baru wajib diisi.';
    } elseif ($password_baru !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif (strlen($password_baru) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } else {
        $hash = password_hash($password_baru, PASSWORD_BCRYPT);
        $update = $koneksi->prepare("UPDATE petugas SET password = ? WHERE id_petugas = ?");
        $update->execute([$hash, $id_petugas]);
        $sukses = 'Password berhasil diperbarui.';
    }
}

$activeMenu = 'profil';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Profil Petugas</h1>
        <p>Informasi akun dan pengaturan password.</p>
      </div>
    </div>

    <div class="card form-card">
      <h3 style="margin-top:0;">Informasi Akun</h3>
      <div class="kv-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; font-size:.9rem; margin-bottom:8px;">
        <div><span style="display:block; color:var(--muted); font-size:.72rem;">Nama Lengkap</span><?= htmlspecialchars($petugas['nama_lengkap']) ?></div>
        <div><span style="display:block; color:var(--muted); font-size:.72rem;">Username</span><?= htmlspecialchars($petugas['username']) ?></div>
        <div><span style="display:block; color:var(--muted); font-size:.72rem;">Role</span>Petugas</div>
        <div><span style="display:block; color:var(--muted); font-size:.72rem;">Status</span><?= htmlspecialchars(ucfirst($petugas['status'])) ?></div>
      </div>
    </div>

    <div class="card form-card">
      <h3 style="margin-top:0;">Ubah Password</h3>

      <?php if ($error): ?><p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <?php if ($sukses): ?><p class="alert alert-sukses"><?= htmlspecialchars($sukses) ?></p><?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>
        <label>Password Baru</label>
        <input type="password" name="password_baru" required minlength="6">
        <label>Konfirmasi Password Baru</label>
        <input type="password" name="konfirmasi_password" required minlength="6">
        <div style="margin-top:20px;">
          <button type="submit" class="btn">Simpan Password</button>
        </div>
      </form>
    </div>
  </div>
  </main>
</body>
</html>