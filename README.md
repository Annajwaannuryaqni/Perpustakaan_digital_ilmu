# Perpustakaan Digital Ilmu

Aplikasi **Perpustakaan Digital Ilmu** merupakan aplikasi perpustakaan sekolah berbasis web yang digunakan untuk membantu proses pengelolaan perpustakaan secara digital.

Sistem ini mencakup pengelolaan data buku dan anggota, peminjaman dan pengembalian buku, perhitungan denda keterlambatan, rating dan komentar buku, presensi kunjungan perpustakaan, serta sistem notifikasi.

Aplikasi dikembangkan menggunakan **PHP Native, MySQL/MariaDB, PDO, Tailwind CSS, dan JavaScript** dengan menerapkan sistem autentikasi berdasarkan hak akses pengguna.

**Live Demo:** https://perpustakaandigitalilmu.infinityfreeapp.com

**Mockup:** ...

**Use Case Diagram:** ...

**Flowchart:** ...

**ERD:** ...

---

## 1. Struktur Folder

```text
Perpustakaan_Digital_Ilmu/
│
├── admin/
│   ├── anggota.php
│   ├── buku.php
│   ├── dashboard.php
│   ├── edit_anggota.php
│   ├── edit_buku.php
│   ├── hapus_anggota.php
│   ├── hapus_buku.php
│   ├── hapus_transaksi.php
│   ├── kunjungan.php
│   ├── login.php
│   ├── logout.php
│   ├── petugas.php
│   ├── tambah_anggota.php
│   ├── tambah_buku.php
│   └── transaksi.php
│
├── api/
│   └── notifications.php
│
├── assets/
│   ├── css/
│   │   └── notification.css
│   ├── js/
│   │   └── notification.js
│   ├── sounds/
│   │   └── notification.mp3
│   ├── logo-sekolah.png
│   ├── style.css
│   └── video/
│       ├── gods.mp4
│       └── gntng.mp4
│
├── includes/
│   ├── auth.php
│   ├── navbar_notification.php
│   ├── notification_helper.php
│   ├── petugas_sidebar.php
│   └── siswa_sidebar.php
│
├── petugas/
│   ├── aktivitas.php
│   ├── buku_terlambat.php
│   ├── dashboard.php
│   ├── data_anggota.php
│   ├── data_buku.php
│   ├── login.php
│   ├── logout.php
│   ├── peminjaman.php
│   ├── pengembalian.php
│   ├── profil.php
│   ├── proses_kembali.php
│   └── proses_pinjam.php
│
├── siswa/
│   ├── bukti_peminjaman.php
│   ├── daftar.php
│   ├── dashboard.php
│   ├── kembali.php
│   ├── login.php
│   ├── logout.php
│   ├── pinjam.php
│   ├── pinjam_konfirmasi.php
│   ├── presensi.php
│   ├── proses_kembali.php
│   ├── proses_pinjam.php
│   └── proses_rating.php
│
├── uploads/
│   └── cover buku
│
├── bantuan.php
├── index.php
│
├── config/
│   ├── database.php
│   └── constants.php
│
└── db_perpustakaan.sql
```

---

## 2. Fitur Utama

### 🏠 Beranda
- Halaman landing page perpustakaan.
- Menampilkan informasi dan koleksi buku.
- Menampilkan kategori buku.
- Menampilkan cover buku.
- Pencarian buku.
- Video pada halaman beranda.
- Informasi perpustakaan.
- Halaman bantuan.

### 📚 Manajemen Buku
Administrator dapat:
- Menambahkan, mengedit, dan menghapus buku.
- Mengelola kode buku, judul, pengarang, penerbit, dan tahun terbit.
- Menentukan kategori, stok, lokasi rak, dan deskripsi.
- Mengunggah cover buku.

### 👥 Manajemen Anggota
Administrator dapat:
- Menambahkan, mengedit, dan menghapus anggota.
- Melihat NIS dan kelas.
- Mengelola username.
- Mengatur status anggota.

### 👨‍💼 Manajemen Petugas
Administrator dapat:
- Menambahkan akun petugas.
- Melihat data petugas.
- Mengelola status petugas.
- Mengelola akun petugas.

### 📖 Peminjaman Buku
- Pemilihan anggota dan buku.
- Pemeriksaan stok.
- Pencatatan tanggal peminjaman dan jatuh tempo.
- Pengurangan stok otomatis.
- Pencatatan petugas.
- Konfirmasi peminjaman.
- Bukti peminjaman.

### 🔄 Pengembalian Buku
- Melihat buku yang sedang dipinjam.
- Memproses pengembalian.
- Mencatat tanggal kembali.
- Mengubah status transaksi.
- Menambah stok kembali.
- Menghitung keterlambatan dan denda.

