<?php
/**
 * Koneksi Database - Aplikasi Peminjaman Buku
 * Menggunakan PDO agar query aman dari SQL Injection (prepared statement)
 */

$DB_HOST = 'localhost';
$DB_NAME = 'db_perpustakaan';
$DB_USER = 'root';
$DB_PASS = ''; // sesuaikan dengan password MySQL/XAMPP kamu

try {
    $koneksi = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}