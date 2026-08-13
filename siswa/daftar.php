<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $nis            = trim($_POST['nis'] ?? '');
    $nama_lengkap   = trim($_POST['nama_lengkap'] ?? '');
    $kelas          = trim($_POST['kelas'] ?? '');
    $no_hp          = trim($_POST['no_hp'] ?? '');
    $alamat         = trim($_POST['alamat'] ?? '');
    $username       = trim($_POST['username'] ?? '');
    $password       = $_POST['password'] ?? '';
    $konfirmasi     = $_POST['konfirmasi_password'] ?? '';

    if ($nis === '' || $nama_lengkap === '' || $kelas === '' || $username === '') {

        $error = "Mohon lengkapi semua data wajib (NIS, Nama Lengkap, Kelas, Username).";

    } elseif (strlen($password) < 6) {

        // Indikator kekuatan password di JS hanya visual, jadi panjang minimal
        // tetap harus divalidasi di backend supaya tidak bisa dilewati.
        $error = "Password minimal 6 karakter.";

    } elseif ($password !== $konfirmasi) {

        $error = "Password dan konfirmasi password tidak sama.";

    } else {

        $cek = $koneksi->prepare("
            SELECT id_anggota
            FROM anggota
            WHERE nis = ?
            OR username = ?
        ");

        $cek->execute([$nis, $username]);

        if ($cek->fetch()) {

            $error = "NIS atau Username sudah terdaftar.";

        } else {

            $hash = password_hash($password, PASSWORD_BCRYPT);

            try {
                $stmt = $koneksi->prepare("
                    INSERT INTO anggota
                    (
                        nis,
                        nama_lengkap,
                        kelas,
                        no_hp,
                        alamat,
                        username,
                        password
                    )
                    VALUES
                    (
                        ?,?,?,?,?,?,?
                    )
                ");

                $stmt->execute([
                    $nis,
                    $nama_lengkap,
                    $kelas,
                    $no_hp,
                    $alamat,
                    $username,
                    $hash
                ]);

                $success = true;

            } catch (PDOException $e) {
                // Lapisan pertahanan kedua: kalau dua orang daftar dengan NIS/username
                // sama persis di saat bersamaan, cek SELECT di atas bisa lolos untuk
                // keduanya (race condition). UNIQUE KEY di database akan menolak salah
                // satunya di sini — kita tangkap dan tampilkan sebagai pesan biasa,
                // bukan error PHP mentah ke pengguna.
                if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                    $error = "NIS atau Username sudah terdaftar.";
                } else {
                    $error = "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.";
                }
            }

        }

    }

}
?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Daftar Anggota — Perpustakaan Digital</title>

<link rel="stylesheet" href="../assets/style.css">

<link rel="stylesheet"
href="../assets/css/notification.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
:root{
  --navy:#0f2742;
  --blue:#1677d2;
  --text:#172033;
  --muted:#667085;
  --border:#d9e1ea;
  --bg:#f4f7fb;
}

*{box-sizing:border-box}

body.register-page{
  margin:0;
  min-height:100vh;
  min-height:100dvh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:24px;
  background:var(--bg);
  font-family:Inter,Poppins,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  color:var(--text);
}

.login-card{
  width:min(560px,100%);
  background:#fff;
  border:1px solid var(--border);
  border-radius:14px;
  padding:36px;
  box-shadow:0 12px 35px rgba(15,39,66,.08);
}

.login-brand{
  display:flex;
  align-items:center;
  gap:11px;
  margin-bottom:28px;
}

.brand-icon{
  width:38px;
  height:38px;
  display:grid;
  place-items:center;
  border-radius:9px;
  background:#edf5fc;
  color:var(--blue);
}

.brand-icon svg{
  width:20px;
  height:20px;
}

.brand-text{
  color:var(--navy);
  font-size:13px;
  font-weight:800;
}

.brand-subtext{
  margin-top:2px;
  color:#98a2b3;
  font-size:9px;
}

