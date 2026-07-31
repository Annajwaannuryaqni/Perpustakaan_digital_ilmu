<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$keyword = trim($_GET['q'] ?? '');

if ($keyword !== '') {
    $stmt = $koneksi->prepare("
        SELECT * FROM anggota
        WHERE nama_lengkap LIKE :kw OR nis LIKE :kw OR kelas LIKE :kw OR username LIKE :kw
        ORDER BY nama_lengkap ASC
    ");
    $stmt->execute(['kw' => '%' . $keyword . '%']);
    $daftarAnggota = $stmt->fetchAll();
} else {
    $daftarAnggota = $koneksi->query("SELECT * FROM anggota ORDER BY nama_lengkap ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Anggota</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>


  <div class="container">
    <div class="page-head">
      <div>
        <h1>🎓 Kelola Anggota</h1>
        <p>Total <?= count($daftarAnggota) ?> anggota ditampilkan<?= $keyword !== '' ? ' untuk pencarian "' . htmlspecialchars($keyword) . '"' : '' ?>.</p>
      </div>
      <a href="tambah_anggota.php" class="btn">+ Tambah Anggota Baru</a>
    </div>

    <div class="card">
      <form method="GET" action="anggota.php" class="search-form">
        <input type="text" name="q" placeholder="Cari NIS, nama, kelas, atau username..."
               value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit" class="btn btn-outline">🔍 Cari</button>
        <?php if ($keyword !== ''): ?>
          <a href="anggota.php" class="btn-link">✕ Reset</a>
        <?php endif; ?>
      </form>

      <table>
        <thead>
          <tr>
            <th>NIS</th>
            <th>Nama</th>
            <th>Kelas</th>
            <th>No HP</th>
            <th>Username</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarAnggota as $a): ?>
          <tr>
            <td data-label="NIS"><?= htmlspecialchars($a['nis']) ?></td>
            <td data-label="Nama" style="font-weight:600;"><?= htmlspecialchars($a['nama_lengkap']) ?></td>
            <td data-label="Kelas"><?= htmlspecialchars($a['kelas']) ?></td>
            <td data-label="No HP"><?= htmlspecialchars($a['no_hp']) ?></td>
            <td data-label="Username"><?= htmlspecialchars($a['username']) ?></td>
            <td data-label="Status">
              <?php if ($a['status'] === 'aktif'): ?>
                <span class="badge badge-ok"><?= htmlspecialchars($a['status']) ?></span>
              <?php else: ?>
                <span class="badge badge-habis"><?= htmlspecialchars($a['status']) ?></span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <a href="edit_anggota.php?id=<?= $a['id_anggota'] ?>" class="btn-link">Edit</a> ·
              <a href="hapus_anggota.php?id=<?= $a['id_anggota'] ?>" class="btn-link" style="color:var(--coral);" onclick="return confirm('Yakin hapus anggota ini?')">Hapus</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarAnggota): ?>
          <tr><td colspan="7" style="text-align:center;">
            <?= $keyword !== '' ? 'Tidak ada anggota yang cocok dengan pencarian "' . htmlspecialchars($keyword) . '"' : 'Belum ada data anggota' ?>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
  </div>
</body>
</html>