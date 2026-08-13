<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$error = '';
$sukses = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username     = trim($_POST['username'] ?? '');
        $password     = $_POST['password'] ?? '';

        if ($nama_lengkap === '' || $username === '' || $password === '') {
            $error = 'Semua kolom wajib diisi.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } else {
            $cek = $koneksi->prepare("SELECT id_petugas FROM petugas WHERE username = ?");
            $cek->execute([$username]);
            if ($cek->fetch()) {
                $error = 'Username sudah dipakai, gunakan yang lain.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $koneksi->prepare("INSERT INTO petugas (username, password, nama_lengkap) VALUES (?, ?, ?)");
                $stmt->execute([$username, $hash, $nama_lengkap]);
                header('Location: petugas.php?pesan=sukses');
                exit;
            }
        }
    } elseif ($aksi === 'toggle_status' && !empty($_POST['id'])) {
        $stmt = $koneksi->prepare("UPDATE petugas SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id_petugas = ?");
        $stmt->execute([$_POST['id']]);
        header('Location: petugas.php');
        exit;
    } elseif ($aksi === 'hapus' && !empty($_POST['id'])) {
        $stmt = $koneksi->prepare("DELETE FROM petugas WHERE id_petugas = ?");
        $stmt->execute([$_POST['id']]);
        header('Location: petugas.php');
        exit;
    }
}

if (($_GET['pesan'] ?? '') === 'sukses') {
    $sukses = 'Akun Petugas baru berhasil dibuat.';
}

$daftarPetugas = $koneksi->query("SELECT * FROM petugas ORDER BY nama_lengkap ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Petugas</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-page">
  <button class="admin-menu-toggle" type="button" aria-label="Buka menu" onclick="document.body.classList.toggle('admin-menu-open')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="14" y2="17"/></svg></button>
  <div class="admin-sidebar-overlay" onclick="document.body.classList.remove('admin-menu-open')"></div>
  <aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
      <div class="admin-brand-mark">P</div>
      <div><strong>Perpustakaan</strong><small>Panel Admin</small></div>
    </div>
    <nav class="admin-side-nav" aria-label="Navigasi admin">
      <div class="admin-side-label">MENU UTAMA</div>
      <a href="dashboard.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="8" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="14.5" width="7" height="6" rx="1.5"/></svg></span><span>Dashboard</span></a>
      <a href="buku.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/></svg></span><span>Buku</span></a>
      <a href="anggota.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.3c2.4.3 4 2.2 4 5.2"/></svg></span><span>Anggota</span></a>
      <a href="transaksi.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/></svg></span><span>Transaksi</span></a>
      <a href="kunjungan.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5"/><line x1="8" y1="3" x2="8" y2="6.5"/><line x1="16" y1="3" x2="16" y2="6.5"/></svg></span><span>Kunjungan</span></a>
      <a href="petugas.php" class="admin-side-link active"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="3"/><path d="M4 19.5c0-3 2.2-5 5-5s5 2 5 5"/><path d="M14.5 9.2h5M17 6.7v5"/></svg></span><span>Petugas</span></a>
    </nav>
    <div class="admin-sidebar-bottom">
      <div class="admin-side-user"><span class="admin-avatar">A</span><span><strong>Admin</strong><small>Pengelola Perpustakaan</small></span></div>
      <a href="logout.php" class="admin-logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/></svg><span>Keluar</span></a>
    </div>
  </aside>
  <main class="admin-main">

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Kelola Akun Petugas</h1>
        <p>Buat dan kelola akun Petugas Perpustakaan. Petugas hanya memiliki akses operasional, bukan akses administratif.</p>
      </div>
    </div>

    <?php if ($sukses): ?><p class="alert alert-sukses"><?= htmlspecialchars($sukses) ?></p><?php endif; ?>

    <div class="card form-card">
      <h3 style="margin-top:0;">Tambah Petugas Baru</h3>
      <?php if ($error): ?><p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="aksi" value="tambah">
        <label>Nama Lengkap</label>
        <input type="text" name="nama_lengkap" required>
        <label>Username</label>
        <input type="text" name="username" required>
        <label>Password</label>
        <input type="password" name="password" required minlength="6">
        <div style="margin-top:20px;">
          <button type="submit" class="btn">Buat Akun Petugas</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h3 style="margin-top:0;">Daftar Petugas</h3>
      <table>
        <thead>
          <tr>
            <th>Nama Lengkap</th>
            <th>Username</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarPetugas as $p): ?>
          <tr>
            <td data-label="Nama Lengkap" style="font-weight:600;"><?= htmlspecialchars($p['nama_lengkap']) ?></td>
            <td data-label="Username"><?= htmlspecialchars($p['username']) ?></td>
            <td data-label="Status">
              <?php if ($p['status'] === 'aktif'): ?>
                <span class="badge badge-ok">aktif</span>
              <?php else: ?>
                <span class="badge badge-habis">nonaktif</span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="aksi" value="toggle_status">
                <input type="hidden" name="id" value="<?= $p['id_petugas'] ?>">
                <button type="submit" class="btn-link" style="background:none; border:none; cursor:pointer; padding:0; font:inherit;">
                  <?= $p['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                </button>
              </form>
              ·
              <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus akun petugas ini? Riwayat transaksi yang sudah tercatat tidak akan terhapus.')">
                <?= csrfField() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= $p['id_petugas'] ?>">
                <button type="submit" class="btn-link" style="color:var(--coral); background:none; border:none; cursor:pointer; padding:0; font:inherit;">Hapus</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarPetugas): ?>
          <tr><td colspan="4" style="text-align:center;">Belum ada akun Petugas.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
