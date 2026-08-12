<?php
require_once '../includes/auth.php';

// Hanya hapus data session milik Petugas. Tidak memakai session_destroy()
// agar session Administrator/Siswa yang mungkin aktif di browser yang sama
// tidak ikut terhapus.
unset($_SESSION['petugas_id']);
unset($_SESSION['petugas_nama']);

header('Location: login.php');
exit;
