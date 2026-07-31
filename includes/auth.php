<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Panggil di awal halaman khusus admin
function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Panggil di awal halaman khusus siswa
function requireSiswa() {
    if (!isset($_SESSION['anggota_id'])) {
        header('Location: login.php');
        exit;
    }
}