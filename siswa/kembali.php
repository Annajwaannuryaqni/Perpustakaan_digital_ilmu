<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

const TARIF_DENDA_PER_HARI = 1000; // Rp1.000 / hari keterlambatan

$id_anggota = $_SESSION['anggota_id'];
$pesan = $_GET['pesan'] ?? '';

$stmt = $koneksi->prepare("
    SELECT t.*, b.judul, b.pengarang
    FROM transaksi t
    JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.id_anggota = ? AND t.status = 'dipinjam'
    ORDER BY t.tanggal_jatuh_tempo ASC
");
$stmt->execute([$id_anggota]);
$daftarPinjaman = $stmt->fetchAll();

// Buku yang baru saja dikembalikan, untuk ditawari rating & komentar
$bukuUntukRating = null;
$id_rate = $_GET['rate'] ?? null;
if ($id_rate) {
    $stmtRate = $koneksi->prepare("
        SELECT t.id_transaksi, b.judul, b.pengarang
        FROM transaksi t
        JOIN buku b ON b.id_buku = t.id_buku
        WHERE t.id_transaksi = ? AND t.id_anggota = ?
    ");
    $stmtRate->execute([$id_rate, $id_anggota]);
    $bukuUntukRating = $stmtRate->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pengembalian Buku</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div class="topbar">
    <h1> Pengembalian Buku</h1>
  </div>

  <div class="container">
    <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>

    <?php if ($pesan === 'sukses' && !$bukuUntukRating): ?>
      <p class="alert alert-sukses">Buku berhasil dikembalikan. Terima kasih!</p>
    <?php elseif ($pesan === 'rating_sukses'): ?>
      <p class="alert alert-sukses">Terima kasih atas rating &amp; komentarnya!</p>
    <?php endif; ?>

    <?php if ($bukuUntukRating): ?>
      <div class="card rating-card">
        <h3 style="margin-top:0;">Buku berhasil dikembalikan 🎉</h3>
        <p style="color:var(--muted); margin-top:-8px;">
          Beri rating &amp; ulasan singkat untuk "<strong><?= htmlspecialchars($bukuUntukRating['judul']) ?></strong>"
          karya <?= htmlspecialchars($bukuUntukRating['pengarang']) ?> (opsional, tapi sangat membantu siswa lain).
        </p>
        <form method="POST" action="proses_rating.php">
          <input type="hidden" name="id_transaksi" value="<?= $bukuUntukRating['id_transaksi'] ?>">

          <?= csrfField() ?>

          <div class="rating-section">
            <div class="rating-title">Berikan Rating</div>
            <div class="rating-stars" id="starInput" role="radiogroup" aria-label="Pilih rating buku">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <label class="rating-star" data-value="<?= $i ?>" title="<?= $i ?> dari 5">
                  <input type="radio" name="nilai" value="<?= $i ?>" <?= $i === 5 ? 'required' : '' ?>>
                  <span aria-hidden="true">★</span>
                </label>
              <?php endfor; ?>
            </div>
            <div class="rating-value-text" id="ratingValueText">Pilih rating 1–5</div>
          </div>

          <textarea name="isi_komentar" rows="3" placeholder="Tulis komentar/ulasan singkat tentang buku ini (opsional)..." style="width:100%; margin-top:12px;"></textarea>

          <div style="margin-top:12px; display:flex; gap:10px;">
            <button type="submit" class="btn">Kirim Rating</button>
            <a href="kembali.php" class="btn btn-outline">Lewati</a>
          </div>
        </form>
      </div>
      <script>
        (function(){
          var labels = {1:'Sangat Buruk',2:'Buruk',3:'Cukup',4:'Bagus',5:'Sangat Bagus'};
          var stars = document.querySelectorAll('#starInput .rating-star');
          var text = document.getElementById('ratingValueText');
          function paint(value, preview) {
            stars.forEach(function(star){
              var n = Number(star.getAttribute('data-value'));
              star.classList.toggle('active', n <= value);
              star.classList.toggle('preview', !!preview && n <= value);
            });
            text.textContent = value ? (value + ' dari 5 — ' + labels[value]) : 'Pilih rating 1–5';
          }
          stars.forEach(function(star){
            star.addEventListener('mouseenter', function(){ paint(Number(star.dataset.value), true); });
            star.addEventListener('focusin', function(){ paint(Number(star.dataset.value), true); });
            star.addEventListener('click', function(){
              var input = star.querySelector('input');
              input.checked = true;
              paint(Number(input.value), false);
            });
          });
          document.getElementById('starInput').addEventListener('mouseleave', function(){
            var checked = document.querySelector('#starInput input:checked');
            paint(checked ? Number(checked.value) : 0, false);
          });
          document.getElementById('starInput').addEventListener('focusout', function(){
            var checked = document.querySelector('#starInput input:checked');
            paint(checked ? Number(checked.value) : 0, false);
          });
          paint(0, false);
        })();
      </script>
    <?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Judul</th>
            <th>Tgl Pinjam</th>
            <th>Jatuh Tempo</th>
            <th>Status</th>
            <th>Denda</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarPinjaman as $p):
              $hariIni = strtotime(date('Y-m-d'));
              $jatuhTempo = strtotime($p['tanggal_jatuh_tempo']);
              $telat = $hariIni > $jatuhTempo;
              $hariTerlambat = $telat ? floor(($hariIni - $jatuhTempo) / 86400) : 0;
              $denda = $hariTerlambat * TARIF_DENDA_PER_HARI;
          ?>
          <tr>
            <td data-label="Judul"><?= htmlspecialchars($p['judul']) ?></td>
            <td data-label="Tgl Pinjam"><?= $p['tanggal_pinjam'] ?></td>
            <td data-label="Jatuh Tempo"><?= $p['tanggal_jatuh_tempo'] ?></td>
            <td data-label="Status">
              <span class="badge <?= $telat ? 'badge-habis' : 'badge-ok' ?>">
                <?= $telat ? "Terlambat $hariTerlambat hari" : 'Masih dalam batas waktu' ?>
              </span>
            </td>
            <td data-label="Denda">
              <?php if ($telat): ?>
                <span class="badge badge-habis">Rp<?= number_format($denda, 0, ',', '.') ?></span>
              <?php else: ?>
                <span style="color:#94a3b8;">-</span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <form method="POST" action="proses_kembali.php" onsubmit="return confirm('Kembalikan buku ini?<?= $telat ? " Denda: Rp" . number_format($denda, 0, ',', '.') : '' ?>')" style="margin:0;">
                <input type="hidden" name="id_transaksi" value="<?= $p['id_transaksi'] ?>">
                <?= csrfField() ?>
                <button type="submit" class="btn">Kembalikan</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarPinjaman): ?>
          <tr><td colspan="6" style="text-align:center;">Kamu tidak sedang meminjam buku apapun</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>