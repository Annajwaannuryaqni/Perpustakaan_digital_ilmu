<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $koneksi->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id_admin'];
        $_SESSION['admin_nama'] = $admin['nama_lengkap'];
        $_SESSION['flash_notif'] = [
            'title'   => 'Login Berhasil',
            'message' => 'Selamat, Anda berhasil login sebagai Admin.',
            'type'    => 'success',
            'icon'    => 'fa-circle-check',
            'color'   => '#22c55e',
        ];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — Perpustakaan Digital</title>
<link rel="stylesheet" href="../assets/style.css">

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

body.admin-login-page{
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
  width:min(430px,100%);
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

.brand-icon svg{width:20px;height:20px}

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

.login-heading{margin-bottom:25px}

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

.login-alert svg{flex:0 0 15px}

.form-group{margin-bottom:16px}

.form-label{
  display:block;
  margin-bottom:7px;
  color:#344054;
  font-size:10px;
  font-weight:700;
}

.input-wrap{position:relative}

.input-icon{
  position:absolute;
  left:12px;
  top:50%;
  width:16px;
  height:16px;
  color:#98a2b3;
  transform:translateY(-50%);
  pointer-events:none;
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

#password.form-input{
  padding-right:40px;
}

.form-input::placeholder{color:#a3adba}

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

.password-toggle:hover{background:#f4f6f8;color:#475467}

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

.back-link:hover{color:var(--blue)}

.login-footer{
  margin-top:23px;
  padding-top:15px;
  border-top:1px solid #edf0f3;
  text-align:center;
  color:#98a2b3;
  font-size:9px;
  line-height:1.5;
}

@media(max-width:480px){
  body.admin-login-page{padding:15px}

  .login-card{
    padding:28px 22px;
    border-radius:12px;
    box-shadow:0 8px 25px rgba(15,39,66,.07);
  }

  .login-brand{margin-bottom:24px}
  .login-heading h1{font-size:23px}
}
</style>
</head>

<body class="admin-login-page">

<main class="login-card">

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
    <h1>Login Admin</h1>
    <p>Masuk menggunakan akun administrator untuk mengelola perpustakaan.</p>
  </div>

  <?php if ($error): ?>
    <div class="login-alert" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7.5v5"/>
        <path d="M12 16.2h.01"/>
      </svg>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" id="adminLoginForm">
    <?= csrfField() ?>

    <div class="form-group">
      <label class="form-label" for="username">Username</label>
      <div class="input-wrap">
        <input class="form-input" type="text" id="username" name="username"
               autocomplete="username" placeholder="Masukkan username" required autofocus>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <div class="input-wrap">
        <input class="form-input" type="password" id="password" name="password"
               autocomplete="current-password" placeholder="Masukkan password" required>
        <button type="button" class="password-toggle" id="togglePassword" aria-label="Tampilkan password">
          <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3.5 12s3.1-5 8.5-5 8.5 5 8.5 5-3.1 5-8.5 5-8.5-5-8.5-5Z"/>
            <circle cx="12" cy="12" r="2.3"/>
          </svg>
        </button>
      </div>
    </div>

    <button type="submit" class="login-submit" id="loginSubmit">Masuk</button>
  </form>

  <a href="../index.php" class="back-link">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 12H5"/>
      <path d="m11 6-6 6 6 6"/>
    </svg>
    Kembali ke halaman utama
  </a>

  <div class="login-footer">
    Halaman khusus administrator perpustakaan.
  </div>

</main>

<script>
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');
const eyeIcon = document.getElementById('eyeIcon');

togglePassword.addEventListener('click', function(){
  const showing = passwordInput.type === 'text';
  passwordInput.type = showing ? 'password' : 'text';
  this.setAttribute('aria-label', showing ? 'Tampilkan password' : 'Sembunyikan password');

  eyeIcon.innerHTML = showing
    ? '<path d="M3.5 12s3.1-5 8.5-5 8.5 5 8.5 5-3.1 5-8.5 5-8.5-5-8.5-5Z"/><circle cx="12" cy="12" r="2.3"/>'
    : '<path d="M4 4l16 16"/><path d="M10.6 6.9A10.5 10.5 0 0 1 12 6.8c5.4 0 8.5 5.2 8.5 5.2a15.4 15.4 0 0 1-3 3.3"/><path d="M6.5 8.2C4.5 9.6 3.5 12 3.5 12s3.1 5.2 8.5 5.2c1.1 0 2.1-.2 3-.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>';
});

document.getElementById('adminLoginForm').addEventListener('submit', function(){
  const button = document.getElementById('loginSubmit');
  button.disabled = true;
  button.textContent = 'Memproses...';
});
</script>

</body>
</html>