.login-heading{
  margin-bottom:25px;
}

.login-heading h1{
  margin:0;
  color:var(--navy);
  font-size:25px;
  line-height:1.25;
  font-weight:800;
  letter-spacing:-.02em;
}

.login-heading p{
  margin:7px 0 0;
  color:var(--muted);
  font-size:11px;
  line-height:1.6;
}

.login-alert{
  display:flex;
  gap:9px;
  margin-bottom:18px;
  padding:10px 11px;
  border:1px solid #fecdca;
  border-radius:9px;
  background:#fff7f6;
  color:#b42318;
  font-size:10px;
  line-height:1.5;
}

.login-alert svg{
  flex:0 0 15px;
}

.form-section{
  margin-bottom:22px;
}

.form-section-title{
  margin-bottom:14px;
  padding-bottom:8px;
  border-bottom:1px solid #edf0f3;
  color:var(--navy);
  font-size:11px;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.03em;
}

.form-grid-2{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px;
}

.form-grid-2 .full{
  grid-column:1 / -1;
}

.form-group{
  margin-bottom:16px;
}

.form-grid-2 .form-group{
  margin-bottom:0;
}

.form-label{
  display:block;
  margin-bottom:7px;
  color:#344054;
  font-size:10px;
  font-weight:700;
}

.input-wrap{
  position:relative;
}

.form-input{
  width:100%;
  height:44px;
  padding:0 14px;
  border:1px solid var(--border);
  border-radius:9px;
  outline:none;
  background:#fff;
  color:var(--text);
  font:inherit;
  font-size:11px;
  transition:border-color .15s,box-shadow .15s;
}

textarea.form-input{
  height:auto;
  padding:10px 14px;
  resize:vertical;
  min-height:60px;
}

.form-input.has-toggle{
  padding-right:40px;
}

.form-input::placeholder{
  color:#a3adba;
}

.form-input:focus{
  border-color:#65a8e6;
  box-shadow:0 0 0 3px rgba(22,119,210,.08);
}

.password-toggle{
  position:absolute;
  right:7px;
  top:50%;
  width:30px;
  height:30px;
  display:grid;
  place-items:center;
  border:0;
  border-radius:7px;
  background:transparent;
  color:#98a2b3;
  cursor:pointer;
  transform:translateY(-50%);
}

.password-toggle:hover{
  background:#f4f6f8;
  color:#475467;
}

.pw-strength{
  margin-top:8px;
}

.pw-strength-track{
  height:5px;
  border-radius:99px;
  background:#edf0f3;
  overflow:hidden;
}

.pw-strength-fill{
  height:100%;
  width:0%;
  border-radius:99px;
  transition:width .2s,background .2s;
}

.pw-strength-label{
  display:block;
  margin-top:6px;
  color:#98a2b3;
  font-size:9px;
}

.login-submit{
  width:100%;
  height:44px;
  margin-top:4px;
  border:0;
  border-radius:9px;
  background:var(--blue);
  color:#fff;
  font:inherit;
  font-size:11px;
  font-weight:800;
  cursor:pointer;
  transition:background .15s,transform .15s;
}

.login-submit:hover{
  background:#1268bd;
  transform:translateY(-1px);
}

.login-submit:disabled{
  opacity:.7;
  cursor:wait;
  transform:none;
}

.back-link{
  display:flex;
  justify-content:center;
  align-items:center;
  gap:6px;
  margin-top:20px;
  color:#667085;
  text-decoration:none;
  font-size:10px;
  font-weight:650;
}

.back-link:hover{
  color:var(--blue);
}

.login-footer{
  margin-top:23px;
  padding-top:15px;
  border-top:1px solid #edf0f3;
  text-align:center;
  color:#98a2b3;
  font-size:9px;
  line-height:1.5;
}

.login-footer a{
  color:var(--blue);
  text-decoration:none;
  font-weight:700;
}

.login-footer a:hover{
  text-decoration:underline;
}

