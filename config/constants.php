<?php
/**
 * Konstanta global aplikasi Perpustakaan Digital
 */

// Tarif denda keterlambatan pengembalian buku, per hari.
// Denda dihitung berdasarkan jumlah hari keterlambatan tanpa batas maksimum.
define('TARIF_DENDA_PER_HARI', 1000);

// Denda tambahan jika kondisi buku saat dikembalikan dicatat sebagai Rusak.
// Nilai ini dapat diubah sesuai kebijakan perpustakaan. Untuk demo: Rp20.000.
define('DENDA_BUKU_RUSAK', 20000);

// Denda tambahan jika kondisi buku saat dikembalikan dicatat sebagai Hilang.
define('DENDA_BUKU_HILANG', 50000);
