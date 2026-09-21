<?php
require_once '../includes/auth.php';
requireSiswa();
require_once '../config/database.php';

date_default_timezone_set('Asia/Jakarta');

$id_anggota=$_SESSION['anggota_id'];
$pesan='';

/* Jadwal operasional: Senin-Kamis 07:30-15:30, Jumat 07:30-15:00, Sabtu-Minggu tutup */
$hari=(int)date('N');
$jamSekarang=date('H:i:s');
$jamBuka=null;
$jamTutup=null;

if($hari>=1&&$hari<=4){
    $jamBuka='07:30:00';
    $jamTutup='15:30:00';
}elseif($hari===5){
    $jamBuka='07:30:00';
    $jamTutup='15:00:00';
}

$perpustakaanBuka=false;
if($jamBuka!==null&&$jamTutup!==null){
    $perpustakaanBuka=($jamSekarang>=$jamBuka&&$jamSekarang<$jamTutup);
}

/* Cek presensi hari ini */
$cek=$koneksi->prepare("
    SELECT id_kunjungan
    FROM kunjungan
    WHERE id_anggota=? AND DATE(waktu_kunjungan)=CURDATE()
    LIMIT 1
");
$cek->execute([$id_anggota]);
$sudahPresensi=$cek->fetch();

/* Proses presensi dengan validasi server */
if($_SERVER['REQUEST_METHOD']==='POST'&&!$sudahPresensi){
    requireCsrf();

    $hariPost=(int)date('N');
    $jamPost=date('H:i:s');
    $bolehPresensi=false;

    if($hariPost>=1&&$hariPost<=4){
        $bolehPresensi=($jamPost>='07:30:00'&&$jamPost<'15:30:00');
    }elseif($hariPost===5){
        $bolehPresensi=($jamPost>='07:30:00'&&$jamPost<'15:00:00');
    }

    if(!$bolehPresensi){
        $pesan='tutup';
    }else{
        // FIX: waktu diambil dari PHP (sudah Asia/Jakarta), bukan dibiarkan
        // diisi otomatis oleh default current_timestamp() milik MySQL,
        // karena timezone session MySQL di server hosting bisa berbeda (UTC).
        $waktuSekarang = date('Y-m-d H:i:s');
        $stmt=$koneksi->prepare("INSERT INTO kunjungan (id_anggota, waktu_kunjungan) VALUES (?, ?)");
        $stmt->execute([$id_anggota, $waktuSekarang]);
        $sudahPresensi=true;
        $pesan='sukses';
    }
}

/* Riwayat 5 kunjungan terakhir */
$riwayatStmt=$koneksi->prepare("
    SELECT *
    FROM kunjungan
    WHERE id_anggota=?
    ORDER BY waktu_kunjungan DESC
    LIMIT 5
");
$riwayatStmt->execute([$id_anggota]);
$riwayat=$riwayatStmt->fetchAll();

/* Kunjungan hari ini */
$kunjunganHariIni=null;
if($sudahPresensi&&$riwayat){
    foreach($riwayat as $r){
        if(date('Y-m-d',strtotime($r['waktu_kunjungan']))===date('Y-m-d')){
            $kunjunganHariIni=$r;
            break;
        }
    }
}

/* Tanggal Indonesia */
$namaBulan=[
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];
$tanggalIndonesia=date('d').' '.$namaBulan[(int)date('n')].' '.date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Presensi Kunjungan | Perpustakaan Digital Ilmu</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
.presensi-topbar{min-height:86px!important;padding:0 38px 0 210px!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:20px}
.topbar-info{display:flex;flex-direction:column;gap:3px}
.topbar-brand{font-size:17px;font-weight:800;color:#fff;letter-spacing:-.2px}
.topbar-page{font-size:13px;color:rgba(255,255,255,.72)}
.presensi-wrapper{max-width:1000px;margin:0 auto;padding-bottom:50px}
.presensi-hero{margin-top:26px;padding:26px 28px;border-radius:18px;background:linear-gradient(135deg,#fff,#f8fbff);border:1px solid #e2e8f0;box-shadow:0 8px 25px rgba(15,23,42,.05)}
.presensi-hero-top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px}
.presensi-hero h1{margin:0 0 7px;font-size:25px;color:#0f172a;letter-spacing:-.5px}
.presensi-hero p{margin:0;color:#64748b;font-size:14px;line-height:1.6}
.operasional-badge{flex-shrink:0;display:flex;align-items:center;gap:8px;padding:9px 13px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:12px;font-weight:700}
.operasional-badge.closed{background:#fef2f2;color:#b91c1c}
.operasional-dot{width:8px;height:8px;border-radius:50%;background:#10b981}
.operasional-badge.closed .operasional-dot{background:#ef4444}
.jadwal-ringkas{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
.jadwal-item{padding:10px 14px;border-radius:11px;background:#f8fafc;border:1px solid #e2e8f0}
.jadwal-item span{display:block;font-size:11px;color:#94a3b8;margin-bottom:2px}
.jadwal-item strong{font-size:13px;color:#334155}
.realtime-status{display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;margin-top:15px;padding:13px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px}
.realtime-left{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:600;color:#334155}
.realtime-dot{width:9px;height:9px;border-radius:50%;background:#10b981;box-shadow:0 0 0 4px rgba(16,185,129,.12)}
.realtime-dot.closed{background:#ef4444;box-shadow:0 0 0 4px rgba(239,68,68,.12)}
.realtime-clock{font-size:14px;font-weight:700;color:#0f172a}
.presensi-status-card{margin-top:22px;padding:38px 25px;border-radius:20px;text-align:center;background:#fff;border:1px solid #e2e8f0;box-shadow:0 10px 30px rgba(15,23,42,.06)}
.status-icon-custom{width:68px;height:68px;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;border-radius:18px}
.status-icon-custom svg{width:34px;height:34px}
.status-icon-success{color:#059669;background:#ecfdf5}
.status-icon-pending{color:#0284c7;background:#eff6ff}
.status-icon-closed{color:#dc2626;background:#fef2f2}
.presensi-status-card h2{margin:0 0 9px;color:#0f172a;font-size:23px}
.presensi-status-card p{max-width:580px;margin:0 auto;color:#64748b;line-height:1.7}
.operasional-info{display:flex;justify-content:center;gap:12px;margin-top:24px;flex-wrap:wrap}
.operasional-box{min-width:135px;padding:13px 18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:13px}
.operasional-box strong{display:block;color:#0f172a;font-size:17px}
.operasional-box span{display:block;margin-top:3px;color:#64748b;font-size:12px}
.presensi-button{margin-top:24px;min-width:210px;padding:13px 22px;border:none;border-radius:11px;background:#0284c7;color:#fff;font-size:14px;font-weight:700;cursor:pointer;transition:.2s}
.presensi-button:hover{background:#0369a1;transform:translateY(-1px);box-shadow:0 8px 18px rgba(2,132,199,.22)}
.presensi-button:disabled{opacity:.55;cursor:not-allowed;transform:none;box-shadow:none}
.meta-time-custom{display:flex;justify-content:center;gap:12px;margin-top:25px}
.meta-time-custom>div{min-width:135px;padding:12px 15px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0}
.meta-time-custom strong{display:block;color:#0f172a;font-size:16px}
.meta-time-custom span{display:block;margin-top:3px;color:#64748b;font-size:12px}
.presensi-alert{margin:18px 0;padding:13px 16px;border-radius:12px;font-size:14px;font-weight:600}
.presensi-alert.success{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.presensi-alert.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.riwayat-card{margin-top:22px;padding:25px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 8px 25px rgba(15,23,42,.04)}
.riwayat-card h2{margin:0 0 18px;color:#0f172a;font-size:18px}
.empty-state-custom{text-align:center;padding:30px 15px}
.empty-state-custom svg{width:48px;height:48px;margin-bottom:10px;color:#94a3b8}
.empty-state-custom strong{display:block;color:#334155}
.empty-state-custom span{display:block;margin-top:5px;color:#94a3b8;font-size:13px}
@media(max-width:700px){
.presensi-topbar{min-height:76px!important;padding:0 16px 0 88px!important}
.topbar-brand{font-size:14px}
.topbar-page{font-size:11px}
.presensi-wrapper{padding-left:14px;padding-right:14px}
.presensi-hero{margin-top:18px;padding:21px 18px;border-radius:16px}
.presensi-hero-top{display:block}
.presensi-hero h1{font-size:22px}
.operasional-badge{display:inline-flex;margin-top:15px}
.jadwal-ringkas{display:grid;grid-template-columns:1fr 1fr}
.jadwal-item{padding:9px 11px}
.realtime-status{align-items:flex-start;flex-direction:column}
.realtime-clock{font-size:13px}
.presensi-status-card{padding:30px 18px;border-radius:17px}
.presensi-status-card h2{font-size:20px}
.operasional-info{display:grid;grid-template-columns:1fr 1fr;width:100%}
.operasional-box{min-width:0}
.meta-time-custom{display:grid;grid-template-columns:1fr 1fr}
.meta-time-custom>div{min-width:0}
.presensi-button{width:100%;min-width:0}
.riwayat-card{padding:18px;overflow-x:auto}
}
</style>
</head>
<body class="admin-page">
<?php $activeMenu='presensi';require '../includes/siswa_sidebar.php'; ?>

<div class="topbar presensi-topbar">
    <div class="topbar-info">
        <span class="topbar-brand">Perpustakaan Digital Ilmu</span>
        <span class="topbar-page">Presensi Kunjungan</span>
    </div>

</div>

<main>
<div class="container">
<div class="presensi-wrapper">

<div class="presensi-hero">
    <div class="presensi-hero-top">
        <div>
            <h1>Presensi Kunjungan</h1>
            <p>Catat kunjunganmu ke perpustakaan dengan mudah dan cepat.</p>
        </div>
        <div id="operasionalBadge" class="operasional-badge <?= $perpustakaanBuka?'':'closed' ?>">
            <span class="operasional-dot"></span>
            <span id="operasionalText">
                <?= $perpustakaanBuka?'Perpustakaan Buka':'Perpustakaan Tutup' ?>
            </span>
        </div>
    </div>

    <div class="jadwal-ringkas">
        <div class="jadwal-item">
            <span>Senin – Kamis</span>
            <strong>07:30 – 15:30</strong>
        </div>
        <div class="jadwal-item">
            <span>Jumat</span>
            <strong>07:30 – 15:00</strong>
        </div>
        <div class="jadwal-item">
            <span>Sabtu – Minggu</span>
            <strong>Tutup</strong>
        </div>
    </div>

    <div class="realtime-status">
        <div class="realtime-left">
            <span id="realtimeDot" class="realtime-dot <?= $perpustakaanBuka?'':'closed' ?>"></span>
            <span id="realtimeStatus">
                <?php if($hari===6||$hari===7): ?>
                    Perpustakaan tutup hari ini
                <?php elseif($perpustakaanBuka): ?>
                    Perpustakaan sedang buka
                <?php elseif($jamSekarang<$jamBuka): ?>
                    Perpustakaan belum buka
                <?php else: ?>
                    Perpustakaan sudah tutup
                <?php endif; ?>
            </span>
        </div>
        <div id="realtimeClock" class="realtime-clock"><?= date('H:i:s') ?> WIB</div>
    </div>
</div>

<?php if($pesan==='sukses'): ?>
<div class="presensi-alert success"> Presensi berhasil! Selamat membaca.</div>
<?php elseif($pesan==='tutup'): ?>
<div class="presensi-alert error"> Presensi tidak dapat dilakukan karena perpustakaan sedang tutup.</div>
<?php endif; ?>

<div class="presensi-status-card">
<?php if($sudahPresensi): ?>

<div class="status-icon-custom status-icon-success">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<circle cx="12" cy="12" r="9.5"/>
<polyline points="8 12.5 11 15.5 16 9"/>
</svg>
</div>
<h2>Presensi Berhasil</h2>
<p>Kamu sudah melakukan presensi hari ini. Selamat membaca dan belajar!</p>

<?php if($kunjunganHariIni): ?>
<div class="meta-time-custom">
<div>
<strong><?= date('d-m-Y',strtotime($kunjunganHariIni['waktu_kunjungan'])) ?></strong>
<span>Tanggal</span>
</div>
<div>
<strong><?= date('H:i',strtotime($kunjunganHariIni['waktu_kunjungan'])) ?> WIB</strong>
<span>Jam Kunjungan</span>
</div>
</div>
<?php endif; ?>

<?php else: ?>

<?php if($perpustakaanBuka): ?>

<div id="statusIcon" class="status-icon-custom status-icon-pending">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<circle cx="12" cy="12" r="9.5"/>
<polyline points="12 7 12 12 15.5 14"/>
</svg>
</div>
<h2 id="statusTitle">Belum Presensi Hari Ini</h2>
<p id="statusDescription">Perpustakaan sedang buka. Silakan lakukan presensi sebelum mulai membaca.</p>

<form method="POST">
<?= csrfField() ?>
<button type="submit" id="presensiButton" class="presensi-button"> Presensi Sekarang</button>
</form>

<?php else: ?>

<div id="statusIcon" class="status-icon-custom status-icon-closed">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<rect x="3" y="4" width="18" height="17" rx="2"/>
<line x1="16" y1="2" x2="16" y2="6"/>
<line x1="8" y1="2" x2="8" y2="6"/>
<line x1="3" y1="10" x2="21" y2="10"/>
</svg>
</div>

<h2 id="statusTitle">
<?php if($hari===6||$hari===7): ?>
Perpustakaan Tutup
<?php elseif($jamSekarang<$jamBuka): ?>
Presensi Belum Dibuka
<?php else: ?>
Presensi Sudah Ditutup
<?php endif; ?>
</h2>

<p id="statusDescription">
<?php if($hari===6||$hari===7): ?>
Hari ini perpustakaan tidak beroperasi. Presensi tersedia kembali pada hari Senin.
<?php elseif($jamSekarang<$jamBuka): ?>
Presensi akan dibuka pukul <strong><?= substr($jamBuka,0,5) ?> WIB</strong>.
<?php else: ?>
Presensi hari ini sudah ditutup. Silakan kembali pada hari dan jam operasional berikutnya.
<?php endif; ?>
</p>

<?php endif; ?>

<div class="operasional-info">
<?php if($jamBuka!==null): ?>
<div class="operasional-box">
<strong><?= substr($jamBuka,0,5) ?></strong>
<span>Jam Buka</span>
</div>
<div class="operasional-box">
<strong><?= substr($jamTutup,0,5) ?></strong>
<span>Jam Tutup</span>
</div>
<?php else: ?>
<div class="operasional-box">
<strong>Tutup</strong>
<span>Hari Ini</span>
</div>
<?php endif; ?>
</div>

<?php endif; ?>
</div>

<div class="riwayat-card">
<h2> Riwayat Kunjungan Kamu</h2>

<?php if($riwayat): ?>
<table>
<thead>
<tr>
<th>Tanggal</th>
<th>Jam</th>
<th>Keterangan</th>
</tr>
</thead>
<tbody>
<?php foreach($riwayat as $r): ?>
<tr>
<td data-label="Tanggal"><?= date('d-m-Y',strtotime($r['waktu_kunjungan'])) ?></td>
<td data-label="Jam"><?= date('H:i',strtotime($r['waktu_kunjungan'])) ?> WIB</td>
<td data-label="Keterangan"><?= htmlspecialchars($r['keterangan']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<div class="empty-state-custom">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
<rect x="3" y="4" width="18" height="18" rx="2"/>
<line x1="16" y1="2" x2="16" y2="6"/>
<line x1="8" y1="2" x2="8" y2="6"/>
<line x1="3" y1="10" x2="21" y2="10"/>
</svg>
<strong>Belum ada riwayat kunjungan</strong>
<span>Riwayat presensimu akan muncul di sini.</span>
</div>
<?php endif; ?>
</div>

</div>
</div>
</main>

<script>
(function(){
const statusElement=document.getElementById('realtimeStatus');
const clockElement=document.getElementById('realtimeClock');
const dotElement=document.getElementById('realtimeDot');
const statusTitle=document.getElementById('statusTitle');
const statusDescription=document.getElementById('statusDescription');
const statusIcon=document.getElementById('statusIcon');
const presensiButton=document.getElementById('presensiButton');
const operasionalBadge=document.getElementById('operasionalBadge');
const operasionalText=document.getElementById('operasionalText');

const bulan=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function formatMinute(total){
    const h=Math.floor(total/60);
    const m=total%60;
    return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');
}

function updateRealtime(){
    const now=new Date();
    const h=String(now.getHours()).padStart(2,'0');
    const m=String(now.getMinutes()).padStart(2,'0');
    const s=String(now.getSeconds()).padStart(2,'0');
    const jamText=h+':'+m+':'+s+' WIB';

    clockElement.textContent=jamText;

    const hari=now.getDay();
    let jamBuka=null;
    let jamTutup=null;

    if(hari>=1&&hari<=4){
        jamBuka=450;
        jamTutup=930;
    }else if(hari===5){
        jamBuka=450;
        jamTutup=900;
    }

    const menitSekarang=now.getHours()*60+now.getMinutes();

    if(jamBuka===null){
        statusElement.textContent='Perpustakaan tutup hari ini';
        dotElement.classList.add('closed');
        operasionalBadge.classList.add('closed');
        operasionalText.textContent='Perpustakaan Tutup';

        if(statusTitle)statusTitle.textContent='Perpustakaan Tutup';
        if(statusDescription)statusDescription.textContent='Hari ini perpustakaan tidak beroperasi. Presensi tersedia kembali pada hari Senin.';
        if(statusIcon){
            statusIcon.classList.remove('status-icon-pending');
            statusIcon.classList.add('status-icon-closed');
        }
        if(presensiButton)presensiButton.disabled=true;
        return;
    }

    if(menitSekarang<jamBuka){
        statusElement.textContent='Perpustakaan belum buka';
        dotElement.classList.add('closed');
        operasionalBadge.classList.add('closed');
        operasionalText.textContent='Perpustakaan Belum Buka';

        if(statusTitle)statusTitle.textContent='Presensi Belum Dibuka';
        if(statusDescription)statusDescription.innerHTML='Presensi akan dibuka pukul <strong>'+formatMinute(jamBuka)+' WIB</strong>.';
        if(statusIcon){
            statusIcon.classList.remove('status-icon-pending');
            statusIcon.classList.add('status-icon-closed');
        }
        if(presensiButton)presensiButton.disabled=true;
        return;
    }

    if(menitSekarang>=jamBuka&&menitSekarang<jamTutup){
        statusElement.textContent='Perpustakaan sedang buka';
        dotElement.classList.remove('closed');
        operasionalBadge.classList.remove('closed');
        operasionalText.textContent='Perpustakaan Buka';

        if(statusTitle)statusTitle.textContent='Belum Presensi Hari Ini';
        if(statusDescription)statusDescription.textContent='Perpustakaan sedang buka. Silakan lakukan presensi sebelum mulai membaca.';
        if(statusIcon){
            statusIcon.classList.remove('status-icon-closed');
            statusIcon.classList.add('status-icon-pending');
        }
        if(presensiButton)presensiButton.disabled=false;
        return;
    }

    statusElement.textContent='Perpustakaan sudah tutup';
    dotElement.classList.add('closed');
    operasionalBadge.classList.add('closed');
    operasionalText.textContent='Perpustakaan Tutup';

    if(statusTitle)statusTitle.textContent='Presensi Sudah Ditutup';
    if(statusDescription)statusDescription.textContent='Presensi hari ini sudah ditutup. Silakan kembali pada hari dan jam operasional berikutnya.';
    if(statusIcon){
        statusIcon.classList.remove('status-icon-pending');
        statusIcon.classList.add('status-icon-closed');
    }
    if(presensiButton)presensiButton.disabled=true;
}

updateRealtime();
setInterval(updateRealtime,1000);
})();
</script>
</body>
</html>