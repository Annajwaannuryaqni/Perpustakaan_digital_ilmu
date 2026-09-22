<?php
require_once '../includes/auth.php';
requirePetugas();
require_once '../config/database.php';

$pesan = $_GET['pesan'] ?? '';

$daftarDenda = $koneksi->query("
    SELECT t.*, a.nama_lengkap AS nama_anggota, a.nis, a.kelas, b.judul, b.deleted_at,
           p.metode_pembayaran, p.file_bukti, p.uploaded_at AS bukti_uploaded_at, p.status AS status_bukti, p.catatan AS catatan_bukti
    FROM transaksi t
    JOIN anggota a ON a.id_anggota = t.id_anggota
    LEFT JOIN buku b ON b.id_buku = t.id_buku
    LEFT JOIN pembayaran_denda p ON p.id_transaksi = t.id_transaksi
    WHERE t.denda > 0
    ORDER BY t.status_denda ASC, t.tanggal_kembali DESC
")->fetchAll();

$totalBelumLunas = 0;
$totalTerkumpul = 0;
foreach ($daftarDenda as $d) {
    if ($d['status_denda'] === 'Belum Lunas') {
        $totalBelumLunas += (float)$d['denda'];
    } elseif ($d['status_denda'] === 'Lunas') {
        $totalTerkumpul += (float)$d['denda'];
    }
}

$activeMenu = 'denda';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Denda - Panel Petugas</title>
<link rel="stylesheet" href="../assets/style.css">

<style>
.bukti-modal{display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.78);align-items:center;justify-content:center;padding:24px;}
.bukti-modal.show{display:flex;}
.bukti-modal-card{position:relative;width:min(960px,96vw);height:min(90vh,900px);background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.28);overflow:hidden;display:flex;flex-direction:column;}
.bukti-modal-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;border-bottom:1px solid #e5e7eb;background:#fff;}
.bukti-modal-title{font-weight:800;color:#0f172a;}
.bukti-modal-close{width:38px;height:38px;border:0;border-radius:10px;background:#f1f5f9;color:#0f172a;font-size:24px;line-height:1;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;}
.bukti-modal-close:hover{background:#e2e8f0;}
.bukti-modal-body{flex:1;min-height:0;background:#0f172a;display:flex;align-items:center;justify-content:center;padding:16px;}
.bukti-modal-body img{max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;background:#fff;}
.bukti-modal-body iframe{width:100%;height:100%;border:0;background:#fff;border-radius:8px;}
@media(max-width:640px){.bukti-modal{padding:10px}.bukti-modal-card{width:100%;height:94vh;border-radius:14px}.bukti-modal-body{padding:8px}}
</style>

</head>
<body class="admin-page">
  <?php require_once '../includes/petugas_sidebar.php'; ?>

  <div class="container">
    <div class="page-head">
      <div>
        <h1>Denda Keterlambatan</h1>
        <p>Denda belum lunas: <strong>Rp<?= number_format($totalBelumLunas, 0, ',', '.') ?></strong> &nbsp; | &nbsp; <strong>Sudah terkumpul: Rp<?= number_format($totalTerkumpul, 0, ',', '.') ?></strong></p>
      </div>
    </div>

    <?php if ($pesan === 'kembali_denda'): ?>
      <p class="alert alert-sukses">Buku berhasil dikembalikan, tetapi ada denda keterlambatan. Silakan proses pembayaran denda di bawah ini.</p>
    <?php endif; ?>

    <?php if ($pesan === 'denda_lunas'): ?>
      <p class="alert alert-sukses">Denda berhasil ditandai lunas setelah bukti pembayaran diverifikasi.</p>
    <?php elseif ($pesan === 'bukti_belum_ada'): ?>
      <p class="alert" style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;">Bukti pembayaran belum diunggah siswa, jadi denda belum dapat ditandai lunas.</p>
    <?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Anggota</th>
            <th>Kelas</th>
            <th>Judul Buku</th>
            <th>Tgl Kembali</th>
            <th>Denda</th>
            <th>Status Bayar</th>
            <th>Metode</th>
            <th>Bukti Pembayaran</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarDenda as $d): ?>
          <tr>
            <td data-label="Anggota" style="font-weight:600;"><?= htmlspecialchars($d['nama_anggota']) ?> <br><small style="color:var(--muted); font-weight:400;">NIS <?= htmlspecialchars($d['nis']) ?></small></td>
            <td data-label="Kelas"><?= htmlspecialchars($d['kelas']) ?></td>
            <td data-label="Judul Buku"><?= htmlspecialchars($d['judul'] ?? 'Buku tidak ditemukan') ?><?php if (!empty($d['deleted_at'])): ?> <span class="badge badge-habis">Diarsipkan</span><?php endif; ?></td>
            <td data-label="Tgl Kembali"><?= htmlspecialchars($d['tanggal_kembali'] ?? '-') ?></td>
            <td data-label="Denda">Rp<?= number_format((float)$d['denda'], 0, ',', '.') ?></td>
            <td data-label="Status Bayar">
              <?php if ($d['status_denda'] === 'Lunas'): ?>
                <span class="badge badge-ok">Lunas<?= $d['tanggal_bayar_denda'] ? ' (' . htmlspecialchars($d['tanggal_bayar_denda']) . ')' : '' ?></span>
              <?php else: ?>
                <span class="badge badge-habis">Belum Lunas</span>
              <?php endif; ?>
            </td>
            <td data-label="Metode">
              <?php if (($d['metode_pembayaran'] ?? '') === 'cash'): ?>
                <span class="badge badge-ok">Cash</span>
              <?php elseif (($d['metode_pembayaran'] ?? '') === 'qris'): ?>
                <span class="badge badge-info">QRIS</span>
              <?php else: ?>
                <span style="color:var(--muted);">Belum dipilih</span>
              <?php endif; ?>
            </td>
            <td data-label="Bukti Pembayaran">
              <?php if (!empty($d['file_bukti'])): ?>
                <?php
  $buktiFile = basename($d['file_bukti']);
  $buktiUrl = '../uploads/bukti_pembayaran/' . rawurlencode($buktiFile);
  $buktiExt = strtolower(pathinfo($buktiFile, PATHINFO_EXTENSION));
  $buktiIsPdf = ($buktiExt === 'pdf');
?>
<button type="button" class="btn btn-outline btn-lihat-bukti"
        data-bukti-url="<?= htmlspecialchars($buktiUrl, ENT_QUOTES, 'UTF-8') ?>"
        data-bukti-type="<?= $buktiIsPdf ? 'pdf' : 'image' ?>"
        style="padding:6px 10px;">Lihat Bukti</button>
                <div style="margin-top:4px;font-size:.7rem;color:var(--muted);">
                  <?= $d['status_bukti'] === 'diterima' ? 'Sudah diverifikasi' : ($d['status_bukti'] === 'ditolak' ? 'Ditolak' : 'Menunggu verifikasi') ?>
                </div>
              <?php else: ?>
                <span style="color:var(--muted);">Belum ada</span>
              <?php endif; ?>
            </td>
            <td data-label="Aksi">
              <?php if ($d['status_denda'] === 'Belum Lunas'): ?>
                <?php if (($d['metode_pembayaran'] ?? '') === 'cash' && ($d['status_bukti'] ?? '') === 'menunggu_verifikasi'): ?>
                <form method="POST" action="bayar_denda.php" onsubmit="return confirm('Siswa sudah menyerahkan pembayaran cash? Konfirmasi dan tandai denda ini lunas?')" style="margin:0;">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $d['id_transaksi'] ?>">
                  <button type="submit" class="btn">Konfirmasi Cash &amp; Lunas</button>
                </form>
                <?php elseif (($d['metode_pembayaran'] ?? '') === 'qris' && !empty($d['file_bukti']) && ($d['status_bukti'] ?? '') !== 'ditolak'): ?>
                <form method="POST" action="bayar_denda.php" onsubmit="return confirm('Bukti QRIS sudah diperiksa dan sesuai? Tandai denda ini lunas?')" style="margin:0;">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $d['id_transaksi'] ?>">
                  <button type="submit" class="btn">Verifikasi QRIS &amp; Lunas</button>
                </form>
                <?php else: ?>
                  <span style="color:var(--muted);">Menunggu pembayaran/bukti</span>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:var(--muted);">-</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$daftarDenda): ?>
          <tr><td colspan="9" style="text-align:center;">Belum ada transaksi yang terkena denda.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  

<div class="bukti-modal" id="buktiModal" aria-hidden="true">
  <div class="bukti-modal-card" role="dialog" aria-modal="true" aria-labelledby="buktiModalTitle">
    <div class="bukti-modal-head">
      <div class="bukti-modal-title" id="buktiModalTitle">Bukti Pembayaran</div>
      <button type="button" class="bukti-modal-close" id="btnTutupBukti" aria-label="Tutup">&times;</button>
    </div>
    <div class="bukti-modal-body" id="buktiModalBody"></div>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById('buktiModal');
  const body = document.getElementById('buktiModalBody');
  const closeBtn = document.getElementById('btnTutupBukti');

  function tutupBukti(){
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden','true');
    body.innerHTML='';
    document.body.style.overflow='';
  }

  document.querySelectorAll('.btn-lihat-bukti').forEach(function(btn){
    btn.addEventListener('click', function(){
      const url = btn.getAttribute('data-bukti-url');
      const type = btn.getAttribute('data-bukti-type');
      if(type === 'pdf'){
        body.innerHTML = '<iframe title="Bukti pembayaran PDF" src="'+url+'"></iframe>';
      }else{
        const img = document.createElement('img');
        img.src = url;
        img.alt = 'Bukti pembayaran';
        body.appendChild(img);
      }
      modal.classList.add('show');
      modal.setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
      closeBtn.focus();
    });
  });

  closeBtn.addEventListener('click', tutupBukti);
  modal.addEventListener('click', function(e){
    if(e.target === modal) tutupBukti();
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && modal.classList.contains('show')) tutupBukti();
  });
})();
</script>

</main>
</body>
</html>