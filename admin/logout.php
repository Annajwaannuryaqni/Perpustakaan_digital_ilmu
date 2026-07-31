<?php
session_start();
session_destroy(); // hapus semua data session (admin_id, admin_nama, dll)
header('Location: dashboard.php');
exit;