@media(max-width:480px){
  body.register-page{
    padding:15px;
  }

  .login-card{
    padding:28px 22px;
    border-radius:12px;
    box-shadow:0 8px 25px rgba(15,39,66,.07);
  }

  .login-brand{
    margin-bottom:24px;
  }

  .login-heading h1{
    font-size:23px;
  }

  .form-grid-2{
    grid-template-columns:1fr;
  }
}
</style>

</head>

<body class="register-page">

<main class="login-card">

  <?php if(!$success): ?>

  <div class="login-brand">
    <div class="brand-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 5.5c2.3-1 5-1 8 .4v13.2c-3-1.4-5.7-1.4-8-.4V5.5Z"/>
        <path d="M20 5.5c-2.3-1-5-1-8 .4v13.2c3-1.4 5.7-1.4 8-.4V5.5Z"/>
        <path d="M12 5.9v13.2"/>
      </svg>
    </div>

    <div>
      <div class="brand-text">Perpustakaan Digital</div>
      <div class="brand-subtext">Sistem Informasi Perpustakaan Sekolah</div>
    </div>
  </div>

  <div class="login-heading">
    <h1>Daftar Anggota</h1>
    <p>Lengkapi data dirimu untuk membuat akun anggota perpustakaan sekolah.</p>
  </div>

  <?php if($error): ?>
    <div class="login-alert" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7.5v5"/>
        <path d="M12 16.2h.01"/>
      </svg>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" id="daftarForm">
    <?= csrfField() ?>

    <div class="form-section">
      <div class="form-section-title">Informasi Pribadi</div>

      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label" for="nis">NIS</label>
          <div class="input-wrap">
            <input class="form-input" type="text" id="nis" name="nis" required placeholder="Nomor Induk Siswa" value="<?= htmlspecialchars($_POST['nis'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="kelas">Kelas</label>
          <div class="input-wrap">
            <input class="form-input" type="text" id="kelas" name="kelas" required placeholder="Contoh: XII RPL 1" value="<?= htmlspecialchars($_POST['kelas'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group full">
          <label class="form-label" for="nama_lengkap">Nama Lengkap</label>
          <div class="input-wrap">
            <input class="form-input" type="text" id="nama_lengkap" name="nama_lengkap" required placeholder="Nama lengkap sesuai identitas" value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="no_hp">No HP</label>
          <div class="input-wrap">
            <input class="form-input" type="text" id="no_hp" name="no_hp" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group full">
          <label class="form-label" for="alamat">Alamat</label>
          <div class="input-wrap">
            <textarea class="form-input" id="alamat" name="alamat" rows="2" placeholder="Alamat tempat tinggal"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="form-section">
      <div class="form-section-title">Informasi Akun</div>

      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <div class="input-wrap">
          <input class="form-input" type="text" id="username" name="username" required placeholder="Username untuk login" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-wrap">
          <input class="form-input has-toggle" type="password" id="password" name="password" required minlength="6" placeholder="Minimal 6 karakter">
          <button type="button" class="password-toggle toggle-visibility" data-target="password" aria-label="Tampilkan password">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3.5 12s3.1-5 8.5-5 8.5 5 8.5 5-3.1 5-8.5 5-8.5-5-8.5-5Z"/>
              <circle cx="12" cy="12" r="2.3"/>
            </svg>
          </button>
        </div>
        <div class="pw-strength">
          <div class="pw-strength-track"><div class="pw-strength-fill" id="pwFill"></div></div>
          <span class="pw-strength-label" id="pwLabel">Ketik password untuk melihat kekuatannya</span>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="konfirmasi_password">Konfirmasi Password</label>
        <div class="input-wrap">
          <input class="form-input has-toggle" type="password" id="konfirmasi_password" name="konfirmasi_password" required minlength="6" placeholder="Ulangi password">
          <button type="button" class="password-toggle toggle-visibility" data-target="konfirmasi_password" aria-label="Tampilkan password">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3.5 12s3.1-5 8.5-5 8.5 5 8.5 5-3.1 5-8.5 5-8.5-5-8.5-5Z"/>
              <circle cx="12" cy="12" r="2.3"/>
            </svg>
          </button>
        </div>
        <span class="pw-strength-label" id="pwMatchLabel"></span>
      </div>
    </div>

    <button type="submit" class="login-submit" id="btnDaftar">Daftar Sekarang</button>
  </form>

  <div class="login-footer">
    Sudah punya akun? <a href="login.php">Masuk di sini</a>
  </div>

  <a href="../index.php" class="back-link">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 12H5"/>
      <path d="m11 6-6 6 6 6"/>
    </svg>
    Kembali ke halaman utama
  </a>

  <?php endif; ?>

