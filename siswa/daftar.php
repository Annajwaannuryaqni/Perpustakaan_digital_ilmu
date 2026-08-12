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

    if ($password !== $konfirmasi) {

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

<title>Daftar Anggota</title>

<link rel="stylesheet" href="../assets/style.css">

<link rel="stylesheet"
href="../assets/css/notification.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<body>

<div class="auth-shell">

  <div class="auth-visual">
    <div class="auth-visual-brand">
      <span class="mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5V5a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v14"/><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H19"/><path d="M4 19.5V17"/><line x1="9" y1="7" x2="14" y2="7"/></svg>
      </span>
      Perpustakaan Digital Sekolah
    </div>

    <div class="auth-visual-body">
      <h1>Daftar sekali, pinjam buku kapan saja.</h1>
      <p>Lengkapi data dirimu untuk membuat akun anggota perpustakaan sekolah.</p>
    </div>

    <div class="auth-visual-stats">
      <div><strong>2 menit</strong><span>Proses pendaftaran</span></div>
      <div><strong>Gratis</strong><span>Untuk seluruh siswa</span></div>
      <div><strong>Aman</strong><span>Data tersimpan terenkripsi</span></div>
    </div>
  </div>

  <div class="auth-form-side">
    <div class="auth-form-card" style="max-width:480px;">

      <?php if(!$success): ?>

      <div class="eyebrow">Portal Siswa</div>
      <h2>Daftar Anggota Perpustakaan</h2>
      <p class="lede">Isi data pribadi dan data akun kamu di bawah ini.</p>

      <?php if($error): ?>
      <p class="alert alert-gagal"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>

        <div class="form-section">
          <div class="form-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            Informasi Pribadi
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label for="nis">NIS</label>
              <input type="text" id="nis" name="nis" required placeholder="Nomor Induk Siswa">
            </div>

            <div class="form-group">
              <label for="kelas">Kelas</label>
              <input type="text" id="kelas" name="kelas" required placeholder="Contoh: XII RPL 1">
            </div>

            <div class="form-group full">
              <label for="nama_lengkap">Nama Lengkap</label>
              <input type="text" id="nama_lengkap" name="nama_lengkap" required placeholder="Nama lengkap sesuai identitas">
            </div>

            <div class="form-group">
              <label for="no_hp">No HP</label>
              <input type="text" id="no_hp" name="no_hp" placeholder="08xxxxxxxxxx">
            </div>

            <div class="form-group full">
              <label for="alamat">Alamat</label>
              <textarea id="alamat" name="alamat" rows="2" placeholder="Alamat tempat tinggal"></textarea>
            </div>
          </div>
        </div>

        <div class="form-section">
          <div class="form-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>
            Informasi Akun
          </div>

          <div class="form-group">
            <label for="username">Username</label>
            <div class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
              <input type="text" id="username" name="username" required placeholder="Username untuk login">
            </div>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-icon has-toggle">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>
              <input type="password" id="password" name="password" required placeholder="Minimal 6 karakter">
              <button type="button" class="toggle-visibility" data-target="password" aria-label="Tampilkan password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="pw-strength">
              <div class="pw-strength-track"><div class="pw-strength-fill" id="pwFill"></div></div>
              <span class="pw-strength-label" id="pwLabel">Ketik password untuk melihat kekuatannya</span>
            </div>
          </div>

          <div class="form-group">
            <label for="konfirmasi_password">Konfirmasi Password</label>
            <div class="input-icon has-toggle">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>
              <input type="password" id="konfirmasi_password" name="konfirmasi_password" required placeholder="Ulangi password">
              <button type="button" class="toggle-visibility" data-target="konfirmasi_password" aria-label="Tampilkan password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <span class="pw-strength-label" id="pwMatchLabel"></span>
          </div>
        </div>

        <button type="submit" class="btn" id="btnDaftar" style="width:100%;margin-top:22px;">
          Daftar Sekarang
        </button>
      </form>

      <div class="form-foot">
        Sudah punya akun? <a href="login.php" class="btn-link">Masuk di sini</a>
      </div>
      <br>
      <a href="../index.php" class="back-link" style="justify-content:center;display:flex;">← Kembali ke Beranda</a>

      <?php endif; ?>

    </div>
  </div>

</div>

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

  // Indikator kekuatan password (visual only, tidak memblokir submit)
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
        { width: '0%',   color: 'var(--slate-200)', label: 'Ketik password untuk melihat kekuatannya' },
        { width: '30%',  color: 'var(--danger)',     label: 'Lemah' },
        { width: '55%',  color: 'var(--warning)',    label: 'Cukup' },
        { width: '80%',  color: 'var(--royal-500)',  label: 'Kuat' },
        { width: '100%', color: 'var(--success)',    label: 'Sangat kuat' }
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
      pwMatchLabel.style.color = 'var(--success)';
    } else {
      pwMatchLabel.textContent = 'Password belum sama';
      pwMatchLabel.style.color = 'var(--danger)';
    }
  }
  if (pwConfirm) {
    pwConfirm.addEventListener('input', cekMatch);
    pwInput.addEventListener('input', cekMatch);
  }

  // Loading state saat submit
  var formDaftar = document.querySelector('.auth-form-card form');
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