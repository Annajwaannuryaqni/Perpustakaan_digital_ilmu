<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

$MASA_PINJAM_HARI = 7;

$id_buku = (int)($_GET['id'] ?? $_POST['id_buku'] ?? 0);

// Ambil detail buku yang mau dipinjam
$stmt = $koneksi->prepare("
    SELECT b.*, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
    WHERE b.id_buku = ?
");
$stmt->execute([$id_buku]);
$buku = $stmt->fetch();

// Buku tidak ditemukan, lempar balik ke katalog
if (!$buku) {
    header('Location: pinjam.php?pesan=gagal');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $id_anggota          = $_SESSION['anggota_id'];
    $tanggal_pinjam       = date('Y-m-d');
    $tanggal_jatuh_tempo  = date('Y-m-d', strtotime("+{$MASA_PINJAM_HARI} days"));

    try {
        $koneksi->beginTransaction();

        // Kunci baris buku ini agar tidak ada request lain yang membaca stok basi
        // saat proses ini berjalan (mencegah race condition / peminjaman ganda).
        $cekBuku = $koneksi->prepare("SELECT id_buku, stok FROM buku WHERE id_buku = ? FOR UPDATE");
        $cekBuku->execute([$id_buku]);
        $stokTerkini = $cekBuku->fetch();

        if (!$stokTerkini || (int)$stokTerkini['stok'] < 1) {
            $koneksi->rollBack();
            header('Location: pinjam.php?pesan=gagal');
            exit;
        }

        // Kurangi stok secara atomik: hanya berhasil jika stok memang masih > 0
        // saat statement ini dieksekusi (lapisan tambahan selain FOR UPDATE di atas).
        $update = $koneksi->prepare("UPDATE buku SET stok = stok - 1 WHERE id_buku = ? AND stok > 0");
        $update->execute([$id_buku]);

        if ($update->rowCount() === 0) {
            $koneksi->rollBack();
            header('Location: pinjam.php?pesan=gagal');
            exit;
        }

        $insert = $koneksi->prepare("
            INSERT INTO transaksi (id_anggota, id_buku, tanggal_pinjam, tanggal_jatuh_tempo, status)
            VALUES (?, ?, ?, ?, 'dipinjam')
        ");
        $insert->execute([$id_anggota, $id_buku, $tanggal_pinjam, $tanggal_jatuh_tempo]);
        $id_transaksi = $koneksi->lastInsertId();

        $koneksi->commit();

        header('Location: bukti_peminjaman.php?id=' . $id_transaksi);
        exit;

    } catch (PDOException $e) {
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
        header('Location: pinjam.php?pesan=gagal');
        exit;
    }
}

$tanggalPinjamPreview      = date('d-m-Y');
$tanggalJatuhTempoPreview  = date('d-m-Y', strtotime("+{$MASA_PINJAM_HARI} days"));
$stokTersedia = (int)$buku['stok'] > 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Konfirmasi Peminjaman - <?= htmlspecialchars($buku['judul']) ?></title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .confirm-wrap { max-width: 720px; margin: 0 auto; }

  .confirm-steps {
    display: flex; align-items: center; justify-content: center;
    gap: 8px; margin-bottom: 22px;
  }
  .confirm-steps .step { display: flex; align-items: center; gap: 8px; }
  .confirm-steps .step .dot {
    width: 24px; height: 24px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .72rem; font-weight: 700;
    background: var(--slate-100); color: var(--muted);
    border: 1px solid var(--border);
  }
  .confirm-steps .step.done .dot { background: rgba(22,163,74,.12); color: var(--success); border-color: transparent; }
  .confirm-steps .step.done .dot svg { width: 12px; height: 12px; }
  .confirm-steps .step.active .dot { background: var(--royal-600); color: #fff; border-color: transparent; }
  .confirm-steps .step .label { font-size: .78rem; color: var(--muted); font-weight: 600; }
  .confirm-steps .step.active .label { color: var(--navy); }
  .confirm-steps .line { width: 32px; height: 1px; background: var(--border); }

  .confirm-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-card); box-shadow: var(--shadow-card);
    padding: 26px; overflow: hidden;
  }

  .confirm-book { display: flex; gap: 20px; }
  .confirm-cover {
    flex-shrink: 0; width: 120px; aspect-ratio: 3/4;
    border-radius: 10px; overflow: hidden; background: var(--slate-100);
    box-shadow: var(--shadow-card);
    display: flex; align-items: center; justify-content: center;
  }
  .confirm-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .confirm-cover .no-cover-ic { color: var(--muted); }
  .confirm-cover .no-cover-ic svg { width: 30px; height: 30px; }

  .confirm-book-info { flex: 1; min-width: 0; }
  .confirm-genre {
    display: inline-block; font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em;
    color: var(--royal-600); background: rgba(22,88,201,.08);
    padding: 3px 9px; border-radius: 999px; margin-bottom: 8px;
  }
  .confirm-title {
    font-family: 'Poppins', sans-serif; font-weight: 700;
    font-size: 1.2rem; color: var(--navy); line-height: 1.3; margin: 0 0 4px;
  }
  .confirm-author { font-size: .86rem; color: var(--muted); margin-bottom: 12px; }
  .confirm-stock { display: flex; align-items: center; gap: 6px; font-size: .8rem; font-weight: 600; }
  .confirm-stock.ok { color: var(--success); }
  .confirm-stock.habis { color: var(--danger); }
  .confirm-stock svg { width: 15px; height: 15px; }

  .confirm-meta {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 12px 20px; margin-top: 22px; padding-top: 20px;
    border-top: 1px dashed var(--border);
  }
  .confirm-meta-item span { display: block; font-size: .72rem; color: var(--muted); margin-bottom: 3px; }
  .confirm-meta-item strong { font-size: .86rem; color: var(--navy); font-weight: 600; }

  .confirm-period {
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    background: rgba(22,88,201,.05); border: 1px solid rgba(22,88,201,.15);
    border-radius: 12px; padding: 14px 18px; margin-top: 20px;
  }
  .confirm-period-item { text-align: center; flex: 1; }
  .confirm-period-item span { display: block; font-size: .7rem; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; margin-bottom: 4px; }
  .confirm-period-item strong { font-family: 'Poppins', sans-serif; font-size: .95rem; color: var(--navy); font-weight: 700; }
  .confirm-period-arrow { color: var(--royal-600); flex-shrink: 0; }
  .confirm-period-arrow svg { width: 18px; height: 18px; }
  .confirm-period-days {
    text-align: center; font-size: .74rem; color: var(--royal-600);
    font-weight: 700; margin-top: 10px;
  }

  .confirm-deskripsi {
    margin-top: 20px; padding-top: 18px; border-top: 1px dashed var(--border);
  }
  .confirm-deskripsi h4 {
    font-size: .72rem; text-transform: uppercase; letter-spacing: .05em;
    color: var(--muted); margin: 0 0 8px; font-weight: 700;
  }
  .confirm-deskripsi p { font-size: .84rem; color: var(--text); line-height: 1.6; margin: 0; }

  .confirm-notice {
    display: flex; gap: 10px; align-items: flex-start;
    background: rgba(217,119,6,.07); border: 1px solid rgba(217,119,6,.18);
    border-radius: 10px; padding: 12px 14px; margin-top: 20px;
    font-size: .78rem; color: #92650a; line-height: 1.5;
  }
  .confirm-notice svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }

  .confirm-actions { display: flex; gap: 12px; margin-top: 24px; }
  .confirm-actions a, .confirm-actions button { flex: 1; text-align: center; }

  .confirm-alert-gagal {
    display: flex; gap: 10px; align-items: flex-start;
    background: rgba(220,38,38,.06); border: 1px solid rgba(220,38,38,.2);
    color: var(--danger); border-radius: 10px; padding: 12px 14px;
    margin-bottom: 18px; font-size: .84rem;
  }
  .confirm-alert-gagal svg { width: 17px; height: 17px; flex-shrink: 0; margin-top: 1px; }

  @media (max-width: 560px) {
    .confirm-book { flex-direction: column; align-items: center; text-align: center; }
    .confirm-cover { width: 150px; }
    .confirm-genre, .confirm-stock { justify-content: center; }
    .confirm-stock { justify-content: center; }
    .confirm-meta { grid-template-columns: 1fr 1fr; }
  }