</main>

<div id="toast-container"></div>
<audio id="notificationSound" preload="auto">
    <source src="../assets/sound/notification.mp3" type="audio/mpeg">
</audio>
<script src="../assets/js/notification.js"></script>

<script>
  // Toggle tampilkan/sembunyikan password (kosmetik, tidak mengubah logic PHP)
  document.querySelectorAll('.toggle-visibility').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.target);
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.setAttribute('aria-label', showing ? 'Tampilkan password' : 'Sembunyikan password');
    });
  });

  // Indikator kekuatan password (visual only, tidak memblokir submit —
  // validasi wajib tetap dilakukan di server)
  var pwInput = document.getElementById('password');
  var pwFill = document.getElementById('pwFill');
  var pwLabel = document.getElementById('pwLabel');
  if (pwInput) {
    pwInput.addEventListener('input', function () {
      var val = pwInput.value;
      var score = 0;
      if (val.length >= 6) score++;
      if (val.length >= 10) score++;
      if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      var levels = [
        { width: '0%',   color: '#edf0f3', label: 'Ketik password untuk melihat kekuatannya' },
        { width: '30%',  color: '#e5484d', label: 'Lemah' },
        { width: '55%',  color: '#f59e0b', label: 'Cukup' },
        { width: '80%',  color: '#1677d2', label: 'Kuat' },
        { width: '100%', color: '#22c55e', label: 'Sangat kuat' }
      ];
      var lvl = val.length === 0 ? levels[0] : levels[Math.min(score, 4)];
      pwFill.style.width = lvl.width;
      pwFill.style.background = lvl.color;
      pwLabel.textContent = lvl.label;
    });
  }

  // Indikator konfirmasi password cocok/tidak (visual only)
  var pwConfirm = document.getElementById('konfirmasi_password');
  var pwMatchLabel = document.getElementById('pwMatchLabel');
  function cekMatch() {
    if (!pwConfirm.value) { pwMatchLabel.textContent = ''; return; }
    if (pwConfirm.value === pwInput.value) {
      pwMatchLabel.textContent = 'Password cocok';
      pwMatchLabel.style.color = '#22c55e';
    } else {
      pwMatchLabel.textContent = 'Password belum sama';
      pwMatchLabel.style.color = '#e5484d';
    }
  }
  if (pwConfirm) {
    pwConfirm.addEventListener('input', cekMatch);
    pwInput.addEventListener('input', cekMatch);
  }

  // Loading state saat submit
  var formDaftar = document.getElementById('daftarForm');
  if (formDaftar) {
    formDaftar.addEventListener('submit', function () {
      var btn = document.getElementById('btnDaftar');
      btn.disabled = true;
      btn.textContent = 'Memproses...';
    });
  }
</script>

<?php if($success): ?>
<script>
document.addEventListener("DOMContentLoaded",function(){
    if(typeof showToast==="function"){
        showToast(
            "Registrasi Berhasil",
            "Selamat! Akun berhasil dibuat. Anda akan diarahkan ke halaman login.",
            "success",
            "fa-circle-check",
            "#22c55e"
        );

    }
    setTimeout(function(){
        window.location.href="login.php"
    },3000);
});
</script>
<?php endif; ?>
</body>
</html>