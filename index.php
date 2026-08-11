<?php
session_start();
require_once 'config/database.php';

if (empty($_SESSION['rating_csrf'])) {
    $_SESSION['rating_csrf'] = bin2hex(random_bytes(32));
}
$ratingCsrf = $_SESSION['rating_csrf'];

$idAnggotaLogin = isset($_SESSION['anggota_id']) ? (int)$_SESSION['anggota_id'] : null;
$transaksiRatingPerBuku = [];
$ratingSayaPerBuku = [];

if ($idAnggotaLogin) {
    try {
        // Ambil transaksi terakhir anggota untuk setiap buku yang pernah dipinjam.
        $stmtTR = $koneksi->prepare("
            SELECT t.id_buku, t.id_transaksi
            FROM transaksi t
            INNER JOIN (
                SELECT id_buku, MAX(id_transaksi) AS max_id
                FROM transaksi
                WHERE id_anggota = ?
                GROUP BY id_buku
            ) x ON x.max_id = t.id_transaksi
            WHERE t.id_anggota = ?
        ");
        $stmtTR->execute([$idAnggotaLogin, $idAnggotaLogin]);

        foreach ($stmtTR->fetchAll() as $tr) {
            $transaksiRatingPerBuku[(int)$tr['id_buku']] = (int)$tr['id_transaksi'];
        }

        // Ambil rating yang sudah diberikan anggota pada transaksi tersebut.
        if ($transaksiRatingPerBuku) {
            $stmtRS = $koneksi->prepare("
                SELECT id_transaksi, nilai
                FROM rating
                WHERE id_transaksi IN (" . implode(',', array_fill(0, count($transaksiRatingPerBuku), '?')) . ")
            ");
            $stmtRS->execute(array_values($transaksiRatingPerBuku));
            foreach ($stmtRS->fetchAll() as $rr) {
                $ratingSayaPerBuku[(int)$rr['id_transaksi']] = (int)$rr['nilai'];
            }
        }
    } catch (PDOException $e) {
        // Bila tabel rating belum tersedia, form rating tetap aman dan tidak merusak halaman.
    }
}

if (isset($_SESSION['admin_id'])) { header('Location: admin/dashboard.php'); exit; }

// Ambil SEMUA buku dengan stok > 0, dikelompokkan per genre
$daftarBuku = $koneksi->query("
    SELECT b.*, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON k.id_kategori = b.id_kategori
    WHERE b.stok > 0
    ORDER BY k.nama_kategori ASC, b.judul ASC
")->fetchAll();

$bukuPerGenre = [];
foreach ($daftarBuku as $b) {
    $genre = $b['nama_kategori'] ?: 'Lainnya';
    $bukuPerGenre[$genre][] = $b;
}

// Ambil daftar kategori unik untuk filter capsule
$kategoriList = [];
foreach ($daftarBuku as $b) {
    $cat = $b['nama_kategori'] ?: 'Lainnya';
    if (!in_array($cat, $kategoriList)) {
        $kategoriList[] = $cat;
    }
}
sort($kategoriList);

// Data untuk Grafik Statistik Koleksi per Genre (landing page publik)
$genreChartLabels = [];
$genreChartData = [];
foreach ($bukuPerGenre as $genre => $daftar) {
    $genreChartLabels[] = $genre;
    $genreChartData[] = count($daftar);
}

// Rata-rata rating per buku (try-catch: aman kalau tabel "rating" belum dibuat)
$ratingPerBuku = [];
try {
    foreach ($koneksi->query("SELECT id_buku, AVG(nilai) AS rata, COUNT(*) AS jumlah FROM rating GROUP BY id_buku") as $r) {
        $ratingPerBuku[$r['id_buku']] = ['rata' => (float)$r['rata'], 'jumlah' => (int)$r['jumlah']];
    }
} catch (PDOException $e) { /* tabel rating belum ada, lewati */ }

/**
 * ============================================================
 *  RINGKASAN STATISTIK KOLEKSI (Landing Page Publik)
 *  Seluruhnya dihitung dari data PHP yang sudah tersedia
 *  di atas ($daftarBuku, $bukuPerGenre, $ratingPerBuku).
 * ============================================================
 */
$totalBukuAktif  = count($daftarBuku);
$totalGenreAktif = count($bukuPerGenre);

$genreTerbanyakNama  = null;
$genreTerbanyakJumlah = 0;
foreach ($bukuPerGenre as $genre => $daftar) {
    if (count($daftar) > $genreTerbanyakJumlah) {
        $genreTerbanyakJumlah = count($daftar);
        $genreTerbanyakNama = $genre;
    }
}

// Rata-rata rating keseluruhan (gabungan seluruh buku yang punya rating)
$totalUlasanSemua = 0;
$jumlahSkorSemua  = 0.0;
foreach ($ratingPerBuku as $rp) {
    $totalUlasanSemua += $rp['jumlah'];
    $jumlahSkorSemua  += $rp['rata'] * $rp['jumlah'];
}
$rataRatingSemua = $totalUlasanSemua > 0 ? ($jumlahSkorSemua / $totalUlasanSemua) : 0;

// Urutkan genre dari yang terbanyak, untuk breakdown ringkas (top 5 + "lainnya")
$genreUrutTerbanyak = $bukuPerGenre;
uasort($genreUrutTerbanyak, function($a, $b) { return count($b) <=> count($a); });
$genreTop5 = array_slice($genreUrutTerbanyak, 0, 5, true);
$genreSisaJumlah = 0;
$genreSisaCount = max(0, count($genreUrutTerbanyak) - 5);
if ($genreSisaCount > 0) {
    $i = 0;
    foreach ($genreUrutTerbanyak as $daftarGenre) {
        $i++;
        if ($i > 5) $genreSisaJumlah += count($daftarGenre);
    }
}

// Komentar per buku, terbaru dulu, sekalian ambil rating yang diberikan reviewer itu
// (try-catch: aman kalau tabel "komentar"/"rating" belum dibuat)
$komentarPerBuku = [];
try {
    $stmtKomentar = $koneksi->query("
        SELECT k.id_buku, k.isi_komentar, k.created_at, a.nama_lengkap, r.nilai
        FROM komentar k
        JOIN anggota a ON a.id_anggota = k.id_anggota
        LEFT JOIN rating r ON r.id_transaksi = k.id_transaksi
        ORDER BY k.created_at DESC
    ");
    foreach ($stmtKomentar as $k) {
        $komentarPerBuku[$k['id_buku']][] = $k;
    }
} catch (PDOException $e) { /* tabel komentar belum ada, lewati */ }

/**
 * ============================================================
 *  RATING — SVG Star Icons (mendukung setengah bintang)
 * ============================================================
 */
function starIconSvg($mode, $size) {
    // $mode: 'full' | 'empty'
    if ($mode === 'full') {
        return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="#f59e0b"><path d="M12 2.7l2.9 6 6.5.9-4.7 4.6 1.1 6.5L12 17.6l-5.8 3.1 1.1-6.5-4.7-4.6 6.5-.9 2.9-6Z"/></svg>';
    }
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="#d5dce6" stroke-width="1.6"><path d="M12 2.7l2.9 6 6.5.9-4.7 4.6 1.1 6.5L12 17.6l-5.8 3.1 1.1-6.5-4.7-4.6 6.5-.9 2.9-6Z"/></svg>';
}

function starsSvg($rata, $size = 16) {
    $rata = max(0, min(5, (float)$rata));
    $html = '<span class="stars-row">';
    for ($i = 1; $i <= 5; $i++) {
        $diff = $rata - ($i - 1);
        if ($diff >= 1) {
            $html .= '<span class="star-slot" style="width:'.$size.'px;height:'.$size.'px;">'.starIconSvg('full', $size).'</span>';
        } elseif ($diff <= 0) {
            $html .= '<span class="star-slot" style="width:'.$size.'px;height:'.$size.'px;">'.starIconSvg('empty', $size).'</span>';
        } else {
            $pct = round($diff * 100);
            $html .= '<span class="star-slot" style="width:'.$size.'px;height:'.$size.'px;">'
                   . starIconSvg('empty', $size)
                   . '<span class="star-fill" style="width:'.$pct.'%;">'.starIconSvg('full', $size).'</span>'
                   . '</span>';
        }
    }
    $html .= '</span>';
    return $html;
}

/**
 * ============================================================
 *  UI HELPER — icon()
 * ============================================================
 */
function icon($name, $class = 'w-5 h-5') {
    $paths = [
        'menu'         => '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="14" y2="17"/>',
        'close'        => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'search'       => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
        'chevron-right'=> '<polyline points="9 18 15 12 9 6"/>',
        'home'         => '<path d="M4 11.5 12 4l8 7.5"/><path d="M6 10.5V20h4v-6h4v6h4v-9.5"/>',
        'dashboard'    => '<rect x="3.5" y="3.5" width="7" height="8" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="14.5" width="7" height="6" rx="1.5"/>',
        'book'         => '<path d="M4 5.5c2.2-1 5-1 7 .3v13.7c-2-1.3-4.8-1.3-7-.3V5.5Z"/><path d="M20 5.5c-2.2-1-5-1-7 .3v13.7c2-1.3 4.8-1.3 7-.3V5.5Z"/>',
        'tag'          => '<rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1.5"/><rect x="14" y="3.5" width="6.5" height="6.5" rx="1.5"/><rect x="3.5" y="14" width="6.5" height="6.5" rx="1.5"/><rect x="14" y="14" width="6.5" height="6.5" rx="1.5"/>',
        'info'         => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16.5"/><circle cx="12" cy="7.7" r="0.9" fill="currentColor" stroke="none"/>',
        'help'         => '<circle cx="12" cy="12" r="9"/><path d="M9.7 9.2a2.45 2.45 0 1 1 4.35 1.55c-.82.82-1.9 1.2-1.9 2.55"/><circle cx="12" cy="16.5" r=".8" fill="currentColor" stroke="none"/>',
        'mail'         => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'phone'        => '<path d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 0 0 5.5 5.5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4.5 5.7 2 2 0 0 1 6.5 3.5Z"/>',
        'pin'          => '<path d="M12 21s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12Z"/><circle cx="12" cy="9" r="2.4"/>',
        'login'        => '<path d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"/><polyline points="15 8 19 12 15 16"/><line x1="19" y1="12" x2="8.5" y2="12"/>',
        'arrow-right'  => '<line x1="4" y1="12" x2="19" y2="12"/><polyline points="13 6 19 12 13 18"/>',
        'sparkle'      => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'clock'        => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/>',
    ];
    $d = $paths[$name] ?? '';
    return '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'.$d.'</svg>';
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Perpustakaan Digital Sekolah — Perpustakaan Digital Ilmu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          heading: ['Poppins', 'sans-serif'],
          body: ['Inter', 'sans-serif'],
        },
        colors: {
          navy: { 950: '#071325', 900: '#0B1F3A', 800: '#132d53', 700: '#1d3f72' },
          ocean: { 500: '#0EA5E9', 400: '#38BDF8', 600: '#0284C7' },
          royal: { 600: '#2563EB', 500: '#3B82F6' }
        },
        boxShadow: {
          card: '0 4px 25px -4px rgba(11, 31, 58, 0.05)',
          cardHover: '0 24px 45px -12px rgba(14, 165, 233, 0.22)',
          slider: '0 25px 50px -12px rgba(7, 19, 37, 0.35)',
          premium: '0 10px 40px -10px rgba(11, 31, 58, 0.12)',
          nav: '0 1px 0 rgba(14, 165, 233, 0.08), 0 12px 30px -18px rgba(11, 31, 58, 0.12)',
        }
      }
    }
  }
</script>
<style>
  :root{ --sbw: 300px; }
  body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #1e293b; overflow-x: hidden; }
  h1, h2, h3, .font-heading { font-family: 'Poppins', sans-serif; }

  /* Background Noise & Abstract Wave Accents */
  .ocean-bg-pattern {
    background-color: #F8FAFC;
    background-image: 
      radial-gradient(at 0% 0%, rgba(14, 165, 233, 0.06) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(37, 99, 235, 0.05) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(56, 189, 248, 0.03) 0px, transparent 60%);
  }

  .scrollbar-none::-webkit-scrollbar { display: none; }
  .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }

  #navbar { transition: all .35s cubic-bezier(0.16, 1, 0.3, 1); }
  #navbar.shrink { box-shadow: 0 1px 0 rgba(14,165,233,.08), 0 12px 28px -20px rgba(11,31,58,.16); }
  #navbar.shrink .nav-inner { height: 56px; }
  .nav-inner { height: 68px; transition: height .35s cubic-bezier(0.16, 1, 0.3, 1); }

  #sidebar {
    width: var(--sbw); transform: translateX(-100%);
    transition: transform .38s cubic-bezier(0.16, 1, 0.3, 1);
  }
  #sidebar.open { transform: translateX(0); }
  #sidebarOverlay {
    opacity: 0; pointer-events: none; backdrop-filter: blur(6px);
    background: rgba(7,19,37,.6);
    transition: opacity .35s cubic-bezier(0.16, 1, 0.3, 1);
  }
  #sidebarOverlay.show { opacity: 1; pointer-events: auto; }
  .side-link { position: relative; transition: background-color .25s ease, color .25s ease, transform .2s ease; }
  .side-link:hover { transform: translateX(3px); }
  .side-link .side-ind {
    position: absolute; left: 0; top: 50%; transform: translateY(-50%) scaleY(0);
    width: 4px; height: 60%; border-radius: 999px; background: #0EA5E9;
    transition: transform .25s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .side-link.active .side-ind,
  .side-link:hover .side-ind { transform: translateY(-50%) scaleY(1); }
  .side-link.active { background: linear-gradient(90deg, rgba(14,165,233,.12), rgba(14,165,233,0)); color:#0284C7; }

  .slider-track { display: flex; transition: transform 0.7s cubic-bezier(0.16, 1, 0.3, 1); }
  .slider-item { min-width: 100%; box-sizing: border-box; }
  @keyframes floatY { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
  .float-slow { animation: floatY 6s ease-in-out infinite; }

  .buku-card { transition: transform .25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .25s cubic-bezier(0.16, 1, 0.3, 1), border-color .25s ease; }
  .buku-card:hover { transform: translateY(-3px); border-color: rgba(14,165,233,.3); }
  .cover-img { transition: transform .5s cubic-bezier(0.16, 1, 0.3, 1); }
  .buku-card:hover .cover-img { transform: scale(1.035); }

  .reveal { opacity: 0; transform: translateY(22px); transition: opacity .6s cubic-bezier(0.16, 1, 0.3, 1), transform .6s cubic-bezier(0.16, 1, 0.3, 1); }
  .reveal.in-view { opacity: 1; transform: translateY(0); }

  .overlay {
    display: none; position: fixed; inset: 0; z-index: 100;
    background: rgba(7, 19, 37, 0.68); backdrop-filter: blur(14px);
    align-items: center; justify-content: center; padding: 16px;
    opacity: 0; transition: opacity .3s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .overlay.show { display: flex; opacity: 1; }

  .genre-hidden, .buku-hidden { display: none !important; }

  .btn-press { transition: transform .18s ease, box-shadow .25s ease, background-color .25s ease; }
  .btn-press:active { transform: scale(.97); }

  .cat-pill { transition: all .25s cubic-bezier(0.16, 1, 0.3, 1); }
  .cat-pill:hover { transform: translateY(-1px); }

  /* ===== RATING DISPLAY - ALWAYS HORIZONTAL ===== */
  .stars-row {
    display: inline-flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    flex-wrap: nowrap !important;
    gap: 2px !important;
    width: max-content !important;
    height: auto !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
  }
  .stars-row .star-slot {
    display: inline-flex !important;
    position: relative !important;
    flex: 0 0 auto !important;
    align-items: center !important;
    justify-content: center !important;
    width: auto;
    height: auto;
    line-height: 0 !important;
  }
  .stars-row .star-slot > svg {
    display: block !important;
    flex: 0 0 auto !important;
  }
  .stars-row .star-fill {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    bottom: 0 !important;
    overflow: hidden !important;
    display: block !important;
    pointer-events: none !important;
  }
  .stars-row .star-fill > svg {
    display: block !important;
    max-width: none !important;
  }

  /* ============================================================
     BOOK DETAIL MODAL — Redesign (namespaced & self-contained)
     ============================================================ */
  .book-detail-modal {
    position: relative;
    width: 980px;
    max-width: 95vw;
    max-height: 88vh;
    background: #ffffff;
    border-radius: 26px;
    overflow: hidden;
    box-shadow: 0 40px 90px -20px rgba(11,31,58,.45), 0 0 0 1px rgba(11,31,58,.03);
    display: flex;
    flex-direction: row;
    transform: scale(.94) translateY(14px);
    opacity: 0;
    transition: transform .38s cubic-bezier(0.16,1,0.3,1), opacity .38s cubic-bezier(0.16,1,0.3,1);
  }
  .overlay.show .book-detail-modal { transform: scale(1) translateY(0); opacity: 1; }

  .book-detail-close {
    position: absolute; top: 16px; right: 16px; z-index: 20;
    width: 40px; height: 40px; border-radius: 999px;
    background: #F1F5F9; color: #334155; border: 1px solid #E2E8F0;
    display: flex; align-items: center; justify-content: center;
    transition: background-color .2s ease, color .2s ease, transform .2s ease;
  }
  .book-detail-close:hover { background: #0B1F3A; color: #fff; transform: rotate(90deg); }

  /* ----- Kolom kiri: cover + aksi ----- */
  .book-detail-left {
    flex: 0 0 268px;
    background: #F8FAFC;
    border-right: 1px solid #E2E8F0;
    padding: 34px 26px 28px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
  }
  .book-detail-cover {
    width: 100%;
    aspect-ratio: 3 / 4.3;
    border-radius: 16px;
    overflow: hidden;
    background: #E2E8F0;
    box-shadow: 0 20px 40px -14px rgba(11,31,58,.32);
  }
  .book-detail-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .book-detail-cover .no-cover {
    width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
    color: #94A3B8; font-size: 11px; text-align: center; padding: 0 16px;
  }

  .book-detail-actions { margin-top: 22px; display: flex; flex-direction: column; gap: 10px; }
  .bd-btn {
    height: 46px; border-radius: 12px; font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    text-decoration: none; border: none; cursor: pointer;
    transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
  }
  .bd-btn:active { transform: scale(.97); }
  .bd-btn-primary {
    background: linear-gradient(135deg, #155EEF, #146EF5);
    color: #fff;
    box-shadow: 0 12px 26px -8px rgba(21,94,239,.55);
  }
  .bd-btn-primary:hover { box-shadow: 0 16px 30px -8px rgba(21,94,239,.6); transform: translateY(-2px); }
  .bd-btn-secondary {
    background: #EEF4FF;
    color: #155EEF;
    border: 1px solid #DCE7FE;
  }
  .bd-btn-secondary:hover { background: #E1EBFF; transform: translateY(-2px); }

  /* ----- Kolom kanan: hero + konten (scrollable) ----- */
  .book-detail-right { flex: 1; min-width: 0; display: flex; flex-direction: column; max-height: 100%; overflow: hidden; }

  .book-detail-hero {
    flex-shrink: 0;
    padding: 28px 34px 24px;
    background: linear-gradient(135deg, #0B1F3A, #155EEF);
    color: #fff;
  }
  .bd-title {
    font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 22px; line-height: 1.32;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    margin-bottom: 5px;
  }
  .bd-author { font-size: 13px; font-weight: 500; color: rgba(255,255,255,.78); margin-bottom: 14px; }
  .bd-badges { display: flex; flex-wrap: wrap; gap: 8px; }
  .bd-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 999px; font-size: 11px; font-weight: 700;
    background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); color: #fff;
  }
  .bd-badge-status { background: rgba(255,255,255,.95); color: #0B1F3A; }
  .bd-badge-status .dot { width: 6px; height: 6px; border-radius: 50%; background: #16A34A; display: inline-block; }

  .book-detail-content {
    flex: 1; overflow-y: auto; padding: 26px 34px 30px;
    scrollbar-width: thin; scrollbar-color: #CBD5E1 transparent;
  }
  .book-detail-content::-webkit-scrollbar { width: 6px; }
  .book-detail-content::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 999px; }
  .book-detail-content::-webkit-scrollbar-track { background: transparent; }

  .bd-content-grid {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    grid-template-areas: "tentang informasi" "ulasan rating";
    column-gap: 32px; row-gap: 30px;
  }
  .bd-area-tentang   { grid-area: tentang; }
  .bd-area-informasi { grid-area: informasi; }
  .bd-area-ulasan    { grid-area: ulasan; }
  .bd-area-rating    { grid-area: rating; }

  .bd-heading {
    font-family: 'Poppins', sans-serif; font-size: 12.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .05em; color: #0B1F3A;
    margin-bottom: 12px; padding-bottom: 9px; border-bottom: 1px solid #E2E8F0;
  }
  .bd-desc { font-size: 13.5px; line-height: 1.75; color: #475569; }

  .bd-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 18px; }
  .bd-info-item { min-width: 0; }
  .bd-info-label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #94A3B8; font-weight: 700; margin-bottom: 3px; }
  .bd-info-value { font-size: 13px; font-weight: 700; color: #0F172A; word-break: break-word; }
  .bd-status-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #15803D; background: #ECFDF5; border: 1px solid #BBF7D0; padding: 3px 10px; border-radius: 999px; }
  .bd-status-pill .dot { width: 6px; height: 6px; border-radius: 50%; background: #16A34A; }

  .bd-rating-box {
    background: linear-gradient(135deg, #EEF4FF, #ffffff);
    border: 1px solid #DCE7FE; border-radius: 16px; padding: 18px;
    display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px;
  }
  .bd-rating-score { font-family: 'Poppins', sans-serif; font-size: 30px; font-weight: 800; color: #0B1F3A; line-height: 1; }
  .bd-rating-count { font-size: 11px; color: #64748B; font-weight: 600; }

  /* ----- Interactive rating (nama class dipertahankan untuk JS) ----- */
  .rating-input-card {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 16px;
    margin-top: 14px;
  }
  .rating-selected-badge {
    flex-shrink: 0; display: inline-flex; align-items: center;
    padding: 6px 10px; border-radius: 999px;
    background: #EEF4FF; color: #155EEF; font-size: 10px; font-weight: 800;
  }
  .star-input-horizontal { display: flex; align-items: center; gap: 4px; min-height: 44px; }
  .rating-star-btn {
    appearance: none; border: 0; background: transparent; padding: 2px; margin: 0;
    font-size: 30px; line-height: 1; color: #D5DCE6; cursor: pointer;
    transition: color .15s ease, transform .15s ease, filter .15s ease;
  }
  .rating-star-btn:hover, .rating-star-btn.preview, .rating-star-btn.selected {
    color: #F5A623; filter: drop-shadow(0 3px 5px rgba(245,166,35,.25));
  }
  .rating-star-btn:hover, .rating-star-btn.preview { transform: translateY(-2px) scale(1.04); }
  .rating-help-text { min-height: 18px; margin-top: 2px; color: #64748B; font-size: 11px; font-weight: 700; }
  .rating-submit-btn {
    margin-top: 10px; width: 100%; border: 0; border-radius: 12px; padding: 10px 14px;
    background: linear-gradient(135deg, #155EEF, #146EF5); color: #fff;
    font-size: 12px; font-weight: 800; cursor: pointer;
    box-shadow: 0 8px 18px rgba(21,94,239,.25);
    transition: transform .18s ease, opacity .18s ease, box-shadow .18s ease;
  }
  .rating-submit-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(21,94,239,.32); }
  .rating-submit-btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }
  .rating-message { min-height: 18px; margin: 7px 0 0; font-size: 11px; font-weight: 700; }
  .rating-message.success { color: #15803D; }
  .rating-message.error { color: #DC2626; }
  .rating-login-note {
    display: block; padding: 11px 12px; border-radius: 12px;
    background: #F8FAFC; border: 1px solid #E2E8F0; color: #64748B;
    font-size: 11px; line-height: 1.55; margin-top: 14px;
  }
  .rating-login-link { text-decoration: none; color: #155EEF; font-weight: 700; }

  /* ----- Ulasan pembaca (list, bukan card besar) ----- */
  .komentar-list { margin-top: 16px; display: flex; flex-direction: column; max-height: 220px; overflow-y: auto; padding-right: 4px; }
  .komentar-item { display: flex; gap: 10px; padding: 12px 0; border-bottom: 1px solid #E2E8F0; }
  .komentar-item:last-child { border-bottom: none; }
  .komentar-avatar {
    flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #0B1F3A, #155EEF); color: #fff;
    font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center;
  }
  .komentar-body { flex: 1; min-width: 0; }
  .komentar-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
  .komentar-nama { font-weight: 700; font-size: 12px; color: #0B1F3A; }
  .komentar-tgl { font-size: 10.5px; color: #94A3B8; white-space: nowrap; }
  .komentar-isi { font-size: 12.5px; color: #475569; margin-top: 3px; line-height: 1.55; }
  .komentar-kosong { font-size: 12.5px; color: #94A3B8; font-style: italic; margin-top: 12px; }

  /* ----- Responsive ----- */
  @media (max-width: 768px) {
    .book-detail-modal { flex-direction: column; max-height: 92vh; border-radius: 20px; width: 95vw; }
    .book-detail-left {
      flex: 0 0 auto; flex-direction: row; align-items: flex-start; gap: 18px;
      border-right: none; border-bottom: 1px solid #E2E8F0; padding: 22px 20px; overflow: visible;
    }
    .book-detail-cover { width: 128px; flex-shrink: 0; }
    .book-detail-actions { flex: 1; margin-top: 0; justify-content: center; }
    .book-detail-hero { padding: 20px 20px 16px; }
    .bd-title { font-size: 19px; }
    .book-detail-content { padding: 20px 20px 24px; }
    .bd-content-grid {
      grid-template-columns: 1fr;
      grid-template-areas: "informasi" "tentang" "rating" "ulasan";
      row-gap: 24px;
    }
  }
  @media (max-width: 480px) {
    .book-detail-modal { width: 96vw; max-height: 94vh; border-radius: 18px; }
    .book-detail-left { flex-direction: column; align-items: stretch; }
    .book-detail-cover { width: 100%; max-width: 220px; margin: 0 auto; aspect-ratio: 3/4.3; }
    .star-input-horizontal { justify-content: flex-start; gap: 1px; }
    .rating-star-btn { font-size: 28px; }
  }
</style>
</head>
<body class="antialiased ocean-bg-pattern selection:bg-ocean-500 selection:text-white">

  <!-- ===== OVERLAY SIDEBAR ===== -->
  <div id="sidebarOverlay" class="fixed inset-0 z-[90]" onclick="closeSidebar()"></div>

  <!-- ===== SIDEBAR NAVIGATION (Luxury Ocean Theme) ===== -->
  <aside id="sidebar" class="fixed top-0 left-0 h-full z-[95] bg-white shadow-2xl rounded-r-[28px] flex flex-col overflow-hidden border-r border-slate-100">
    <div class="flex items-center justify-between px-6 h-20 border-b border-slate-100 shrink-0">
      <a href="#" class="flex items-center gap-3 group">
        <div class="relative w-10 h-10 shrink-0">
          <img src="assets/logo-sekolah.png" alt="Logo Sekolah"
               class="w-full h-full object-contain rounded-xl transition-transform duration-300 group-hover:scale-105"
               onerror="this.style.display='none'; document.getElementById('logoFallbackSide').style.display='flex';">
          <div id="logoFallbackSide" class="hidden w-full h-full rounded-xl bg-gradient-to-tr from-navy-900 to-ocean-500 items-center justify-center text-white font-bold text-base shadow-md shadow-ocean-500/20 font-heading">P</div>
        </div>
        <span class="font-heading font-bold text-navy-900 text-sm tracking-tight leading-tight">Perpustakaan<br>Digital Ilmu</span>
      </a>
      <button onclick="closeSidebar()" aria-label="Tutup menu" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:text-navy-900 hover:bg-slate-100 transition btn-press">
        <?= icon('close', 'w-5 h-5') ?>
      </button>
    </div>

    <nav class="flex-1 overflow-y-auto py-5 px-4 space-y-1">
      <span class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Menu Utama</span>
      <a href="siswa/login.php" class="side-link flex items-center gap-3 px-3.5 py-3 mt-1 rounded-2xl text-slate-600 hover:bg-slate-50 hover:text-ocean-600 font-semibold text-sm">
        <span class="side-ind"></span>
        <?= icon('dashboard', 'w-[18px] h-[18px]') ?> Dashboard
      </a>
      <a href="#" class="side-link active flex items-center gap-3 px-3.5 py-3 rounded-2xl font-semibold text-sm">
        <span class="side-ind"></span>
        <?= icon('home', 'w-[18px] h-[18px]') ?> Beranda
      </a>
      <a href="#koleksi" onclick="closeSidebar()" class="side-link flex items-center gap-3 px-3.5 py-3 rounded-2xl text-slate-600 hover:bg-slate-50 hover:text-ocean-600 font-semibold text-sm">
        <span class="side-ind"></span>
        <?= icon('book', 'w-[18px] h-[18px]') ?> Koleksi Novel
      </a>
      <a href="#koleksi" onclick="closeSidebar()" class="side-link flex items-center gap-3 px-3.5 py-3 rounded-2xl text-slate-600 hover:bg-slate-50 hover:text-ocean-600 font-semibold text-sm">
        <span class="side-ind"></span>
        <?= icon('tag', 'w-[18px] h-[18px]') ?> Kategori
      </a>

      <span class="block px-3 pt-5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Lainnya</span>
      <a href="#tentang" onclick="closeSidebar()" class="side-link flex items-center gap-3 px-3.5 py-3 mt-1 rounded-2xl text-slate-600 hover:bg-slate-50 hover:text-ocean-600 font-semibold text-sm">
        <span class="side-ind"></span>
        <?= icon('info', 'w-[18px] h-[18px]') ?> Tentang
      </a>
      <a href="#kontak" onclick="closeSidebar()" class="side-link flex items-center gap-3 px-3.5 py-3 rounded-2xl text-slate-600 hover:bg-slate-50 hover:text-ocean-600 font-semibold text-sm">
        <span class="side-ind"></span>
        <?= icon('mail', 'w-[18px] h-[18px]') ?> Kontak
      </a>
      <a href="bantuan.php" class="side-link flex items-center gap-3 px-3.5 py-3 rounded-2xl text-slate-600 hover:bg-slate-50 hover:text-ocean-600 font-semibold text-sm">
        <span class="side-ind"></span>
        <?= icon('help', 'w-[18px] h-[18px]') ?> Bantuan
      </a>
    </nav>
  </aside>

  <!-- ===== NAVBAR (Glassmorphism & Ocean Blue Accent) ===== -->
  <header id="navbar" class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-sky-100 shadow-nav">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 nav-inner flex items-center justify-between">
      <div class="flex items-center gap-2 sm:gap-4">
        <button onclick="openSidebar()" aria-label="Buka menu" class="w-11 h-11 rounded-2xl flex items-center justify-center text-navy-900 hover:bg-sky-50 transition btn-press shrink-0">
          <?= icon('menu', 'w-[22px] h-[22px]') ?>
        </button>

        <a href="#" class="flex items-center gap-3 group">
          <div class="relative w-10 h-10 md:w-11 md:h-11 shrink-0">
            <img src="assets/logo-sekolah.png" alt="Logo Sekolah"
                 class="w-full h-full object-contain rounded-2xl transition-transform duration-300 group-hover:scale-105 group-hover:-rotate-2"
                 onerror="this.style.display='none'; document.getElementById('logoFallbackNav').style.display='flex';">
            <div id="logoFallbackNav" class="hidden w-full h-full rounded-2xl bg-gradient-to-tr from-navy-900 to-ocean-500 items-center justify-center text-white font-bold text-lg shadow-md shadow-ocean-500/20 font-heading">P</div>
          </div>
          <div class="leading-tight">
            <span class="font-heading font-bold text-navy-900 text-[15px] md:text-lg tracking-tight block">Perpustakaan Digital</span>
            <span class="text-[10px] text-ocean-600 font-semibold tracking-widest uppercase block">Modern Marine Library</span>
          </div>
        </a>
      </div>

      <nav class="hidden lg:flex items-center gap-7 text-sm font-semibold text-slate-600">
        <a href="#" class="text-ocean-600 transition">Beranda</a>
        <a href="#koleksi" class="hover:text-ocean-600 transition">Koleksi Novel</a>
        <a href="#tentang" class="hover:text-ocean-600 transition">Tentang</a>
        <a href="bantuan.php" class="hover:text-ocean-600 transition">Bantuan</a>
        <a href="#kontak" class="hover:text-ocean-600 transition">Kontak</a>
      </nav>

      <div class="flex items-center gap-2 sm:gap-3">
        <a href="#koleksi" class="bg-navy-900 hover:bg-ocean-600 text-white text-xs md:text-sm font-semibold px-4 py-2 rounded-lg shadow-sm transition btn-press">Jelajahi Novel</a>
      </div>
    </div>
  </header>

  <!-- ===== HERO SECTION & SLIDER (Luxury Ocean Theme) ===== -->
  <section class="relative bg-gradient-to-b from-white via-sky-50/30 to-slate-100/40 pt-10 pb-10 md:pt-14 md:pb-14 overflow-hidden">
    
    <!-- Efek Cahaya Bawah Laut & Bubble Transparan Halus -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-gradient-to-r from-sky-400/10 to-blue-600/10 blur-[120px] pointer-events-none rounded-full"></div>
    <div class="absolute top-10 left-10 w-4 h-4 rounded-full bg-sky-400/20 blur-[1px] animate-pulse"></div>
    <div class="absolute bottom-12 right-20 w-6 h-6 rounded-full bg-ocean-400/20 blur-[1px] float-slow"></div>

    <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center relative z-10">

      <!-- KIRI: Hero Section (Typography Elegan & Search Bar Premium) -->
      <div class="lg:col-span-6 text-left reveal in-view">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-ocean-500/10 border border-ocean-500/20 text-ocean-600 text-xs font-bold mb-5 shadow-sm">
          <?= icon('sparkle', 'w-3.5 h-3.5') ?> Perpustakaan Digital Ilmu
        </div>

        <h1 class="font-heading font-extrabold text-[1.85rem] sm:text-3xl lg:text-[2.35rem] text-navy-900 leading-[1.22] tracking-tight mb-4">
          Gerbang Literasi & <span class="text-transparent bg-clip-text bg-gradient-to-r from-ocean-500 to-royal-600">Dunia Novel Modern</span>
        </h1>

        <p class="text-slate-600 text-sm md:text-[15px] leading-relaxed font-normal max-w-md mb-6">
          Nikmati kenyamanan membaca ribuan koleksi novel pilihan terbaik untuk pengalaman literasi yang berkesan.
        </p>

        <!-- Search Bar Premium -->
        <div class="max-w-lg mb-3">
          <div class="relative shadow-card rounded-xl bg-white/90 backdrop-blur-md border border-sky-100 p-1.5 flex items-center focus-within:ring-2 focus-within:ring-ocean-500/30 transition">
            <span class="pl-3.5 text-slate-400 shrink-0"><?= icon('search', 'w-[18px] h-[18px]') ?></span>
            <input
              id="searchInput"
              type="text"
              placeholder="Cari judul novel atau nama pengarang..."
              class="w-full pl-3 pr-4 py-2 bg-transparent text-navy-900 placeholder:text-slate-400 text-sm font-medium focus:outline-none"
              autocomplete="off"
            >
            <button onclick="triggerSearch()" class="bg-navy-900 hover:bg-ocean-600 text-white text-xs md:text-sm font-semibold px-5 py-2 rounded-lg transition shrink-0 btn-press">
              Cari
            </button>
          </div>
          <p id="searchCount" class="text-xs text-ocean-600 font-semibold mt-2 ml-2"></p>
        </div>
      </div>

      <!-- KANAN: Hero Slider (Tepat 3 Slider, Fade+Zoom, Glassmorphism, Shadow Premium) -->
      <div class="lg:col-span-6 relative reveal in-view">
        <div class="relative w-full max-w-[560px] h-[320px] md:h-[340px] mx-auto rounded-[1.75rem] overflow-hidden shadow-premium border border-sky-100 bg-navy-900 group float-slow">

          <div id="heroSlider" class="slider-track w-full h-full">
            <?php
              $sliders = [
                [
                  'img'   => 'assets/slider/slider1.jpg', 
                  'title' => 'Jelajahi Dunia Imajinasi Melalui Novel', 
                  'desc'  => 'Temukan berbagai koleksi novel terbaik mulai dari fiksi, romansa, petualangan, misteri hingga karya sastra pilihan.'
                ],
                [
                  'img'   => 'assets/slider/slider2.jpg', 
                  'title' => 'Ribuan Cerita Dalam Satu Perpustakaan', 
                  'desc'  => 'Baca novel favoritmu kapan saja dengan koleksi modern yang dirancang khusus untuk kenyamanan membaca Anda.'
                ],
                [
                  'img'   => 'assets/slider/slider3.jpg', 
                  'title' => 'Membaca Novel, Membuka Wawasan', 
                  'desc'  => 'Nikmati pengalaman eksplorasi literatur yang tenang, elegan, dan mendalam di era digital masa kini.'
                ]
              ];
              foreach($sliders as $index => $s):
            ?>
              <div class="slider-item relative w-full h-full flex items-end p-7 md:p-9">
                <div class="absolute inset-0 bg-navy-950 overflow-hidden">
                  <img src="<?= $s['img'] ?>" alt="Slider <?= $index+1 ?>" class="w-full h-full object-cover opacity-60 transform scale-100 group-hover:scale-105 transition-transform duration-1000 ease-out" onerror="this.style.display='none'">
                  <div class="absolute inset-0 bg-gradient-to-t from-navy-950 via-navy-900/50 to-transparent"></div>
                  <!-- Efek kilau air tipis di bagian bawah slider -->
                  <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-ocean-500 via-sky-300 to-royal-600 opacity-70"></div>
                </div>

                <div class="relative z-10 text-white max-w-lg">
                  <h3 class="font-heading font-bold text-xl md:text-2xl mb-1.5 leading-snug"><?= $s['title'] ?></h3>
                  <p class="text-slate-300 text-xs md:text-sm leading-relaxed"><?= $s['desc'] ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Tombol Next & Previous -->
          <button onclick="prevSlide()" aria-label="Sebelumnya" class="absolute left-4 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/10 hover:bg-white/25 text-white backdrop-blur-md flex items-center justify-center transition opacity-0 group-hover:opacity-100 btn-press border border-white/20">
            <?= icon('chevron-left', 'w-4 h-4') ?>
          </button>
          <button onclick="nextSlide()" aria-label="Berikutnya" class="absolute right-4 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/10 hover:bg-white/25 text-white backdrop-blur-md flex items-center justify-center transition opacity-0 group-hover:opacity-100 btn-press border border-white/20">
            <?= icon('chevron-right', 'w-4 h-4') ?>
          </button>

          <!-- Indikator Bulat Modern -->
          <div class="absolute bottom-4 right-5 z-20 flex items-center gap-1.5">
            <?php for($i=0; $i<count($sliders); $i++): ?>
              <button onclick="goToSlide(<?= $i ?>)" class="slider-dot h-2 rounded-full transition-all duration-300 <?= $i === 0 ? 'bg-ocean-400 w-6' : 'bg-white/40 w-2' ?>"></button>
            <?php endfor; ?>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- ===== SECTION KOLEKSI & FILTER KATEGORI ===== -->
  <main id="koleksi" class="max-w-7xl mx-auto px-6 py-8 md:py-10 scroll-mt-24">

    <div class="mb-6 reveal">
      <h2 class="font-heading font-bold text-2xl md:text-[1.7rem] text-navy-900 tracking-tight">
        Koleksi Novel & Buku Pilihan
      </h2>
      <div class="w-10 h-1 bg-ocean-500 rounded-full mt-2.5"></div>
      <p class="text-slate-500 text-sm mt-2.5">Jelajahi berbagai pilihan novel menarik.</p>
    </div>

    <!-- Sticky Filter Capsule -->
    <div class="sticky top-[56px] z-30 bg-white/90 backdrop-blur-xl py-2.5 mb-7 -mx-6 px-6 border-b border-sky-100">
      <div class="flex items-center gap-2 overflow-x-auto scrollbar-none">
        <button onclick="filterKategori('semua')" class="cat-pill shrink-0 bg-navy-900 text-white text-xs font-semibold px-3.5 py-2 rounded-full">
          Semua Kategori
        </button>
        <?php foreach ($kategoriList as $cat): ?>
          <button onclick="filterKategori('<?= htmlspecialchars($cat) ?>')" class="cat-pill shrink-0 bg-white hover:bg-ocean-500 hover:text-white hover:border-ocean-500 text-slate-700 border border-slate-200 text-xs font-semibold px-3.5 py-2 rounded-full">
            <?= htmlspecialchars($cat) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!$daftarBuku): ?>
      <div class="text-center py-24 bg-white rounded-[2rem] border border-slate-200 shadow-sm">
        <p class="text-slate-500 text-sm font-medium">Belum ada buku yang tersedia di perpustakaan saat ini.</p>
      </div>
    <?php else: ?>

      <!-- State Jika Tidak Ditemukan -->
      <div id="noResult" class="hidden text-center py-20 bg-white rounded-[2rem] border border-slate-200 shadow-sm">
        <div class="max-w-md mx-auto px-4">
          <div class="w-16 h-16 bg-ocean-500/10 text-ocean-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <?= icon('book', 'w-8 h-8') ?>
          </div>
          <p class="text-slate-700 font-bold text-base mb-1">Buku yang Anda cari belum tersedia.</p>
          <p class="text-slate-500 text-xs">Coba gunakan kata kunci lain atau pilih kategori yang berbeda.</p>
        </div>
      </div>

      <?php foreach ($bukuPerGenre as $genre => $daftar): ?>
        <div class="genre-section mb-10 reveal" data-genre-section data-genre-name="<?= htmlspecialchars($genre) ?>">
          <div class="flex items-center justify-between mb-5 border-b border-sky-100 pb-3">
            <div class="flex items-center gap-3">
              <div class="w-2 h-5 rounded-full bg-ocean-500"></div>
              <h3 class="font-heading font-bold text-lg text-navy-900 tracking-tight"><?= htmlspecialchars($genre) ?></h3>
            </div>
            <span class="text-xs font-bold text-ocean-600 bg-ocean-500/10 px-3 py-1 rounded-full" data-genre-count><?= count($daftar) ?> buku</span>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            <?php foreach ($daftar as $b): ?>
              <!-- Card Buku Mewah (Hover naik, shadow ocean, border berubah biru) -->
              <div class="buku-card bg-white rounded-2xl overflow-hidden border border-slate-200/80 shadow-card hover:shadow-md cursor-pointer flex flex-col justify-between group h-full"
                   onclick="bukaModal('modal-<?= $b['id_buku'] ?>')"
                   data-judul="<?= htmlspecialchars(strtolower($b['judul'])) ?>"
                   data-pengarang="<?= htmlspecialchars(strtolower($b['pengarang'])) ?>"
                   data-kategori="<?= htmlspecialchars($genre) ?>">

                <div>
                  <div class="relative w-full aspect-[3/4] bg-slate-100 overflow-hidden">
                    <?php if ($b['cover']): ?>
                      <img class="cover-img w-full h-full object-cover" src="uploads/<?= htmlspecialchars($b['cover']) ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
                    <?php else: ?>
                      <div class="w-full h-full flex items-center justify-center p-4">
                        <span class="text-xs text-slate-400 font-medium text-center">Cover Tidak Tersedia</span>
                      </div>
                    <?php endif; ?>
                    <span class="absolute top-2 left-2 bg-navy-900/75 text-sky-300 text-[9px] font-semibold px-2 py-0.5 rounded-md backdrop-blur-sm">
                      <?= htmlspecialchars($genre) ?>
                    </span>
                  </div>

                  <div class="p-4 flex flex-col gap-1.5">
                    <div class="font-bold text-xs md:text-sm text-navy-900 leading-snug line-clamp-2 group-hover:text-ocean-600 transition"><?= htmlspecialchars($b['judul']) ?></div>
                    <div class="text-xs text-slate-500 font-normal line-clamp-1"><?= htmlspecialchars($b['pengarang']) ?></div>
                    <?php $rCard = $ratingPerBuku[$b['id_buku']] ?? null; ?>
                    <div class="flex items-center gap-1.5">
                      <?= starsSvg($rCard['rata'] ?? 0, 12) ?>
                      <?php if ($rCard): ?><span class="text-[10px] text-slate-400 font-semibold"><?= number_format($rCard['rata'], 1) ?> (<?= $rCard['jumlah'] ?>)</span><?php endif; ?>
                    </div>
                  </div>
                </div>

                <div class="px-4 pb-3.5 pt-2.5 mt-auto flex items-center justify-between border-t border-slate-50">
                  <span class="text-[10px] text-slate-500 font-medium"><?= (int)$b['stok'] ?> tersedia</span>
                  <span class="text-[11px] font-semibold text-ocean-600 flex items-center gap-1 group-hover:gap-1.5 transition-all duration-300">
                    Detail <?= icon('arrow-right', 'w-3 h-3') ?>
                  </span>
                </div>

              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </main>

  <!-- ===== SECTION STATISTIK KOLEKSI (Publik, compact) ===== -->
  <section class="bg-white py-9 md:py-12 px-6 border-t border-sky-100">
    <div class="max-w-5xl mx-auto reveal">
      <div class="text-center mb-6 md:mb-7">
        <span class="text-[11px] font-bold uppercase tracking-wider text-ocean-600 bg-ocean-500/10 px-3 py-1 rounded-full mb-3 inline-block">Statistik Koleksi</span>
        <h2 class="font-heading font-bold text-xl md:text-2xl text-navy-900 tracking-tight">Ringkasan Koleksi Perpustakaan</h2>
        <p class="text-slate-500 text-xs md:text-sm mt-2 max-w-xl mx-auto">Gambaran singkat jumlah dan sebaran koleksi novel yang tersedia saat ini.</p>
      </div>

      <!-- Tiga angka kunci -->
      <div class="grid grid-cols-3 gap-2.5 sm:gap-3.5 max-w-xl mx-auto mb-5 md:mb-6">
        <div class="text-center bg-sky-50/60 border border-sky-100 rounded-xl px-2 py-3 sm:py-3.5">
          <div class="font-heading font-extrabold text-lg sm:text-2xl text-navy-900 leading-none"><?= $totalBukuAktif ?></div>
          <div class="text-[10px] sm:text-xs font-semibold text-slate-500 mt-1">Total Buku</div>
        </div>
        <div class="text-center bg-sky-50/60 border border-sky-100 rounded-xl px-2 py-3 sm:py-3.5">
          <div class="font-heading font-extrabold text-lg sm:text-2xl text-navy-900 leading-none"><?= $totalGenreAktif ?></div>
          <div class="text-[10px] sm:text-xs font-semibold text-slate-500 mt-1">Total Genre</div>
        </div>
        <div class="text-center bg-sky-50/60 border border-sky-100 rounded-xl px-2 py-3 sm:py-3.5">
          <?php if ($totalUlasanSemua > 0): ?>
            <div class="font-heading font-extrabold text-lg sm:text-2xl text-navy-900 leading-none"><?= number_format($rataRatingSemua, 1) ?></div>
            <div class="text-[10px] sm:text-xs font-semibold text-slate-500 mt-1">Rating (<?= $totalUlasanSemua ?>)</div>
          <?php else: ?>
            <div class="font-heading font-extrabold text-sm sm:text-lg text-navy-900 leading-tight line-clamp-1"><?= $genreTerbanyakNama ? htmlspecialchars($genreTerbanyakNama) : '-' ?></div>
            <div class="text-[10px] sm:text-xs font-semibold text-slate-500 mt-1">Genre Terbanyak</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Chart compact + breakdown genre -->
      <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-6 items-center max-w-3xl mx-auto">
        <div class="flex flex-col items-center">
          <div class="relative h-[180px] w-[180px] md:h-[230px] md:w-[230px]">
            <canvas id="chartGenreLanding"></canvas>
          </div>
          <?php if ($genreTerbanyakNama): ?>
            <p class="text-[11px] text-slate-400 text-center mt-3 max-w-[220px]">
              <span class="font-bold text-ocean-600"><?= htmlspecialchars($genreTerbanyakNama) ?></span> menjadi genre dengan koleksi terbanyak, yaitu <span class="font-bold text-navy-900"><?= $genreTerbanyakJumlah ?></span> judul.
            </p>
          <?php endif; ?>
        </div>
        <div class="flex flex-col gap-1.5" id="genreLegend">
          <?php foreach ($genreTop5 as $genre => $daftar): ?>
            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 bg-sky-50/50 px-3 py-2 rounded-lg">
              <span class="truncate pr-2"><?= htmlspecialchars($genre) ?></span>
              <span class="text-ocean-600 shrink-0"><?= count($daftar) ?> judul</span>
            </div>
          <?php endforeach; ?>
          <?php if ($genreSisaCount > 0): ?>
            <div class="flex items-center justify-between text-xs font-semibold text-slate-400 px-3 py-1.5 italic">
              <span>+<?= $genreSisaCount ?> genre lainnya</span>
              <span><?= $genreSisaJumlah ?> judul</span>
            </div>
          <?php endif; ?>
          <?php if (!$bukuPerGenre): ?>
            <p class="text-xs text-slate-400 italic">Belum ada data koleksi buku.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== TENTANG SECTION ===== -->
  <section id="tentang" class="bg-navy-900 text-white py-12 md:py-16 px-6 relative overflow-hidden mt-6 scroll-mt-20">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-navy-800 via-navy-900 to-navy-950 pointer-events-none"></div>
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-10 items-center relative z-10 reveal">
      <div>
        <span class="text-[11px] font-bold uppercase tracking-wider text-ocean-400 bg-ocean-500/10 px-3 py-1 rounded-full mb-3 inline-block">Tentang Layanan</span>
        <h2 class="font-heading font-extrabold text-xl md:text-2xl tracking-tight mb-3">Mendukung Budaya Literasi & Membaca Novel</h2>
        <p class="text-slate-300 text-sm leading-relaxed mb-5">
          Perpustakaan Digital Sekolah hadir dengan konsep Modern Marine untuk memudahkan siswa dan staf pengajar dalam mengakses literatur dan koleksi novel berkualitas secara praktis.
        </p>
        <div class="flex flex-wrap items-center gap-2.5 text-xs font-semibold text-slate-200">
          <div class="flex items-center gap-2 bg-white/5 px-3.5 py-2.5 rounded-xl border border-white/10"><?= icon('sparkle','w-4 h-4 text-ocean-400') ?> Peminjaman Mudah</div>
          <div class="flex items-center gap-2 bg-white/5 px-3.5 py-2.5 rounded-xl border border-white/10"><?= icon('book','w-4 h-4 text-ocean-400') ?> Baca di Tempat</div>
        </div>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-xl">
        <h3 class="font-heading font-bold text-base text-white mb-3.5 flex items-center gap-2"><?= icon('clock','w-[18px] h-[18px] text-ocean-400') ?> Jam Operasional Perpustakaan</h3>
        <ul class="space-y-2.5 text-sm text-slate-300">
          <li class="flex justify-between border-b border-white/10 pb-2.5">
            <span>Senin – Kamis</span>
            <span class="font-semibold text-white">07:30 - 15:30 WIB</span>
          </li>
          <li class="flex justify-between border-b border-white/10 pb-2.5">
            <span>Jumat</span>
            <span class="font-semibold text-white">07:30 - 14:00 WIB</span>
          </li>
          <li class="flex justify-between pb-1">
            <span>Sabtu & Minggu / Libur</span>
            <span class="font-semibold text-sky-400">Tutup</span>
          </li>
        </ul>
      </div>
    </div>
  </section>

  <!-- ===== FOOTER (Navy Malam / Night Ocean) ===== -->
  <footer id="kontak" class="bg-navy-950 text-slate-400 pt-12 pb-6 px-6 text-xs border-t border-sky-900/30 scroll-mt-20 relative">
    <!-- Garis ombak tipis di bagian atas footer -->
    <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-ocean-500 to-transparent opacity-60"></div>

    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8 reveal">

      <div>
        <div class="flex items-center gap-3 mb-4">
          <div class="relative w-10 h-10 shrink-0">
            <img src="assets/logo-sekolah.png" alt="Logo Sekolah"
                 class="w-full h-full object-contain rounded-xl"
                 onerror="this.style.display='none'; document.getElementById('logoFallbackFooter').style.display='flex';">
            <div id="logoFallbackFooter" class="hidden w-full h-full rounded-xl bg-gradient-to-tr from-navy-900 to-ocean-500 items-center justify-center text-white font-bold text-lg shadow-md shadow-ocean-500/20 font-heading">P</div>
          </div>
          <span class="font-heading font-bold text-white text-base tracking-tight">Perpustakaan Digital</span>
        </div>
        <p class="text-slate-400 text-xs leading-relaxed max-w-sm">
          Pusat layanan literasi digital sekolah berstandar modern untuk memudahkan akses referensi akademik dan koleksi novel pilihan.
        </p>
      </div>

      <div>
        <div class="font-heading font-bold text-white text-sm mb-4 tracking-wide uppercase">Menu Cepat</div>
        <ul class="space-y-2.5 text-slate-400 font-medium">
          <li><a href="#" class="hover:text-ocean-400 hover:pl-1 transition-all duration-200">Beranda</a></li>
          <li><a href="#koleksi" class="hover:text-ocean-400 hover:pl-1 transition-all duration-200">Koleksi Novel</a></li>
          <li><a href="#tentang" class="hover:text-ocean-400 hover:pl-1 transition-all duration-200">Tentang</a></li>
          <li><a href="bantuan.php" class="hover:text-ocean-400 hover:pl-1 transition-all duration-200">Bantuan</a></li>
          <li><a href="#kontak" class="hover:text-ocean-400 hover:pl-1 transition-all duration-200">Kontak</a></li>
        </ul>
      </div>

      <div>
        <div class="font-heading font-bold text-white text-sm mb-4 tracking-wide uppercase">Informasi</div>
        <ul class="space-y-3 text-slate-400">
          <li class="flex items-start gap-2.5 leading-relaxed"><?= icon('pin','w-4 h-4 mt-0.5 text-ocean-400 shrink-0') ?> Jl. Samas Km. 11 (Kreteg Abang) Ngemplak, Srigading, Sanden, Bantul, Yogyakarta</li>
          <li class="flex items-center gap-2.5"><?= icon('mail','w-4 h-4 text-ocean-400 shrink-0') ?> sekolah@smkn1sanden.sch.id</li>
          <li class="flex items-center gap-2.5"><?= icon('phone','w-4 h-4 text-ocean-400 shrink-0') ?> 0889-5758-621</li>
        </ul>
      </div>

    </div>

    <div class="max-w-7xl mx-auto pt-5 border-t border-sky-900/20 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
      <div>&copy; 2026 Perpustakaan Digital Sekolah. All Rights Reserved.</div>
      <div>
        <a href="admin/login.php" class="hover:text-ocean-400 transition font-medium inline-flex items-center gap-1.5"><?= icon('login','w-3.5 h-3.5') ?> Administrator Login</a>
      </div>
    </div>
  </footer>

  <!-- ===== MODAL DETAIL BUKU ===== -->
  <?php foreach ($daftarBuku as $b): ?>
    <div class="overlay" id="modal-<?= $b['id_buku'] ?>">
      <div class="book-detail-modal">
        <button onclick="tutupModal('modal-<?= $b['id_buku'] ?>')"
                class="book-detail-close" aria-label="Tutup detail buku">
          <?= icon('close','w-4 h-4') ?>
        </button>

        <!-- ===== KOLOM KIRI: COVER + AKSI ===== -->
        <div class="book-detail-left">
          <div class="book-detail-cover">
            <?php if ($b['cover']): ?>
              <img src="uploads/<?= htmlspecialchars($b['cover']) ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
            <?php else: ?>
              <span class="no-cover">Tanpa Cover</span>
            <?php endif; ?>
          </div>

          <div class="book-detail-actions">
            <a href="siswa/login.php" class="bd-btn bd-btn-primary">
              <?= icon('book','w-4 h-4') ?> Pinjam Buku
            </a>
            <a href="siswa/login.php" class="bd-btn bd-btn-secondary">
              <?= icon('book','w-4 h-4') ?> Baca di Tempat
            </a>
          </div>
        </div>

        <!-- ===== KOLOM KANAN: HERO + KONTEN (SCROLLABLE) ===== -->
        <div class="book-detail-right">

          <div class="book-detail-hero">
            <h2 class="bd-title"><?= htmlspecialchars($b['judul']) ?></h2>
            <div class="bd-author">Penulis · <?= htmlspecialchars($b['pengarang']) ?></div>
            <div class="bd-badges">
              <span class="bd-badge"><?= htmlspecialchars($b['nama_kategori'] ?? 'Lainnya') ?></span>
              <span class="bd-badge bd-badge-status"><span class="dot"></span> <?= (int)$b['stok'] ?> Tersedia</span>
            </div>
          </div>

          <div class="book-detail-content">
            <div class="bd-content-grid">

              <!-- Tentang Buku -->
              <div class="bd-section bd-area-tentang">
                <h3 class="bd-heading">Tentang Buku</h3>
                <div class="bd-desc"><?= nl2br(htmlspecialchars($b['deskripsi'] ?: 'Belum ada deskripsi atau sinopsis untuk buku ini.')) ?></div>
              </div>

              <!-- Informasi Buku -->
              <div class="bd-section bd-area-informasi">
                <h3 class="bd-heading">Informasi Buku</h3>
                <div class="bd-info-grid">
                  <div class="bd-info-item">
                    <span class="bd-info-label">Penulis</span>
                    <span class="bd-info-value"><?= htmlspecialchars($b['pengarang']) ?></span>
                  </div>
                  <?php if (!empty($b['kode_buku'])): ?>
                  <div class="bd-info-item">
                    <span class="bd-info-label">Kode Buku</span>
                    <span class="bd-info-value"><?= htmlspecialchars($b['kode_buku']) ?></span>
                  </div>
                  <?php endif; ?>
                  <div class="bd-info-item">
                    <span class="bd-info-label">Kategori</span>
                    <span class="bd-info-value"><?= htmlspecialchars($b['nama_kategori'] ?? 'Lainnya') ?></span>
                  </div>
                  <div class="bd-info-item">
                    <span class="bd-info-label">Penerbit</span>
                    <span class="bd-info-value"><?= htmlspecialchars($b['penerbit'] ?? '-') ?></span>
                  </div>
                  <div class="bd-info-item">
                    <span class="bd-info-label">Tahun Terbit</span>
                    <span class="bd-info-value"><?= htmlspecialchars($b['tahun_terbit'] ?? '-') ?></span>
                  </div>
                  <div class="bd-info-item">
                    <span class="bd-info-label">Lokasi Rak</span>
                    <span class="bd-info-value"><?= htmlspecialchars($b['lokasi_rak'] ?: '-') ?></span>
                  </div>
                  <div class="bd-info-item">
                    <span class="bd-info-label">Status</span>
                    <span class="bd-status-pill"><span class="dot"></span> <?= (int)$b['stok'] ?> Buku Tersedia</span>
                  </div>
                </div>
              </div>

              <!-- Ulasan Pembaca (input rating + daftar komentar) -->
              <div class="bd-section bd-area-ulasan">
                <h3 class="bd-heading">Ulasan Pembaca</h3>

                <!-- ===== INPUT RATING (fungsi lama dipertahankan) ===== -->
                <div class="rating-input-card">
                  <div class="flex items-start justify-between gap-3 mb-1">
                    <div>
                      <span class="block text-sm font-bold text-navy-900">Berikan Penilaian</span>
                      <span class="block text-[11px] text-slate-400 mt-0.5">
                        <?= !empty($idAnggotaLogin) ? 'Nilai 1–5 sesuai pengalaman membaca kamu.' : 'Login sebagai anggota untuk memberikan rating.' ?>
                      </span>
                    </div>
                    <span class="rating-selected-badge" id="ratingBadge-<?= $b['id_buku'] ?>">
                      <?php
                        $trIdBuku = $transaksiRatingPerBuku[$b['id_buku']] ?? null;
                        $nilaiSaya = $trIdBuku ? ($ratingSayaPerBuku[$trIdBuku] ?? 0) : 0;
                      ?>
                      <?= $nilaiSaya ? $nilaiSaya . '/5' : 'Belum dipilih' ?>
                    </span>
                  </div>

                  <?php if (!empty($idAnggotaLogin) && $trIdBuku): ?>
                    <form class="rating-form" data-book="<?= (int)$b['id_buku'] ?>">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($ratingCsrf) ?>">
                      <input type="hidden" name="id_buku" value="<?= (int)$b['id_buku'] ?>">
                      <input type="hidden" name="nilai" id="ratingValue-<?= $b['id_buku'] ?>" value="<?= (int)$nilaiSaya ?>">

                      <div class="star-input-horizontal" role="radiogroup" aria-label="Pilih rating 1 sampai 5">
                        <?php for ($star = 1; $star <= 5; $star++): ?>
                          <button
                            type="button"
                            class="rating-star-btn <?= ($star <= $nilaiSaya) ? 'selected' : '' ?>"
                            data-value="<?= $star ?>"
                            aria-label="<?= $star ?> dari 5"
                            aria-checked="<?= $star === $nilaiSaya ? 'true' : 'false' ?>"
                          >★</button>
                        <?php endfor; ?>
                      </div>

                      <div class="rating-help-text" id="ratingHelp-<?= $b['id_buku'] ?>">
                        <?php
                          $labelsRating = [
                            1 => 'Sangat Buruk',
                            2 => 'Buruk',
                            3 => 'Cukup',
                            4 => 'Bagus',
                            5 => 'Sangat Bagus'
                          ];
                          echo $nilaiSaya ? ($nilaiSaya . ' dari 5 — ' . $labelsRating[$nilaiSaya]) : 'Klik bintang untuk memilih rating';
                        ?>
                      </div>

                      <button type="submit" class="rating-submit-btn">
                        Simpan Rating
                      </button>
                      <p class="rating-message" id="ratingMessage-<?= $b['id_buku'] ?>" aria-live="polite"></p>
                    </form>
                  <?php elseif (!empty($idAnggotaLogin)): ?>
                    <div class="rating-login-note">
                      Kamu belum memiliki riwayat peminjaman buku ini. <strong>Pinjam buku terlebih dahulu</strong> agar bisa memberikan rating.
                    </div>
                  <?php else: ?>
                    <a href="siswa/login.php" class="rating-login-note rating-login-link">
                      Login sebagai anggota untuk memberikan rating →
                    </a>
                  <?php endif; ?>
                </div>

                <?php $komentarBuku = $komentarPerBuku[$b['id_buku']] ?? []; ?>
                <?php if ($komentarBuku): ?>
                  <div class="komentar-list">
                    <?php foreach ($komentarBuku as $k):
                      $inisial = strtoupper(substr(trim($k['nama_lengkap']), 0, 1)) ?: '?';
                      $tgl = date('d M Y', strtotime($k['created_at']));
                    ?>
                      <div class="komentar-item">
                        <div class="komentar-avatar"><?= htmlspecialchars($inisial) ?></div>
                        <div class="komentar-body">
                          <div class="komentar-head">
                            <span class="komentar-nama"><?= htmlspecialchars($k['nama_lengkap']) ?></span>
                            <span class="komentar-tgl"><?= $tgl ?></span>
                          </div>
                          <?php if ($k['nilai']): ?>
                            <div class="mb-1"><?= starsSvg($k['nilai'], 11) ?></div>
                          <?php endif; ?>
                          <div class="komentar-isi"><?= nl2br(htmlspecialchars($k['isi_komentar'])) ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p class="komentar-kosong">Belum ada ulasan untuk buku ini.</p>
                <?php endif; ?>
              </div>

              <!-- Rating -->
              <div class="bd-section bd-area-rating">
                <h3 class="bd-heading">Rating Buku</h3>
                <?php $r = $ratingPerBuku[$b['id_buku']] ?? null; ?>
                <div class="bd-rating-box">
                  <?php if ($r): ?>
                    <div class="bd-rating-score"><?= number_format($r['rata'], 1) ?></div>
                    <?= starsSvg($r['rata'], 18) ?>
                    <div class="bd-rating-count"><?= $r['jumlah'] ?> ulasan</div>
                  <?php else: ?>
                    <?= starsSvg(0, 18) ?>
                    <div class="bd-rating-count">Belum ada rating</div>
                  <?php endif; ?>
                </div>
              </div>

            </div>
          </div>

        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- ===== FLOATING HELP / BANTUAN ===== -->
  <a href="bantuan.php"
     class="fixed bottom-5 right-5 sm:bottom-6 sm:right-6 z-40 group flex items-center gap-2
            bg-ocean-600 hover:bg-ocean-500
            text-white font-semibold text-xs sm:text-[13px] px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl
            shadow-md shadow-ocean-500/20
            transition-all duration-300 hover:-translate-y-0.5 btn-press"
     aria-label="Buka halaman bantuan">
    <span class="w-6 h-6 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
      <?= icon('help', 'w-[14px] h-[14px]') ?>
    </span>
    <span class="hidden sm:inline">Butuh Bantuan?</span>
  </a>

  <!-- JavaScript Pendukung -->
  <script>
    /* ===== Grafik Statistik Koleksi per Genre ===== */
    const genreLabels = <?= json_encode($genreChartLabels) ?>;
    const genreData = <?= json_encode($genreChartData) ?>;
    const genreCanvas = document.getElementById('chartGenreLanding');

    if (genreCanvas && genreLabels.length) {
      const oceanPalette = ['#0EA5E9', '#2563EB', '#38BDF8', '#0B1F3A', '#60A5FA', '#0284C7', '#93C5FD', '#1D3F72'];
      new Chart(genreCanvas, {
        type: 'doughnut',
        data: {
          labels: genreLabels,
          datasets: [{
            data: genreData,
            backgroundColor: genreLabels.map((_, i) => oceanPalette[i % oceanPalette.length]),
            borderColor: '#ffffff',
            borderWidth: 3,
            hoverOffset: 6,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: '#0B1F3A',
              titleFont: { family: 'Poppins', size: 13 },
              bodyFont: { family: 'Inter', size: 12 },
              padding: 10,
              cornerRadius: 8,
              callbacks: {
                label: (ctx) => ` ${ctx.label}: ${ctx.raw} judul`
              }
            }
          }
        }
      });
    }

    /* ===== Sidebar ===== */
    const sidebarEl = document.getElementById('sidebar');
    const sidebarOverlayEl = document.getElementById('sidebarOverlay');
    function openSidebar() {
      sidebarEl.classList.add('open');
      sidebarOverlayEl.classList.add('show');
      document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
      sidebarEl.classList.remove('open');
      sidebarOverlayEl.classList.remove('show');
      document.body.style.overflow = '';
    }
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') closeSidebar();
    });

    /* ===== Navbar shrink on scroll ===== */
    const navbarEl = document.getElementById('navbar');
    function handleNavShrink() {
      if (window.scrollY > 24) navbarEl.classList.add('shrink');
      else navbarEl.classList.remove('shrink');
    }
    window.addEventListener('scroll', handleNavShrink, { passive: true });
    handleNavShrink();

    /* ===== Scroll reveal ===== */
    const revealTargets = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('in-view');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12 });
      revealTargets.forEach(el => io.observe(el));
    } else {
      revealTargets.forEach(el => el.classList.add('in-view'));
    }

    /* ===== Slider ===== */
    let currentSlide = 0;
    const sliderTrack = document.getElementById('heroSlider');
    const totalSlides = sliderTrack ? sliderTrack.children.length : 0;
    const dots = document.querySelectorAll('.slider-dot');

    function updateSlider() {
      if (!sliderTrack) return;
      sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
      dots.forEach((dot, idx) => {
        if (idx === currentSlide) {
          dot.classList.add('bg-ocean-400', 'w-6');
          dot.classList.remove('bg-white/40', 'w-2');
        } else {
          dot.classList.remove('bg-ocean-400', 'w-6');
          dot.classList.add('bg-white/40', 'w-2');
        }
      });
    }

    function nextSlide() {
      currentSlide = (currentSlide + 1) % totalSlides;
      updateSlider();
    }

    function prevSlide() {
      currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
      updateSlider();
    }

    function goToSlide(index) {
      currentSlide = index;
      updateSlider();
    }

    let sliderInterval = setInterval(nextSlide, 5000);
    const heroSliderContainer = document.querySelector('.group');
    if (heroSliderContainer) {
      heroSliderContainer.addEventListener('mouseenter', () => clearInterval(sliderInterval));
      heroSliderContainer.addEventListener('mouseleave', () => sliderInterval = setInterval(nextSlide, 5000));
    }

    /* ===== Interactive Rating ===== */
    const ratingLabels = {
      1: 'Sangat Buruk',
      2: 'Buruk',
      3: 'Cukup',
      4: 'Bagus',
      5: 'Sangat Bagus'
    };

    document.querySelectorAll('.rating-form').forEach(function(form) {
      const bookId = form.dataset.book;
      const valueInput = document.getElementById('ratingValue-' + bookId);
      const help = document.getElementById('ratingHelp-' + bookId);
      const badge = document.getElementById('ratingBadge-' + bookId);
      const message = document.getElementById('ratingMessage-' + bookId);
      const buttons = form.querySelectorAll('.rating-star-btn');
      const submitBtn = form.querySelector('.rating-submit-btn');

      function paintRating(value, preview = false) {
        buttons.forEach(function(btn) {
          const n = Number(btn.dataset.value);
          btn.classList.toggle('selected', !preview && n <= value);
          btn.classList.toggle('preview', preview && n <= value);
          btn.setAttribute('aria-checked', n === value ? 'true' : 'false');
        });

        if (value > 0) {
          help.textContent = value + ' dari 5 — ' + ratingLabels[value];
          badge.textContent = value + '/5';
        } else {
          help.textContent = 'Klik bintang untuk memilih rating';
          badge.textContent = 'Belum dipilih';
        }
      }

      const initialValue = Number(valueInput.value || 0);
      paintRating(initialValue);

      buttons.forEach(function(btn) {
        btn.addEventListener('mouseenter', function() {
          paintRating(Number(btn.dataset.value), true);
        });
        btn.addEventListener('focus', function() {
          paintRating(Number(btn.dataset.value), true);
        });
        btn.addEventListener('click', function() {
          valueInput.value = btn.dataset.value;
          paintRating(Number(valueInput.value));
          message.textContent = '';
          message.className = 'rating-message';
        });
      });

      const starWrap = form.querySelector('.star-input-horizontal');
      starWrap.addEventListener('mouseleave', function() {
        paintRating(Number(valueInput.value || 0));
      });

      form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const nilai = Number(valueInput.value);
        if (!nilai || nilai < 1 || nilai > 5) {
          message.textContent = 'Silakan pilih rating 1 sampai 5 terlebih dahulu.';
          message.className = 'rating-message error';
          return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Menyimpan...';
        message.textContent = '';

        try {
          const response = await fetch('rating_submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(new FormData(form))
          });

          const data = await response.json();

          if (!response.ok || !data.success) {
            throw new Error(data.message || 'Rating gagal disimpan.');
          }

          message.textContent = data.message || 'Rating berhasil disimpan.';
          message.className = 'rating-message success';
          badge.textContent = nilai + '/5';
          paintRating(nilai);

          // Refresh agar rata-rata & jumlah rating langsung berubah.
          setTimeout(function() {
            window.location.reload();
          }, 850);
        } catch (err) {
          message.textContent = err.message || 'Terjadi kesalahan saat menyimpan rating.';
          message.className = 'rating-message error';
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Simpan Rating';
        }
      });
    });

    /* ===== Modal ===== */
    function bukaModal(id){
      document.getElementById(id).classList.add('show');
      document.body.style.overflow = 'hidden';
    }
    function tutupModal(id){
      document.getElementById(id).classList.remove('show');
      document.body.style.overflow = '';
    }
    document.querySelectorAll('.overlay').forEach(function(ov){
      ov.addEventListener('click', function(e){
        if (e.target === ov) { ov.classList.remove('show'); document.body.style.overflow = ''; }
      });
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') {
        document.querySelectorAll('.overlay.show').forEach(function(ov){ ov.classList.remove('show'); });
        document.body.style.overflow = '';
      }
    });

    /* ===== Filter & Search Realtime ===== */
    let activeCategory = 'semua';

    function filterKategori(cat) {
      activeCategory = cat.toLowerCase();

      document.querySelectorAll('.cat-pill').forEach(btn => {
        if (btn.textContent.trim().toLowerCase() === activeCategory || (activeCategory === 'semua' && btn.textContent.trim().toLowerCase().includes('semua'))) {
          btn.className = 'cat-pill shrink-0 bg-navy-900 text-white text-xs font-semibold px-4 py-2.5 rounded-full shadow-sm';
        } else {
          btn.className = 'cat-pill shrink-0 bg-white hover:bg-ocean-500 hover:text-white hover:border-ocean-500 text-slate-700 border border-slate-200 text-xs font-semibold px-4 py-2.5 rounded-full shadow-sm';
        }
      });

      applyFilters();
      document.getElementById('koleksi').scrollIntoView({ behavior: 'smooth' });
    }

    function triggerSearch() {
      applyFilters();
      document.getElementById('koleksi').scrollIntoView({ behavior: 'smooth' });
    }

    function applyFilters() {
      const input = document.getElementById('searchInput');
      const q = input ? input.value.trim().toLowerCase() : '';
      const countEl = document.getElementById('searchCount');
      const noResultEl = document.getElementById('noResult');

      const sections = Array.prototype.slice.call(document.querySelectorAll('[data-genre-section]'));
      let totalMatch = 0;

      sections.forEach(function(section){
        const sectionCards = section.querySelectorAll('[data-judul]');
        let matchInSection = 0;

        sectionCards.forEach(function(card){
          const judul = card.getAttribute('data-judul');
          const pengarang = card.getAttribute('data-pengarang');
          const kategoriCard = card.getAttribute('data-kategori').toLowerCase();

          const matchQuery = !q || judul.indexOf(q) !== -1 || pengarang.indexOf(q) !== -1;
          const matchCategory = (activeCategory === 'semua') || (kategoriCard === activeCategory);

          const shouldShow = matchQuery && matchCategory;
          card.classList.toggle('buku-hidden', !shouldShow);

          if (shouldShow) {
            matchInSection++;
            totalMatch++;
          }
        });

        section.classList.toggle('genre-hidden', matchInSection === 0);
        const countBadge = section.querySelector('[data-genre-count]');
        if (countBadge) countBadge.textContent = matchInSection + ' buku';
      });

      if (noResultEl) noResultEl.classList.toggle('hidden', totalMatch > 0);
      if (countEl) countEl.textContent = q ? ('Ditemukan ' + totalMatch + ' buku yang cocok') : '';
    }

    const searchInputEl = document.getElementById('searchInput');
    if (searchInputEl) {
      searchInputEl.addEventListener('input', applyFilters);
      searchInputEl.addEventListener('keypress', function(e){
        if(e.key === 'Enter') {
          triggerSearch();
        }
      });
    }
  </script>
</body>
</html>