<?php
/**
 * Konstanta global aplikasi Perpustakaan Digital
 */

// Tarif denda keterlambatan pengembalian buku, per hari
define('TARIF_DENDA_PER_HARI', 1000);

// Batas maksimum denda per transaksi peminjaman (mencegah denda menumpuk
// tanpa batas kalau buku terlambat dikembalikan dalam waktu sangat lama)
define('TARIF_DENDA_MAKSIMUM', 20000);