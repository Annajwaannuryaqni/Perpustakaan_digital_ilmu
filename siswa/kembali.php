<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';
require_once '../config/constants.php';

date_default_timezone_set('Asia/Jakarta');

$id_anggota=$_SESSION['anggota_id'];
$pesan=$_GET['pesan']??'';

$stmt=$koneksi->prepare("
SELECT t.*,b.judul,b.pengarang
FROM transaksi t
JOIN buku b ON b.id_buku=t.id_buku
WHERE t.id_anggota=? AND t.status IN ('dipinjam','menunggu_konfirmasi')
ORDER BY t.tanggal_jatuh_tempo ASC
");
$stmt->execute([$id_anggota]);
$daftarPinjaman=$stmt->fetchAll();

$totalDipinjam=count($daftarPinjaman);
$totalTerlambat=0;
$totalDendaKeseluruhan=0;

foreach($daftarPinjaman as $p){
    $hariIniHitung=strtotime(date('Y-m-d'));
    $jatuhTempoHitung=strtotime($p['tanggal_jatuh_tempo']);
    if($hariIniHitung>$jatuhTempoHitung){
        $totalTerlambat++;
        $totalDendaKeseluruhan+=floor(($hariIniHitung-$jatuhTempoHitung)/86400)*TARIF_DENDA_PER_HARI;
    }
}

$bukuUntukRating=null;
$id_rate=$_GET['rate']??null;

if($id_rate){
    // Form rating hanya boleh muncul kalau:
    // 1. Transaksi ini benar-benar milik siswa yang login
    // 2. Statusnya sudah selesai (dikembalikan/terlambat) — bukan yang masih dipinjam
    // 3. Belum pernah ada komentar untuk transaksi ini — supaya siswa tidak bisa
    //    membuka ulang link ?rate=X berkali-kali dan mengirim komentar berulang
    //    untuk buku yang sama.
    $stmtRate=$koneksi->prepare("
    SELECT t.id_transaksi,b.judul,b.pengarang
    FROM transaksi t
    JOIN buku b ON b.id_buku=t.id_buku
    WHERE t.id_transaksi=? AND t.id_anggota=? AND t.status IN ('dikembalikan','terlambat')
    AND NOT EXISTS (SELECT 1 FROM rating r WHERE r.id_transaksi = t.id_transaksi)
    ");
    $stmtRate->execute([$id_rate,$id_anggota]);
    $bukuUntukRating=$stmtRate->fetch();
}

// Ambil buku yang sudah selesai dikembalikan tapi belum diberi rating,
// supaya siswa punya jalan masuk ke form rating (sebelumnya tidak ada link kesini sama sekali)
$stmtBelumRating = $koneksi->prepare("
    SELECT t.id_transaksi, b.judul, b.pengarang
    FROM transaksi t
    JOIN buku b ON b.id_buku = t.id_buku
    WHERE t.id_anggota = ? AND t.status IN ('dikembalikan','terlambat')
    AND NOT EXISTS (SELECT 1 FROM rating r WHERE r.id_transaksi = t.id_transaksi)
    ORDER BY t.tanggal_kembali DESC
");
$stmtBelumRating->execute([$id_anggota]);
$daftarBelumRating = $stmtBelumRating->fetchAll();

$namaBulan=[
1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];

$tanggalIndonesia=date('d').' '.$namaBulan[(int)date('n')].' '.date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pengembalian Buku | Perpustakaan Digital Ilmu</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.kembali-topbar{min-height:86px!important;padding:0 38px 0 210px!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:20px}
.topbar-info{display:flex;flex-direction:column;gap:3px}
.topbar-brand{font-size:17px;font-weight:800;color:#fff}
.topbar-page{font-size:13px;color:rgba(255,255,255,.72)}
.kembali-wrapper{max-width:1100px;margin:0 auto;padding-bottom:50px}
.kembali-hero{margin-top:26px;padding:27px 28px;border-radius:18px;background:linear-gradient(135deg,#fff,#f8fbff);border:1px solid #e2e8f0;box-shadow:0 8px 25px rgba(15,23,42,.05)}
.kembali-hero h1{margin:0 0 7px;font-size:25px;color:#0f172a;letter-spacing:-.5px}
.kembali-hero p{margin:0;color:#64748b;font-size:14px;line-height:1.6}
.summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:13px;margin-top:22px}
.summary-card{min-width:0;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;display:flex;align-items:center;gap:12px;box-shadow:0 4px 14px rgba(15,23,42,.035)}
.summary-icon{width:42px;height:42px;flex:0 0 42px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:#eff6ff;color:#0284c7}
.summary-icon svg{width:21px;height:21px}
.summary-card.warning .summary-icon{background:#fffbeb;color:#d97706}
.summary-card.danger .summary-icon{background:#fef2f2;color:#dc2626}
.summary-content{min-width:0}
.summary-content strong{display:block;color:#0f172a;font-size:19px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.summary-content span{display:block;margin-top:3px;color:#94a3b8;font-size:10px;line-height:1.3}
.page-alert{margin-top:18px;padding:13px 16px;border-radius:12px;font-size:13px;font-weight:600}
.page-alert.success{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.rating-card{margin-top:18px!important;padding:23px!important;border:1px solid #dbeafe!important;background:linear-gradient(135deg,#fff,#f8fbff)!important}
.rating-header{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.rating-icon{width:42px;height:42px;display:flex;align-items:center;justify-content:center;border-radius:11px;background:#fef3c7;color:#d97706;flex:0 0 42px}
.rating-icon svg{width:22px;height:22px}
.rating-title-main{margin:0;color:#0f172a;font-size:17px}
.rating-subtitle{margin:3px 0 0;color:#64748b;font-size:12px;line-height:1.5}
.rating-book{margin:13px 0;padding:12px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0}
.rating-book strong{display:block;color:#334155;font-size:13px}
.rating-book span{display:block;margin-top:3px;color:#94a3b8;font-size:11px}
.rating-section{margin-top:17px}
.rating-label{font-size:12px;font-weight:700;color:#475569}
.rating-stars{display:flex;gap:5px;margin-top:7px}
.rating-star{position:relative;cursor:pointer}
.rating-star input{position:absolute;opacity:0;width:1px;height:1px}
.rating-star span{font-size:31px;color:#cbd5e1;line-height:1;transition:.15s}
.rating-star.active span,.rating-star.preview span{color:#f59e0b}
.rating-value-text{margin-top:7px;color:#94a3b8;font-size:11px}
.rating-card textarea{box-sizing:border-box;width:100%;margin-top:14px!important;padding:11px 12px;border:1px solid #dbe3ec;border-radius:10px;outline:none;resize:vertical;font-family:inherit;font-size:12px;color:#334155;background:#fff}
.rating-card textarea:focus{border-color:#93c5fd;box-shadow:0 0 0 3px rgba(59,130,246,.08)}
.rating-actions{display:flex;gap:8px;margin-top:12px}
.rating-actions .btn{padding:9px 15px;border-radius:8px;font-size:11px}
.rating-actions .btn-outline{background:#fff;border:1px solid #dbe3ec;color:#475569}
.borrow-section{margin-top:25px}
.section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:13px}
.section-heading h2{margin:0;font-size:17px;color:#0f172a}
.section-heading span{font-size:11px;color:#94a3b8}
.borrow-table{overflow:hidden;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 6px 20px rgba(15,23,42,.045)}
.borrow-table table{width:100%;border-collapse:collapse}
.borrow-table th{padding:13px 14px;background:#f8fafc;color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.3px;text-align:left;border-bottom:1px solid #e2e8f0}
.borrow-table td{padding:14px;color:#475569;font-size:12px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.borrow-table tr:last-child td{border-bottom:0}
.book-title{font-weight:700;color:#0f172a;line-height:1.4}
.book-author{margin-top:3px;color:#94a3b8;font-size:10px}
.date-text{white-space:nowrap;color:#64748b}
.status-badge{display:inline-flex;align-items:center;padding:6px 9px;border-radius:999px;font-size:10px;font-weight:700;white-space:nowrap}
.status-ok{background:#ecfdf5;color:#047857}
.status-late{background:#fef2f2;color:#b91c1c}
.fine-badge{display:inline-flex;padding:6px 9px;border-radius:7px;background:#fef2f2;color:#b91c1c;font-size:10px;font-weight:700}
.return-btn{padding:8px 11px;border-radius:8px;font-size:10px;white-space:nowrap}
.empty-borrow{padding:55px 20px;text-align:center}
.empty-icon-custom{width:58px;height:58px;margin:0 auto 13px;display:flex;align-items:center;justify-content:center;border-radius:16px;background:#f1f5f9;color:#94a3b8}
.empty-icon-custom svg{width:29px;height:29px}
.empty-borrow strong{display:block;color:#334155;font-size:14px}
.empty-borrow span{display:block;margin-top:5px;color:#94a3b8;font-size:11px}
@media(max-width:700px){
.kembali-topbar{min-height:76px!important;padding:0 16px 0 88px!important}
.topbar-brand{font-size:14px}
.topbar-page{font-size:11px}
.kembali-wrapper{padding-left:14px;padding-right:14px}
.kembali-hero{margin-top:18px;padding:21px 18px;border-radius:16px}
.kembali-hero h1{font-size:22px}
.kembali-hero p{font-size:12px}
.summary-grid{grid-template-columns:1fr;gap:9px;margin-top:17px}
.summary-card{padding:12px}
.summary-icon{width:38px;height:38px;flex-basis:38px}
.summary-content strong{font-size:17px}
.summary-content span{font-size:10px}
.rating-card{padding:18px!important}
.rating-header{align-items:flex-start}
.rating-icon{width:38px;height:38px;flex-basis:38px}
.rating-title-main{font-size:15px}
.rating-subtitle{font-size:11px}
.rating-stars{gap:4px}
.rating-star span{font-size:28px}
.borrow-section{margin-top:21px}
.section-heading h2{font-size:15px}
.borrow-table{border-radius:14px}
.borrow-table table,.borrow-table thead,.borrow-table tbody,.borrow-table tr,.borrow-table th,.borrow-table td{display:block}
.borrow-table thead{display:none}
.borrow-table tr{padding:15px;border-bottom:1px solid #e2e8f0}
.borrow-table tr:last-child{border-bottom:0}
.borrow-table td{position:relative;display:flex;align-items:center;justify-content:space-between;gap:15px;padding:7px 0;border:0;text-align:right}
.borrow-table td:before{content:attr(data-label);font-size:10px;font-weight:700;color:#94a3b8;text-align:left;flex:0 0 90px}
.borrow-table td[data-label="Judul"]{display:block;padding-bottom:11px;text-align:left}
.borrow-table td[data-label="Judul"]:before{display:none}
.borrow-table td[data-label="Judul"] .book-title{font-size:13px}
.borrow-table td[data-label="Judul"] .book-author{font-size:10px}
.borrow-table td[data-label="Aksi"]{padding-top:11px;margin-top:4px;border-top:1px solid #f1f5f9}
.borrow-table td[data-label="Aksi"]:before{display:none}
.borrow-table td[data-label="Aksi"] form{width:100%}
.return-btn{width:100%;height:37px}
.date-text{font-size:11px}
}
</style>
</head>
<body class="admin-page">

<?php $activeMenu='kembali';require '../includes/siswa_sidebar.php'; ?>

<div class="topbar kembali-topbar">
    <div class="topbar-info">
        <span class="topbar-brand">Perpustakaan Digital Ilmu</span>
        <span class="topbar-page">Pengembalian & Riwayat Peminjaman</span>
    </div>

</div>

<main>
<div class="container">
<div class="kembali-wrapper">

<div class="kembali-hero">
    <h1>Pengembalian Buku</h1>
    <p>Kelola buku yang sedang kamu pinjam, cek jatuh tempo, dan pantau denda pengembalian.</p>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5V5a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v14"/>
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H19"/>
                </svg>
            </div>
            <div class="summary-content">
                <strong><?= $totalDipinjam ?></strong>
                <span>Total Buku Dipinjam</span>
            </div>
        </div>

        <div class="summary-card warning">
            <div class="summary-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9.5"/>
                    <polyline points="12 7 12 12 15.5 14"/>
                </svg>
            </div>
            <div class="summary-content">
                <strong><?= $totalTerlambat ?></strong>
                <span>Buku Terlambat</span>
            </div>
        </div>

        <div class="summary-card danger">
            <div class="summary-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div class="summary-content">
                <strong>Rp<?= number_format($totalDendaKeseluruhan,0,',','.') ?></strong>
                <span>Total Denda Berjalan</span>
            </div>
        </div>
    </div>
</div>

<?php if($pesan==='diajukan'): ?>
<div class="page-alert success">✓ Pengajuan pengembalian terkirim. Serahkan bukunya ke petugas untuk dikonfirmasi — denda (jika ada) baru dihitung final saat itu.</div>
<?php elseif($pesan==='sukses'&&!$bukuUntukRating): ?>
<div class="page-alert success">✓ Buku berhasil dikembalikan. Terima kasih!</div>
<?php elseif($pesan==='rating_sukses'): ?>
<div class="page-alert success">✓ Terima kasih atas rating dan komentarnya!</div>
<?php elseif($pesan==='gagal'): ?>
<div class="page-alert" style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;">✕ Gagal memproses pengembalian buku. Silakan coba lagi.</div>
<?php endif; ?>

<?php if($bukuUntukRating): ?>
<div class="card rating-card">
    <div class="rating-header">
        <div class="rating-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
        </div>
        <div>
            <h3 class="rating-title-main">Bagaimana menurutmu tentang buku ini?</h3>
            <p class="rating-subtitle">Rating dan ulasanmu dapat membantu siswa lain memilih buku.</p>
        </div>
    </div>

    <div class="rating-book">
        <strong><?= htmlspecialchars($bukuUntukRating['judul']) ?></strong>
        <span>oleh <?= htmlspecialchars($bukuUntukRating['pengarang']) ?></span>
    </div>

    <form method="POST" action="proses_rating.php">
        <input type="hidden" name="id_transaksi" value="<?= $bukuUntukRating['id_transaksi'] ?>">
        <?= csrfField() ?>

        <div class="rating-section">
            <div class="rating-label">Berikan Rating</div>

            <div class="rating-stars" id="starInput" role="radiogroup" aria-label="Pilih rating buku">
                <?php for($i=1;$i<=5;$i++): ?>
                <label class="rating-star" data-value="<?= $i ?>" title="<?= $i ?> dari 5">
                    <input type="radio" name="nilai" value="<?= $i ?>" <?= $i===5?'required':'' ?>>
                    <span aria-hidden="true">★</span>
                </label>
                <?php endfor; ?>
            </div>

            <div class="rating-value-text" id="ratingValueText">Pilih rating 1–5</div>
        </div>

        <textarea name="isi_komentar" rows="3" placeholder="Tulis komentar atau ulasan singkat tentang buku ini (opsional)..."></textarea>

        <div class="rating-actions">
            <button type="submit" class="btn">Kirim Rating</button>
            <a href="kembali.php" class="btn btn-outline">Lewati</a>
        </div>
    </form>
</div>

<script>
(function(){
const labels={1:'Sangat Buruk',2:'Buruk',3:'Cukup',4:'Bagus',5:'Sangat Bagus'};
const stars=document.querySelectorAll('#starInput .rating-star');
const text=document.getElementById('ratingValueText');

function paint(value,preview){
    stars.forEach(function(star){
        const n=Number(star.getAttribute('data-value'));
        star.classList.toggle('active',n<=value);
        star.classList.toggle('preview',!!preview&&n<=value);
    });
    text.textContent=value?(value+' dari 5 — '+labels[value]):'Pilih rating 1–5';
}

stars.forEach(function(star){
    star.addEventListener('mouseenter',function(){
        paint(Number(star.dataset.value),true);
    });
    star.addEventListener('focusin',function(){
        paint(Number(star.dataset.value),true);
    });
    star.addEventListener('click',function(){
        const input=star.querySelector('input');
        input.checked=true;
        paint(Number(input.value),false);
    });
});

document.getElementById('starInput').addEventListener('mouseleave',function(){
    const checked=document.querySelector('#starInput input:checked');
    paint(checked?Number(checked.value):0,false);
});

paint(0,false);
})();
</script>
<?php endif; ?>

<?php if($daftarBelumRating): ?>
<div class="borrow-section">
    <div class="section-heading">
        <h2>Beri Rating Buku yang Sudah Dikembalikan</h2>
        <span><?= count($daftarBelumRating) ?> buku</span>
    </div>
    <div class="borrow-table">
        <table>
            <thead><tr><th>Judul</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach($daftarBelumRating as $r): ?>
                <tr>
                    <td data-label="Judul">
                        <div class="book-title"><?= htmlspecialchars($r['judul']) ?></div>
                        <div class="book-author"><?= htmlspecialchars($r['pengarang']) ?></div>
                    </td>
                    <td data-label="Aksi">
                        <a href="kembali.php?rate=<?= $r['id_transaksi'] ?>" class="btn return-btn">Beri Rating</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="borrow-section">
    <div class="section-heading">
        <h2>Buku yang Sedang Dipinjam</h2>
        <span><?= $totalDipinjam ?> buku</span>
    </div>

    <div class="borrow-table">
        <?php if($daftarPinjaman): ?>
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
            <?php foreach($daftarPinjaman as $p):
                $hariIni=strtotime(date('Y-m-d'));
                $jatuhTempo=strtotime($p['tanggal_jatuh_tempo']);
                $telat=$hariIni>$jatuhTempo;
                $hariTerlambat=$telat?floor(($hariIni-$jatuhTempo)/86400):0;
                $denda=$hariTerlambat*TARIF_DENDA_PER_HARI;
            ?>
                <tr>
                    <td data-label="Judul">
                        <div class="book-title"><?= htmlspecialchars($p['judul']) ?></div>
                        <div class="book-author"><?= htmlspecialchars($p['pengarang']) ?></div>
                    </td>

                    <td data-label="Tgl Pinjam">
                        <span class="date-text"><?= htmlspecialchars($p['tanggal_pinjam']) ?></span>
                    </td>

                    <td data-label="Jatuh Tempo">
                        <span class="date-text"><?= htmlspecialchars($p['tanggal_jatuh_tempo']) ?></span>
                    </td>

                    <td data-label="Status">
                        <?php if($p['status']==='menunggu_konfirmasi'): ?>
                        <span class="status-badge status-late">Menunggu Konfirmasi Petugas</span>
                        <?php else: ?>
                        <span class="status-badge <?= $telat?'status-late':'status-ok' ?>">
                            <?= $telat?'Terlambat '.$hariTerlambat.' hari':'Masih dalam batas waktu' ?>
                        </span>
                        <?php endif; ?>
                    </td>

                    <td data-label="Denda">
                        <?php if($p['status']==='menunggu_konfirmasi'): ?>
                        <span style="color:#94a3b8">Dihitung saat konfirmasi</span>
                        <?php elseif($telat): ?>
                        <span class="fine-badge">Estimasi Rp<?= number_format($denda,0,',','.') ?></span>
                        <?php else: ?>
                        <span style="color:#94a3b8">-</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="Aksi">
                        <?php if($p['status']==='menunggu_konfirmasi'): ?>
                        <span style="color:#94a3b8;font-size:11px;">Sudah diajukan</span>
                        <?php else: ?>
                        <form method="POST" action="proses_kembali.php" onsubmit="return confirm('Ajukan pengembalian buku ini? Bawa fisik bukunya ke petugas untuk dikonfirmasi.<?= $telat?' Estimasi denda: Rp'.number_format($denda,0,',','.') : '' ?>')" style="margin:0">
                            <input type="hidden" name="id_transaksi" value="<?= $p['id_transaksi'] ?>">
                            <?= csrfField() ?>
                            <button type="submit" class="btn return-btn">Kembalikan</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-borrow">
            <div class="empty-icon-custom">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5V5a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v14"/>
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H19"/>
                </svg>
            </div>
            <strong>Tidak ada buku yang sedang dipinjam</strong>
            <span>Buku yang kamu pinjam akan muncul di sini.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

</div>
</div>
</main>

</body>
</html>