<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$id_anggota = $_SESSION['anggota_id'];
$id_buku = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_buku) {
    header('Location: pinjam.php');
    exit;
}

// Ambil data buku terbaru dari database (bukan dari input siswa)
$stmt = $koneksi->prepare("
    SELECT b.*, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
    WHERE b.id_buku = ?
");
$stmt->execute([$id_buku]);
$buku = $stmt->fetch();

if (!$buku) {
    header('Location: pinjam.php');
    exit;
}

// Data peminjam berasal dari sesi yang sedang login, bukan input manual
$stmtAnggota = $koneksi->prepare("SELECT nama_lengkap, nis, kelas FROM anggota WHERE id_anggota = ?");
$stmtAnggota->execute([$id_anggota]);
$anggota = $stmtAnggota->fetch();

$tanggalPinjamPreview = date('Y-m-d');
$jatuhTempoPreview = date('Y-m-d', strtotime('+7 days'));
$tersedia = (int)$buku['stok'] > 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Konfirmasi Peminjaman</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .confirm-wrap { max-width: 640px; margin: 0 auto; }
  .confirm-section { border-top: 1px solid var(--border); padding-top: 18px; margin-top: 18px; }
  .confirm-section:first-child { border-top: none; padding-top: 0; margin-top: 0; }
  .confirm-section h4 {
    font-size: .78rem; text-transform: uppercase; letter-spacing: .05em;
    color: var(--muted); margin: 0 0 12px; font-weight: 700;
  }
  .book-row { display: flex; gap: 16px; align-items: center; }
  .book-row img, .book-row .no-cover-box {
    width: 64px; height: 86px; object-fit: cover; border-radius: 8px;
    box-shadow: var(--shadow-card); flex-shrink: 0; background: var(--slate-100);
  }
  .book-row .no-cover-box { display: flex; align-items: center; justify-content: center; font-size: .65rem; color: var(--muted); text-align: center; }
  .book-row .book-title { font-weight: 700; color: var(--navy); font-size: 1rem; }
  .book-row .book-meta { font-size: .82rem; color: var(--muted); margin-top: 2px; }
  .kv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; font-size: .88rem; }
  .kv-grid div span { display: block; color: var(--muted); font-size: .72rem; margin-bottom: 2px; }
  .confirm-actions { display: flex; gap: 12px; margin-top: 24px; }
  .confirm-actions form, .confirm-actions a { flex: 1; }
  .confirm-actions .btn, .confirm-actions .btn-outline { width: 100%; }
</style>
</head>
<body>
  <div class="topbar"><h1>Konfirmasi Peminjaman</h1></div>

  <div class="container confirm-wrap">
    <div class="card">
      <div class="confirm-section">
        <h4>Data Buku</h4>
        <div class="book-row">
          <?php if ($buku['cover']): ?>
            <img src="../uploads/<?= htmlspecialchars($buku['cover']) ?>" alt="">
          <?php else: ?>
            <div class="no-cover-box">Tanpa cover</div>
          <?php endif; ?>
          <div>
            <div class="book-title"><?= htmlspecialchars($buku['judul']) ?></div>
            <div class="book-meta">oleh <?= htmlspecialchars($buku['pengarang']) ?></div>
            <div class="book-meta"><?= htmlspecialchars($buku['nama_kategori'] ?? 'Lainnya') ?></div>
            <div style="margin-top:6px;">
              <?php if ($tersedia): ?>
                <span class="badge badge-ok">Tersedia (<?= (int)$buku['stok'] ?> stok)</span>
              <?php else: ?>
                <span class="badge badge-habis">Tidak Tersedia</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="confirm-section">
        <h4>Data Peminjam</h4>
        <div class="kv-grid">
          <div><span>Nama</span><?= htmlspecialchars($anggota['nama_lengkap']) ?></div>
          <div><span>ID Anggota (NIS)</span><?= htmlspecialchars($anggota['nis']) ?></div>
          <div><span>Kelas</span><?= htmlspecialchars($anggota['kelas'] ?: '-') ?></div>
        </div>
      </div>

      <?php if ($tersedia): ?>
      <div class="confirm-section">
        <h4>Detail Peminjaman</h4>
        <div class="kv-grid">
          <div><span>Tanggal Peminjaman</span><?= htmlspecialchars($tanggalPinjamPreview) ?></div>
          <div><span>Tanggal Jatuh Tempo</span><?= htmlspecialchars($jatuhTempoPreview) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="confirm-actions">
        <a href="pinjam.php" class="btn-outline btn">Batalkan</a>
        <?php if ($tersedia): ?>
          <form method="POST" action="proses_pinjam.php" id="formKonfirmasi">
            <?= csrfField() ?>
            <input type="hidden" name="id_buku" value="<?= $buku['id_buku'] ?>">
            <button type="submit" class="btn" id="btnKonfirmasi">Konfirmasi Peminjaman</button>
          </form>
        <?php else: ?>
          <button class="btn" disabled style="opacity:.5; cursor:not-allowed;">Tidak Tersedia</button>
        <?php endif; ?>
      </div>
    </div>

    <a href="pinjam.php" class="back-link" style="display:block; margin-top:20px; text-align:center;">&larr; Kembali ke Katalog</a>
  </div>

  <script>
    // Cegah submit ganda akibat klik berkali-kali
    var formKonfirmasi = document.getElementById('formKonfirmasi');
    if (formKonfirmasi) {
      formKonfirmasi.addEventListener('submit', function () {
        document.getElementById('btnKonfirmasi').disabled = true;
        document.getElementById('btnKonfirmasi').textContent = 'Memproses...';
      });
    }
  </script>
</body>
</html>