</style>
</head>
<body class="admin-page">
  <?php $activeMenu = 'pinjam'; require '../includes/siswa_sidebar.php'; ?>
  <div class="topbar">
    <h1>Konfirmasi Peminjaman</h1>
  </div>

  <div class="container confirm-wrap">

    <div class="confirm-steps">
      <div class="step done">
        <span class="dot"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
        <span class="label">Pilih Buku</span>
      </div>
      <span class="line"></span>
      <div class="step active">
        <span class="dot">2</span>
        <span class="label">Konfirmasi</span>
      </div>
      <span class="line"></span>
      <div class="step">
        <span class="dot">3</span>
        <span class="label">Selesai</span>
      </div>
    </div>

    <?php if (!$stokTersedia): ?>
      <div class="confirm-alert-gagal">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5"/><path d="M12 16.2h.01"/></svg>
        <span>Maaf, stok buku ini sudah habis dan tidak bisa dipinjam saat ini.</span>
      </div>
    <?php endif; ?>

    <div class="confirm-card">
      <div class="confirm-book">
        <div class="confirm-cover">
          <?php if ($buku['cover']): ?>
            <img src="../uploads/<?= htmlspecialchars($buku['cover']) ?>" alt="<?= htmlspecialchars($buku['judul']) ?>">
          <?php else: ?>
            <span class="no-cover-ic">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/></svg>
            </span>
          <?php endif; ?>
        </div>

        <div class="confirm-book-info">
          <span class="confirm-genre"><?= htmlspecialchars($buku['nama_kategori'] ?? 'Lainnya') ?></span>
          <h2 class="confirm-title"><?= htmlspecialchars($buku['judul']) ?></h2>
          <div class="confirm-author">oleh <?= htmlspecialchars($buku['pengarang']) ?></div>

          <?php if ($stokTersedia): ?>
            <div class="confirm-stock ok">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><polyline points="8 12.5 11 15.5 16 9"/></svg>
              <?= (int)$buku['stok'] ?> stok tersedia
            </div>
          <?php else: ?>
            <div class="confirm-stock habis">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9.5"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              Stok habis
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="confirm-meta">
        <div class="confirm-meta-item"><span>Kode Buku</span><strong><?= htmlspecialchars($buku['kode_buku'] ?? '-') ?></strong></div>
        <div class="confirm-meta-item"><span>Penerbit</span><strong><?= htmlspecialchars($buku['penerbit'] ?? '-') ?></strong></div>
        <div class="confirm-meta-item"><span>Tahun Terbit</span><strong><?= htmlspecialchars($buku['tahun_terbit'] ?? '-') ?></strong></div>
        <div class="confirm-meta-item"><span>Lokasi Rak</span><strong><?= htmlspecialchars($buku['lokasi_rak'] ?? '-') ?></strong></div>
      </div>

      <?php if ($stokTersedia): ?>
      <div class="confirm-period">
        <div class="confirm-period-item">
          <span>Tanggal Pinjam</span>
          <strong><?= $tanggalPinjamPreview ?></strong>
        </div>
        <div class="confirm-period-arrow">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </div>
        <div class="confirm-period-item">
          <span>Jatuh Tempo</span>
          <strong><?= $tanggalJatuhTempoPreview ?></strong>
        </div>
      </div>
      <div class="confirm-period-days">Masa peminjaman <?= $MASA_PINJAM_HARI ?> hari</div>
      <?php endif; ?>

      <?php if (!empty($buku['deskripsi'])): ?>
      <div class="confirm-deskripsi">
        <h4>Deskripsi</h4>
        <p><?= nl2br(htmlspecialchars($buku['deskripsi'])) ?></p>
      </div>
      <?php endif; ?>

      <div class="confirm-notice">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 16.5h.01"/><path d="M10.3 3.9 2.6 17.5a1.8 1.8 0 0 0 1.6 2.7h15.6a1.8 1.8 0 0 0 1.6-2.7L13.7 3.9a1.8 1.8 0 0 0-3.4 0Z"/></svg>
        <span>Pastikan mengembalikan buku sebelum tanggal jatuh tempo untuk menghindari denda keterlambatan.</span>
      </div>

      <div class="confirm-actions">
        <a href="pinjam.php" class="btn-outline btn">Batal</a>
        <?php if ($stokTersedia): ?>
          <form method="POST" action="pinjam_konfirmasi.php?id=<?= $id_buku ?>" style="flex:1;">
            <?= csrfField() ?>
            <input type="hidden" name="id_buku" value="<?= $id_buku ?>">
            <button type="submit" class="btn" style="width:100%;">Konfirmasi Pinjam</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

  </div>
</body>
</html>