### 💰 Denda
Sistem menghitung denda berdasarkan jumlah hari keterlambatan.

**Tarif denda: Rp1.000/hari keterlambatan.**

### ⭐ Rating dan 💬 Komentar
Siswa dapat memberikan rating dan komentar terhadap buku yang telah dipinjam.

### 🏫 Presensi/Kunjungan
Siswa dapat melakukan presensi kunjungan perpustakaan dengan validasi jadwal operasional dan pencegahan presensi ganda pada hari yang sama.

### 🔔 Notifikasi
- Notifikasi di dalam aplikasi.
- Status sudah/belum dibaca.
- Notifikasi berdasarkan tipe pengguna.
- Suara notifikasi.
- Notifikasi berdasarkan aktivitas tertentu.

---

## 3. Peran Pengguna

### 1. Admin
Admin memiliki hak akses tertinggi:
- Mengelola dashboard.
- Mengelola buku.
- Mengelola anggota.
- Mengelola petugas.
- Mengelola transaksi.
- Melihat kunjungan.
- Mengelola data sistem.

### 2. Petugas
Petugas berfokus pada operasional perpustakaan:
- Melihat dashboard operasional.
- Melihat data buku dan anggota.
- Memproses peminjaman.
- Memproses pengembalian.
- Melihat buku terlambat.
- Melihat aktivitas transaksi.
- Mengelola profil sendiri.

### 3. Siswa/Anggota
Siswa menggunakan layanan perpustakaan:
- Registrasi dan login.
- Melihat dan mencari katalog.
- Meminjam dan mengembalikan buku.
- Melihat bukti peminjaman.
- Memberikan rating dan komentar.
- Melakukan presensi kunjungan.
- Melihat dashboard pribadi.

---

## 4. Teknologi

| Teknologi | Penggunaan |
|---|---|
| PHP Native | Backend dan proses aplikasi |
| MySQL/MariaDB | Database |
| PDO | Koneksi dan query database |
| HTML5 | Struktur halaman |
| CSS3 | Styling dan responsive layout |
| Tailwind CSS | UI |
| JavaScript | Interaksi dan notifikasi |
| PHP Session | Autentikasi |
| bcrypt | Hashing password |

---

## 5. Struktur Database

Database yang digunakan:

```text
db_perpustakaan
```

Tabel utama:
- `admin`
- `anggota`
- `buku`
- `kategori`
- `petugas`
- `transaksi`
- `rating`
- `komentar`
- `kunjungan`
- `notifications`

### Relasi Utama

```text
kategori
   │
   └──< buku
           │
           └──< transaksi >── anggota
                    │
                    └── petugas

buku ───< rating >── anggota
  │
  └──< komentar >── anggota

anggota ───< kunjungan
```

---

## 6. Instalasi

### Persyaratan
- XAMPP/Laragon atau web server PHP.
- PHP 8.2 atau versi kompatibel.
- MySQL/MariaDB.
- Web browser modern.

### Langkah Instalasi

1. Letakkan folder project di folder `htdocs` jika menggunakan XAMPP.
2. Jalankan **Apache** dan **MySQL**.
3. Buka **phpMyAdmin**.
4. Buat database `db_perpustakaan`.
5. Import file `db_perpustakaan.sql`.
6. Periksa konfigurasi database di `config/database.php`.
7. Sesuaikan host, database, username, dan password.
8. Buka:

```text
http://localhost/Perpustakaan_Digital_Ilmu/
```

9. Login menggunakan akun yang tersedia pada database.

---

## 7. Keamanan

Aplikasi menerapkan:
- Password hashing dengan `password_hash()`.
- Verifikasi password dengan `password_verify()`.
- Prepared statement PDO.
- CSRF protection.
- Session authentication berdasarkan role.
- Validasi akses halaman.
- Session regeneration setelah login.
- Transaction database pada proses peminjaman dan pengembalian.
- Penguncian stok saat transaksi untuk membantu mencegah konflik stok.

---

## 8. Kontributor

**Najwa**

Project ini dikembangkan sebagai aplikasi perpustakaan digital sekolah untuk mendukung proses pembelajaran dan pengelolaan layanan perpustakaan secara digital.

---

## 9. Catatan

Aplikasi ini dibuat untuk kebutuhan **perpustakaan sekolah** dan masih dapat dikembangkan lebih lanjut, seperti penambahan laporan transaksi, export data, pencetakan laporan, statistik yang lebih lengkap, serta peningkatan sistem keamanan dan manajemen pengguna.
