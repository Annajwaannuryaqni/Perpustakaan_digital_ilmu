<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $nis          = trim($_POST['nis']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $kelas        = trim($_POST['kelas']);
    $no_hp        = trim($_POST['no_hp']);
    $alamat       = trim($_POST['alamat']);
    $username     = trim($_POST['username']);
    $password     = $_POST['password'];

    // Cek dulu apakah NIS atau username sudah dipakai anggota lain
    $cek = $koneksi->prepare("SELECT id_anggota FROM anggota WHERE nis = ? OR username = ?");
    $cek->execute([$nis, $username]);

    if ($cek->fetch()) {
        $error = 'NIS atau Username sudah terdaftar, gunakan yang lain.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $koneksi->prepare("
            INSERT INTO anggota (nis, nama_lengkap, kelas, no_hp, alamat, username, password)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nis, $nama_lengkap, $kelas, $no_hp, $alamat, $username, $hash]);

        header('Location: anggota.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Anggota</title>
<link rel="stylesheet" href="../assets/style.css">

<style>
/* ===== FORM PAGE REFINEMENT ===== */
.form-page-shell{max-width:1180px;margin:0 auto;}
.form-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:22px;}
.form-page-title-wrap{display:flex;gap:14px;align-items:flex-start;}
.form-page-icon{width:46px;height:46px;display:grid;place-items:center;border-radius:14px;background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb;border:1px solid #dbeafe;box-shadow:0 8px 22px rgba(37,99,235,.10);}
.form-page-icon svg{width:22px;height:22px;}
.form-page-head h1{margin:0;color:#0f172a;font:800 1.55rem/1.2 Poppins,sans-serif;letter-spacing:-.025em;}
.form-page-head p{margin:7px 0 0;color:#64748b;font-size:.88rem;line-height:1.6;}
.form-back-link{display:inline-flex;align-items:center;gap:8px;color:#475569;text-decoration:none;font-size:.82rem;font-weight:700;padding:10px 13px;border:1px solid #e2e8f0;border-radius:11px;background:#fff;transition:.2s ease;white-space:nowrap;}
.form-back-link:hover{color:#2563eb;border-color:#bfdbfe;background:#f8fbff;transform:translateY(-1px);}
.form-back-link svg{width:16px;height:16px;}

.pro-form-card{max-width:none!important;padding:0!important;overflow:hidden;background:#fff;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 16px 45px -28px rgba(15,23,42,.28);}
.pro-form-card:hover{transform:none!important;box-shadow:0 16px 45px -28px rgba(15,23,42,.28)!important;}
.form-card-top{padding:20px 28px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#fff,#fbfdff);}
.form-card-top strong{display:block;color:#0f172a;font:700 .98rem/1.3 Poppins,sans-serif;}
.form-card-top span{display:block;color:#94a3b8;font-size:.76rem;margin-top:4px;}
.pro-form{padding:26px 28px 28px;}
.form-alert{display:flex;align-items:flex-start;gap:11px;margin:0 0 22px!important;padding:13px 15px!important;border-radius:13px!important;}
.form-alert svg{width:18px;height:18px;flex:0 0 auto;margin-top:1px;}

.form-section{padding:0;margin:0 0 26px;}
.form-section:last-of-type{margin-bottom:8px;}
.form-section-head{display:flex;align-items:center;gap:11px;margin-bottom:16px;}
.form-section-number{width:28px;height:28px;display:grid;place-items:center;border-radius:9px;background:#eff6ff;color:#2563eb;font:800 .72rem Inter,sans-serif;}
.form-section-head h2{margin:0;color:#172033;font:700 .94rem Poppins,sans-serif;}
.form-section-head p{margin:2px 0 0;color:#94a3b8;font-size:.72rem;}
.form-section-head>div:last-child{min-width:0;}

.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 20px;}
.form-field{min-width:0;}
.form-field.full{grid-column:1/-1;}
.form-field label{display:flex;align-items:center;gap:6px;margin:0 0 7px!important;font-size:.78rem!important;color:#334155!important;font-weight:700!important;}
.form-required{color:#2563eb;font-size:.72rem;}
.form-help{display:block;margin-top:6px;color:#94a3b8;font-size:.69rem;line-height:1.5;}

.pro-form input[type=text],
.pro-form input[type=password],
.pro-form input[type=number],
.pro-form input[type=tel],
.pro-form input[type=file],
.pro-form select,
.pro-form textarea{
 width:100%;box-sizing:border-box;margin:0!important;padding:12px 14px!important;min-height:45px;
 border:1px solid #dbe3ec!important;border-radius:11px!important;background:#fbfdff!important;color:#172033!important;
 font:500 .82rem Inter,sans-serif!important;outline:none;transition:border-color .2s ease,box-shadow .2s ease,background .2s ease,transform .2s ease;
}
.pro-form textarea{min-height:116px;resize:vertical;line-height:1.65;}
.pro-form input::placeholder,.pro-form textarea::placeholder{color:#a8b3c2;}
.pro-form input:hover,.pro-form select:hover,.pro-form textarea:hover{border-color:#c7d3e1!important;background:#fff!important;}
.pro-form input:focus,.pro-form select:focus,.pro-form textarea:focus{border-color:#60a5fa!important;background:#fff!important;box-shadow:0 0 0 4px rgba(37,99,235,.08)!important;}
.pro-form select{appearance:auto;cursor:pointer;}
.pro-form input[type=file]{padding:9px 11px!important;background:#fff!important;color:#64748b!important;cursor:pointer;}
.pro-form input[type=file]::file-selector-button{border:0;border-radius:8px;padding:8px 11px;margin-right:9px;background:#eff6ff;color:#2563eb;font:700 .72rem Inter,sans-serif;cursor:pointer;}
.input-with-icon{position:relative;}
.input-with-icon input{padding-right:43px!important;}
.input-icon{position:absolute;right:13px;top:50%;transform:translateY(-50%);width:17px;height:17px;color:#94a3b8;pointer-events:none;}
.password-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#94a3b8;padding:7px;cursor:pointer;border-radius:7px;}
.password-toggle:hover{background:#f1f5f9;color:#2563eb;}
.password-toggle svg{width:17px;height:17px;display:block;}

.cover-layout{display:grid;grid-template-columns:220px minmax(0,1fr);gap:24px;align-items:start;}
.cover-drop{min-height:260px;border:1.5px dashed #cbd5e1;border-radius:18px;background:linear-gradient(145deg,#f8fbff,#f1f5f9);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:18px;text-align:center;transition:.2s ease;overflow:hidden;}
.cover-drop:hover{border-color:#60a5fa;background:#f8fbff;}
.cover-preview{width:130px;height:175px;border-radius:10px;background:#fff;border:1px solid #e2e8f0;display:grid;place-items:center;overflow:hidden;box-shadow:0 14px 30px rgba(15,23,42,.12);margin-bottom:14px;}
.cover-preview img{width:100%;height:100%;object-fit:cover;display:none;}
.cover-preview-empty{color:#94a3b8;font-size:.68rem;line-height:1.5;padding:12px;}
.cover-drop strong{color:#334155;font-size:.76rem;}
.cover-drop span{color:#94a3b8;font-size:.66rem;margin-top:5px;line-height:1.5;}
.cover-file{margin-top:12px;width:100%!important;}

.form-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding-top:22px;margin-top:4px;border-top:1px solid #eef2f7;}
.pro-btn{min-height:44px!important;padding:11px 18px!important;border-radius:11px!important;font-size:.78rem!important;box-shadow:none!important;}
.pro-btn-primary{box-shadow:0 10px 22px -10px rgba(37,99,235,.6)!important;}
.pro-btn-primary svg,.pro-btn-secondary svg{width:16px;height:16px;}
.pro-btn-secondary{border:1px solid #dbe3ec!important;color:#475569!important;background:#fff!important;}
.pro-btn-secondary:hover{background:#f8fafc!important;border-color:#cbd5e1!important;color:#0f172a!important;}

.form-footnote{display:flex;align-items:center;gap:7px;margin-top:13px;color:#94a3b8;font-size:.68rem;}
.form-footnote svg{width:14px;height:14px;color:#64748b;flex:0 0 auto;}

@media(max-width:800px){
 .form-page-head{align-items:flex-start;flex-direction:column;}
 .form-back-link{align-self:flex-start;}
 .pro-form{padding:22px 18px 22px;}
 .form-card-top{padding:18px;}
 .form-grid{grid-template-columns:1fr;gap:16px;}
 .form-field.full{grid-column:auto;}
 .cover-layout{grid-template-columns:1fr;}
 .cover-drop{min-height:235px;}
}
@media(max-width:520px){
 .admin-main>.container{padding-left:14px!important;padding-right:14px!important;}
 .form-page-title-wrap{gap:10px;}
 .form-page-icon{width:40px;height:40px;border-radius:12px;}
 .form-page-head h1{font-size:1.28rem;}
 .form-page-head p{font-size:.78rem;}
 .form-actions{flex-direction:column-reverse;}
 .pro-btn{width:100%;}
}
</style>

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
      <a href="petugas.php" class="admin-side-link"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="3"/><path d="M4 19.5c0-3 2.2-5 5-5s5 2 5 5"/><path d="M14.5 9.2h5M17 6.7v5"/></svg></span><span>Petugas</span></a>
    </nav>
    <div class="admin-sidebar-bottom">
      <div class="admin-side-user"><span class="admin-avatar">A</span><span><strong>Admin</strong><small>Pengelola Perpustakaan</small></span></div>
      <a href="logout.php" class="admin-logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/></svg><span>Keluar</span></a>
    </div>
  </aside>
  <main class="admin-main">



  <div class="container">
    <div class="form-page-shell">
      <div class="form-page-head">
        <div class="form-page-title-wrap">
          <div class="form-page-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="8" r="3.5"/>
              <path d="M4.5 20c.5-4 3.1-6.2 7.5-6.2s7 2.2 7.5 6.2"/>
            </svg>
          </div>
          <div>
            <h1>Tambah Anggota</h1>
            <p>Daftarkan siswa baru sebagai anggota perpustakaan dengan data yang lengkap dan terstruktur.</p>
          </div>
        </div>
        <a href="anggota.php" class="form-back-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
          Kembali ke Anggota
        </a>
      </div>

      <div class="card pro-form-card">
        <div class="form-card-top">
          <strong>Data Anggota</strong>
          <span>Isi identitas siswa dan kredensial login yang akan digunakan.</span>
        </div>

        <?php if ($error): ?>
          <div class="pro-form" style="padding-bottom:0;">
            <div class="alert alert-gagal form-alert" role="alert">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>
              <span><?= htmlspecialchars($error) ?></span>
            </div>
          </div>
        <?php endif; ?>

        <form method="POST" class="pro-form">
          <?= csrfField() ?>

          <section class="form-section">
            <div class="form-section-head">
              <span class="form-section-number">01</span>
              <div><h2>Identitas Siswa</h2><p>Data dasar anggota perpustakaan.</p></div>
            </div>
            <div class="form-grid">
              <div class="form-field">
                <label for="nis">NIS <span class="form-required">*</span></label>
                <input id="nis" type="text" name="nis" value="<?= htmlspecialchars($_POST['nis'] ?? '') ?>" placeholder="Nomor induk siswa" required>
              </div>
              <div class="form-field">
                <label for="nama_lengkap">Nama Lengkap <span class="form-required">*</span></label>
                <input id="nama_lengkap" type="text" name="nama_lengkap" value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" placeholder="Nama lengkap siswa" required>
              </div>
              <div class="form-field">
                <label for="kelas">Kelas <span class="form-required">*</span></label>
                <input id="kelas" type="text" name="kelas" value="<?= htmlspecialchars($_POST['kelas'] ?? '') ?>" placeholder="Contoh: XI RPL 1" required>
              </div>
              <div class="form-field">
                <label for="no_hp">No. HP</label>
                <input id="no_hp" type="tel" name="no_hp" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" placeholder="Contoh: 0812xxxxxxxx">
              </div>
              <div class="form-field full">
                <label for="alamat">Alamat</label>
                <textarea id="alamat" name="alamat" rows="4" placeholder="Alamat tempat tinggal siswa"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
              </div>
            </div>
          </section>

          <section class="form-section">
            <div class="form-section-head">
              <span class="form-section-number">02</span>
              <div><h2>Akun Anggota</h2><p>Informasi yang digunakan untuk masuk ke sistem perpustakaan.</p></div>
            </div>
            <div class="form-grid">
              <div class="form-field">
                <label for="username">Username <span class="form-required">*</span></label>
                <input id="username" type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Buat username anggota" autocomplete="username" required>
              </div>
              <div class="form-field">
                <label for="password">Password <span class="form-required">*</span></label>
                <div class="input-with-icon">
                  <input id="password" type="password" name="password" placeholder="Masukkan password" autocomplete="new-password" required>
                  <button type="button" class="password-toggle" id="togglePassword" aria-label="Tampilkan password">
                    <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                  </button>
                </div>
                <span class="form-help">Gunakan password yang cukup kuat dan jangan dibagikan kepada orang lain.</span>
              </div>
            </div>
          </section>

          <div class="form-actions">
            <a href="anggota.php" class="btn btn-outline pro-btn pro-btn-secondary">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
              Batal
            </a>
            <button type="submit" class="btn pro-btn pro-btn-primary">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
              Simpan Anggota
            </button>
          </div>
          <div class="form-footnote">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 10v6"/><path d="M12 7h.01"/></svg>
            NIS dan username harus unik agar akun dapat digunakan tanpa konflik.
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
  (function(){
    const btn=document.getElementById('togglePassword');
    const input=document.getElementById('password');
    if(!btn||!input) return;
    btn.addEventListener('click',function(){
      const show=input.type==='password';
      input.type=show?'text':'password';
      btn.setAttribute('aria-label',show?'Sembunyikan password':'Tampilkan password');
    });
  })();
  </script>
  </main>
</body>
</html>