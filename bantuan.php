<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bantuan — Perpustakaan Digital Ilmu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { heading: ['Poppins','sans-serif'], body: ['Inter','sans-serif'] },
          colors: {
            navy: { 950:'#071325', 900:'#0B1F3A', 800:'#132d53' },
            ocean: { 400:'#38BDF8', 500:'#0EA5E9', 600:'#0284C7' },
            royal: { 500:'#3B82F6', 600:'#2563EB' }
          }
        }
      }
    }
  </script>
  <style>
    body{font-family:Inter,sans-serif;background:#f8fafc;color:#1e293b}
    h1,h2,h3,.font-heading{font-family:Poppins,sans-serif}
    .ocean-bg{background:
      radial-gradient(circle at 0 0,rgba(14,165,233,.10),transparent 32%),
      radial-gradient(circle at 100% 20%,rgba(37,99,235,.08),transparent 28%),
      #f8fafc}
    .faq-answer{display:grid;grid-template-rows:0fr;transition:grid-template-rows .3s ease}
    .faq-answer>div{overflow:hidden}
    .faq.open .faq-answer{grid-template-rows:1fr}
    .faq-icon{transition:transform .3s ease}
    .faq.open .faq-icon{transform:rotate(180deg)}
    .card{box-shadow:0 10px 35px -18px rgba(11,31,58,.22)}
    @media(max-width:640px){
      .hero-title{font-size:2rem!important;line-height:1.12!important}
      .back-label{display:none}
    }
  </style>
</head>
<body class="ocean-bg min-h-screen">
  <header class="sticky top-0 z-50 bg-white/85 backdrop-blur-xl border-b border-sky-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 sm:h-20 flex items-center justify-between gap-4">
      <a href="index.php" class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-navy-900 to-ocean-500 flex items-center justify-center text-white font-bold shrink-0 shadow-lg">
          P
        </div>
        <div class="leading-tight">
          <div class="font-heading font-bold text-navy-900 text-sm sm:text-base">Perpustakaan Digital</div>
          <div class="text-[9px] sm:text-[10px] text-ocean-600 font-bold tracking-widest uppercase">Pusat Bantuan</div>
        </div>
      </a>
      <a href="index.php" class="inline-flex items-center gap-2 text-xs sm:text-sm font-bold text-navy-900 hover:text-ocean-600 transition">
        ← <span class="back-label">Kembali ke Beranda</span>
      </a>
    </div>
  </header>

  <main>
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 sm:pt-20 pb-10">
      <div class="max-w-3xl">
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-ocean-500/10 border border-ocean-500/20 text-ocean-600 text-xs font-bold">
          ❔ Pusat Bantuan
        </span>
        <h1 class="hero-title font-heading font-extrabold text-4xl sm:text-5xl text-navy-900 leading-tight mt-5">
          Butuh bantuan menggunakan perpustakaan?
        </h1>
        <p class="text-slate-600 text-sm sm:text-base leading-relaxed mt-4 max-w-2xl">
          Temukan panduan singkat untuk mencari buku, melihat detail koleksi, melakukan peminjaman,
          dan masuk ke dashboard anggota.
        </p>
      </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-12">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="#faq-cari" class="card bg-white border border-slate-200 rounded-2xl p-5 hover:-translate-y-1 hover:border-sky-300 transition">
          <div class="w-11 h-11 rounded-xl bg-sky-50 text-ocean-600 flex items-center justify-center text-xl mb-4">🔎</div>
          <h2 class="font-heading font-bold text-navy-900 text-sm">Cari Buku</h2>
          <p class="text-xs text-slate-500 mt-1 leading-relaxed">Gunakan pencarian berdasarkan judul atau pengarang.</p>
        </a>
        <a href="#faq-detail" class="card bg-white border border-slate-200 rounded-2xl p-5 hover:-translate-y-1 hover:border-sky-300 transition">
          <div class="w-11 h-11 rounded-xl bg-sky-50 text-ocean-600 flex items-center justify-center text-xl mb-4">📖</div>
          <h2 class="font-heading font-bold text-navy-900 text-sm">Detail Buku</h2>
          <p class="text-xs text-slate-500 mt-1 leading-relaxed">Lihat stok, sinopsis, rating, lokasi rak, dan ulasan.</p>
        </a>
        <a href="#faq-pinjam" class="card bg-white border border-slate-200 rounded-2xl p-5 hover:-translate-y-1 hover:border-sky-300 transition">
          <div class="w-11 h-11 rounded-xl bg-sky-50 text-ocean-600 flex items-center justify-center text-xl mb-4">📚</div>
          <h2 class="font-heading font-bold text-navy-900 text-sm">Peminjaman</h2>
          <p class="text-xs text-slate-500 mt-1 leading-relaxed">Pelajari cara memulai proses peminjaman buku.</p>
        </a>
        <a href="#faq-login" class="card bg-white border border-slate-200 rounded-2xl p-5 hover:-translate-y-1 hover:border-sky-300 transition">
          <div class="w-11 h-11 rounded-xl bg-sky-50 text-ocean-600 flex items-center justify-center text-xl mb-4">🔐</div>
          <h2 class="font-heading font-bold text-navy-900 text-sm">Login Anggota</h2>
          <p class="text-xs text-slate-500 mt-1 leading-relaxed">Masuk ke dashboard untuk mengelola aktivitasmu.</p>
        </a>
      </div>
    </section>

    <section class="max-w-4xl mx-auto px-4 sm:px-6 pb-20">
      <div class="bg-white border border-slate-200 rounded-3xl card overflow-hidden">
        <div class="p-6 sm:p-8 border-b border-slate-100">
          <span class="text-xs font-bold uppercase tracking-wider text-ocean-600">Pertanyaan Umum</span>
          <h2 class="font-heading font-bold text-2xl text-navy-900 mt-2">Cara menggunakan layanan</h2>
        </div>

        <div class="divide-y divide-slate-100">
          <div class="faq" id="faq-cari">
            <button class="w-full px-6 sm:px-8 py-5 flex items-center justify-between gap-5 text-left" onclick="toggleFaq(this)" aria-expanded="false">
              <span class="font-bold text-sm text-navy-900">Bagaimana cara mencari buku?</span>
              <span class="faq-icon text-ocean-600 text-lg shrink-0">⌄</span>
            </button>
            <div class="faq-answer"><div><p class="px-6 sm:px-8 pb-5 text-sm text-slate-600 leading-relaxed">Kembali ke Beranda, lalu gunakan kotak pencarian pada bagian hero. Ketik judul novel atau nama pengarang. Hasil akan diperbarui secara otomatis.</p></div></div>
          </div>

          <div class="faq" id="faq-detail">
            <button class="w-full px-6 sm:px-8 py-5 flex items-center justify-between gap-5 text-left" onclick="toggleFaq(this)" aria-expanded="false">
              <span class="font-bold text-sm text-navy-900">Bagaimana melihat informasi lengkap sebuah buku?</span>
              <span class="faq-icon text-ocean-600 text-lg shrink-0">⌄</span>
            </button>
            <div class="faq-answer"><div><p class="px-6 sm:px-8 pb-5 text-sm text-slate-600 leading-relaxed">Klik kartu buku yang ingin dilihat. Akan muncul detail seperti penulis, penerbit, tahun terbit, lokasi rak, stok, sinopsis, rating, dan ulasan pembaca jika tersedia.</p></div></div>
          </div>

          <div class="faq" id="faq-pinjam">
            <button class="w-full px-6 sm:px-8 py-5 flex items-center justify-between gap-5 text-left" onclick="toggleFaq(this)" aria-expanded="false">
              <span class="font-bold text-sm text-navy-900">Bagaimana cara meminjam buku?</span>
              <span class="faq-icon text-ocean-600 text-lg shrink-0">⌄</span>
            </button>
            <div class="faq-answer"><div><p class="px-6 sm:px-8 pb-5 text-sm text-slate-600 leading-relaxed">Pada detail buku, pilih tombol <b>Pinjam Buku</b>. Jika belum login, sistem akan mengarahkan ke halaman login anggota. Setelah berhasil login, lanjutkan proses sesuai instruksi pada dashboard siswa.</p></div></div>
          </div>

          <div class="faq" id="faq-login">
            <button class="w-full px-6 sm:px-8 py-5 flex items-center justify-between gap-5 text-left" onclick="toggleFaq(this)" aria-expanded="false">
              <span class="font-bold text-sm text-navy-900">Bagaimana jika belum memiliki akun anggota?</span>
              <span class="faq-icon text-ocean-600 text-lg shrink-0">⌄</span>
            </button>
            <div class="faq-answer"><div><p class="px-6 sm:px-8 pb-5 text-sm text-slate-600 leading-relaxed">Silakan hubungi petugas perpustakaan untuk proses pendaftaran atau aktivasi akun anggota sesuai prosedur sekolah.</p></div></div>
          </div>

          <div class="faq">
            <button class="w-full px-6 sm:px-8 py-5 flex items-center justify-between gap-5 text-left" onclick="toggleFaq(this)" aria-expanded="false">
              <span class="font-bold text-sm text-navy-900">Apa yang dilakukan jika buku tidak ditemukan?</span>
              <span class="faq-icon text-ocean-600 text-lg shrink-0">⌄</span>
            </button>
            <div class="faq-answer"><div><p class="px-6 sm:px-8 pb-5 text-sm text-slate-600 leading-relaxed">Coba kata kunci yang lebih pendek, gunakan nama pengarang, atau pilih kategori yang berbeda. Jika tetap tidak ditemukan, tanyakan kepada petugas perpustakaan.</p></div></div>
          </div>
        </div>
      </div>

      <div class="mt-6 rounded-3xl bg-gradient-to-br from-navy-900 to-navy-800 text-white p-6 sm:p-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
        <div>
          <p class="text-ocean-400 text-xs font-bold uppercase tracking-wider">Masih membutuhkan bantuan?</p>
          <h3 class="font-heading font-bold text-lg mt-1">Hubungi petugas perpustakaan</h3>
          <p class="text-slate-300 text-xs mt-2">Petugas dapat membantu masalah akun, peminjaman, dan informasi koleksi.</p>
        </div>
        <a href="index.php#kontak" class="shrink-0 bg-white text-navy-900 hover:bg-sky-50 font-bold text-xs px-5 py-3 rounded-xl transition">Lihat Kontak</a>
      </div>
    </section>
  </main>

  <footer class="bg-navy-950 text-slate-400 text-xs text-center px-4 py-6 border-t border-sky-900/30">
    © 2026 Perpustakaan Digital Sekolah · Pusat Bantuan
  </footer>

  <script>
    function toggleFaq(button){
      const faq = button.closest('.faq');
      const isOpen = faq.classList.contains('open');
      document.querySelectorAll('.faq.open').forEach(el => {
        el.classList.remove('open');
        el.querySelector('button')?.setAttribute('aria-expanded','false');
      });
      if(!isOpen){
        faq.classList.add('open');
        button.setAttribute('aria-expanded','true');
      }
    }
  </script>
</body>
</html>