<?php
/**
 * Sidebar untuk seluruh halaman Petugas.
 * Include file ini setelah requirePetugas() dipanggil.
 * Set $activeMenu sebelum include untuk menandai menu aktif, contoh:
 *   $activeMenu = 'dashboard';
 */
$activeMenu = $activeMenu ?? '';

function petugasSideLink($href, $key, $label, $svgInner, $active) {
    $cls = 'admin-side-link' . ($active === $key ? ' active' : '');
    echo '<a href="' . $href . '" class="' . $cls . '"><span class="admin-side-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $svgInner . '</svg></span><span>' . $label . '</span></a>';
}
?>
<button class="admin-menu-toggle" type="button" aria-label="Buka menu" onclick="document.body.classList.toggle('admin-menu-open')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="14" y2="17"/></svg></button>
<div class="admin-sidebar-overlay" onclick="document.body.classList.remove('admin-menu-open')"></div>
<aside class="admin-sidebar">
  <div class="admin-sidebar-brand">
    <div class="admin-brand-mark">P</div>
    <div><strong>Perpustakaan</strong><small>Panel Petugas</small></div>
  </div>
  <nav class="admin-side-nav" aria-label="Navigasi petugas">
    <div class="admin-side-label">MENU UTAMA</div>
    <?php petugasSideLink('dashboard.php', 'dashboard', 'Dashboard', '<rect x="3.5" y="3.5" width="7" height="8" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="14.5" width="7" height="6" rx="1.5"/>', $activeMenu); ?>

    <div class="admin-side-label" style="margin-top:14px;">OPERASIONAL</div>
    <?php petugasSideLink('peminjaman.php', 'peminjaman', 'Peminjaman', '<path d="M4 7.5h13.5L15 4.5"/><path d="M20 16.5H6.5L9 19.5"/>', $activeMenu); ?>
    <?php petugasSideLink('pengembalian.php', 'pengembalian', 'Pengembalian', '<path d="M20 7.5H6.5L9 4.5"/><path d="M4 16.5h13.5L15 19.5"/>', $activeMenu); ?>
    <?php petugasSideLink('buku_terlambat.php', 'terlambat', 'Buku Terlambat', '<circle cx="12" cy="12" r="8.5"/><polyline points="12 7.5 12 12 15.5 14"/>', $activeMenu); ?>

    <div class="admin-side-label" style="margin-top:14px;">DATA</div>
    <?php petugasSideLink('data_buku.php', 'data_buku', 'Data Buku', '<path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/>', $activeMenu); ?>
    <?php petugasSideLink('data_anggota.php', 'data_anggota', 'Data Anggota', '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.3c2.4.3 4 2.2 4 5.2"/>', $activeMenu); ?>

    <div class="admin-side-label" style="margin-top:14px;">LAINNYA</div>
    <?php petugasSideLink('aktivitas.php', 'aktivitas', 'Aktivitas', '<polyline points="3.5 12 8 12 10 7 14 17 16 12 20.5 12"/>', $activeMenu); ?>

    <div class="admin-side-label" style="margin-top:14px;">AKUN</div>
    <?php petugasSideLink('profil.php', 'profil', 'Profil Petugas', '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c0-4.1 3.4-6.5 7.5-6.5s7.5 2.4 7.5 6.5"/>', $activeMenu); ?>
  </nav>
  <div class="admin-sidebar-bottom">
    <div class="admin-side-user"><span class="admin-avatar">P</span><span><strong><?= htmlspecialchars($_SESSION['petugas_nama'] ?? 'Petugas') ?></strong><small>Petugas Perpustakaan</small></span></div>
    <a href="logout.php" class="admin-logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H6.5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2H11"/><polyline points="15.5 8 19.5 12 15.5 16"/><line x1="19.5" y1="12" x2="9" y2="12"/></svg><span>Keluar</span></a>
  </div>
</aside>
<main class="admin-main">