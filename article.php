<?php
// 1. Mulai sesi & panggil koneksi database
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// 2. Ambil data Website Settings untuk Footer / Kontak (jika ada)
$settings_res = mysqli_query($conn, "SELECT * FROM website_settings WHERE id = 1");
$settings = $settings_res ? mysqli_fetch_assoc($settings_res) : [];

// 3. Ambil data ARTIKEL dari database secara dinamis
// Menggunakan penanganan error agar jika tabel 'articles' belum ada, halaman tidak crash.
$articles = [];
$query_articles = "SELECT * FROM articles ORDER BY id DESC";
$result_articles = mysqli_query($conn, $query_articles);

if ($result_articles) {
    while ($row = mysqli_fetch_assoc($result_articles)) {
        $articles[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Artikel & Wawasan Digital Marketing - Marketiva</title>
  
  <!-- Google Fonts & Material Symbols Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <!-- Internal CSS (Playful Modern Studio Theme) -->
  <style>
    :root {
      --canvas-warm: #FAF9F6;
      --primary-blue: #2563EB;
      --secondary-crimson: #E11D48;
      --brand-pink: #F43F5E;
      --bubblegum-pink: #EC4899;
      --fresh-green: #10B981;
      --amber-sun: #FBBF24;
      --on-surface: #131b2e;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: var(--canvas-warm);
      color: var(--on-surface);
    }

    ::-webkit-scrollbar {
      display: none;
    }

    .ambient-glow-pink {
      position: absolute;
      width: 500px;
      height: 400px;
      background: radial-gradient(circle, rgba(244, 63, 94, 0.15) 0%, rgba(253, 242, 248, 0) 70%);
      filter: blur(60px);
      pointer-events: none;
    }

    .ambient-glow-blue {
      position: absolute;
      width: 550px;
      height: 450px;
      background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(239, 246, 255, 0) 70%);
      filter: blur(60px);
      pointer-events: none;
    }

    .btn-primary-hero {
      background-color: var(--secondary-crimson);
      color: #FFFFFF;
      font-weight: 700;
      padding: 0.875rem 1.75rem;
      border-radius: 1rem;
      box-shadow: 0 8px 20px -4px rgba(225, 29, 72, 0.3);
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
    }

    .btn-primary-hero:hover {
      background-color: #BE123C;
      transform: translateY(-2px);
    }

    .btn-outline-playful {
      background-color: #FFFFFF;
      border: 2px solid #E2E8F0;
      color: var(--on-surface);
      font-weight: 700;
      padding: 0.875rem 1.75rem;
      border-radius: 1rem;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .btn-outline-playful:hover {
      border-color: #3B82F6;
      color: var(--primary-blue);
    }

    .badge-creative {
      background-color: #FDF2F8;
      color: #BE185D;
      border: 1px solid #FBCFE8;
      border-radius: 9999px;
      padding: 0.35rem 0.85rem;
      font-size: 0.75rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
    }

    .card-studio {
      background-color: #FFFFFF;
      border-radius: 1.5rem;
      border: 1.5px solid #F1F5F9;
      padding: 1.5rem;
      box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.04);
      transition: all 0.3s ease;
    }

    .card-studio:hover {
      transform: translateY(-4px);
      box-shadow: 0 16px 32px -8px rgba(37, 99, 235, 0.15);
    }

    .card-img-top {
      height: 200px;
      width: 100%;
      object-fit: cover;
      border-radius: 1rem;
      margin-bottom: 1rem;
    }

    .wa-float-pod {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 60px;
      height: 60px;
      border-radius: 9999px;
      background-color: var(--fresh-green);
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 20px 30px -6px rgba(16, 185, 129, 0.3);
      z-index: 100;
      text-decoration: none;
      transition: transform 0.2s ease;
    }

    .wa-float-pod:hover {
      transform: scale(1.08);
    }
  </style>
</head>
<body class="antialiased relative min-h-screen">

  <!-- HEADER / NAVBAR -->
  <header class="fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200/60 shadow-[0_1px_8px_rgba(0,0,0,0.04)]">
    <div class="h-20 max-w-[1240px] mx-auto px-6 flex items-center justify-between gap-4">
      
      <!-- LOGO MARKETIVA -->
      <a class="flex items-center gap-2.5" href="index.php#beranda">
        <span class="font-extrabold text-xl tracking-tight">
          <span class="text-[#F43F5E]">Mar</span><span class="text-[#3B82F6]">ke</span><span class="text-[#10B981]">ti</span><span class="text-[#F59E0B]">va</span>
        </span>
      </a>

      <!-- MENU NAVBAR -->
      <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-700">
        <a class="hover:text-[#E11D48] transition-colors flex items-center gap-1.5" href="index.php#beranda">
          <span class="material-symbols-outlined text-[18px]">home</span>
          <span>Home</span>
        </a>
        <a class="hover:text-[#E11D48] transition-colors flex items-center gap-1.5" href="about.php">
          <span class="material-symbols-outlined text-[18px]">info</span>
          <span>Tentang Kami</span>
        </a>
        <a class="hover:text-[#E11D48] transition-colors flex items-center gap-1.5" href="index.php#keunggulan">
          <span class="material-symbols-outlined text-[18px]">verified</span>
          <span>Keunggulan</span>
        </a>
        <a class="hover:text-[#E11D48] transition-colors flex items-center gap-1.5" href="catalog.php">
          <span class="material-symbols-outlined text-[18px]">grid_view</span>
          <span>Catalog Layanan</span>
        </a>
        <a class="text-[#E11D48] transition-colors flex items-center gap-1.5 font-bold" href="article.php">
          <span class="material-symbols-outlined text-[18px]">article</span>
          <span>Artikel</span>
        </a>
      </nav>

      <!-- DINAMIS: TOMBOL MASUK / AKUN -->
      <div class="flex items-center gap-3">
        <?php if (isset($_SESSION['user'])): ?>
          <?php $dashboard_url = ($_SESSION['role'] === 'admin') ? 'admin-dashboard.php' : 'user-dashboard.php'; ?>
          <a href="<?= $dashboard_url ?>" class="btn-primary-hero text-xs py-2 px-4 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[18px]">dashboard</span>
            <span>Halo, <?= htmlspecialchars($_SESSION['user']); ?></span>
          </a>
        <?php else: ?>
          <a href="login.php" class="btn-outline-playful text-xs py-2 px-4 flex items-center gap-1.5 border-slate-200 hover:border-rose-300">
            <span class="material-symbols-outlined text-[18px] text-[#E11D48]">account_circle</span>
            <span class="hidden sm:inline">Masuk</span>
          </a>
          <a href="login.php" class="btn-primary-hero text-xs py-2 px-4 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            <span>Daftar</span>
          </a>
        <?php endif; ?>
      </div>

    </div>
  </header>

  <main class="pt-20">
    
    <!-- HERO SECTION ARTIKEL -->
    <section class="relative overflow-hidden py-16 lg:py-20">
      <div class="ambient-glow-pink -top-20 left-10"></div>
      <div class="ambient-glow-blue top-10 right-10"></div>
      
      <div class="max-w-[1240px] mx-auto px-6 relative z-10 text-center space-y-4">
        <span class="badge-creative inline-block">Wawasan & Panduan</span>
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight text-slate-900 max-w-3xl mx-auto leading-tight">
          Tips Konten Web & Strategi Digital Marketing
        </h1>
        <p class="text-slate-600 text-sm md:text-base max-w-2xl mx-auto leading-relaxed">
          Temukan berbagai artikel mendalam, kiat optimasi media sosial, dan rahasia meningkatkan konversi bisnis Anda bersama Marketiva.
        </p>
      </div>
    </section>

    <!-- GRID ARTIKEL DINAMIS DARI DATABASE -->
    <section class="pb-24">
      <div class="max-w-[1240px] mx-auto px-6">
        
        <?php if (!empty($articles)): ?>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($articles as $art): ?>
              <div class="card-studio flex flex-col justify-between">
                <div>
                  <?php if (!empty($art['image'])): ?>
                    <?php
                      $img_raw = trim($art['image']);
                      if (
                          strpos($img_raw, 'http://') === 0 ||
                          strpos($img_raw, 'https://') === 0 ||
                          strpos($img_raw, 'uploads/') === 0 ||
                          strpos($img_raw, 'assets/') === 0 ||
                          strpos($img_raw, '/') === 0
                      ) {
                          $img_final = $img_raw;
                      } else {
                          $img_final = 'assets/img/' . $img_raw;
                      }
                    ?>
                    <img src="<?= htmlspecialchars($img_final); ?>" 
                         class="card-img-top" 
                         alt="<?= htmlspecialchars($art['title'] ?? 'Artikel Marketiva'); ?>">
                  <?php endif; ?>

                  <div class="flex items-center justify-between mb-3">
                    <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-600 border border-rose-200 text-xs font-bold">
                      <?= htmlspecialchars($art['category'] ?? 'Digital Marketing'); ?>
                    </span>
                    <small class="text-slate-400 text-xs"><?= htmlspecialchars($art['date'] ?? ''); ?></small>
                  </div>

                  <h3 class="font-extrabold text-lg text-slate-900 mb-2 leading-snug">
                    <?= htmlspecialchars($art['title'] ?? ''); ?>
                  </h3>
                  
                  <p class="text-slate-600 text-xs leading-relaxed mb-6">
                    <?= htmlspecialchars($art['excerpt'] ?? ''); ?>
                  </p>
                </div>

                <?php
                  $raw_link = trim($art['slug'] ?? $art['url'] ?? $art['link'] ?? '#');
                  if (strpos($raw_link, 'http://') === 0 || strpos($raw_link, 'https://') === 0) {
                      $link_artikel = $raw_link;
                  } elseif (!empty($raw_link) && $raw_link !== '#') {
                      $link_artikel = 'https://' . ltrim($raw_link, '/');
                  } else {
                      $link_artikel = '#';
                  }
                ?>
                <a href="<?= htmlspecialchars($link_artikel); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 font-bold text-xs text-rose-600 hover:text-rose-700 transition-colors">
                  <span>Baca Selengkapnya</span>
                  <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <!-- Tampilan Jika Tabel Masih Kosong atau Belum Dibuat -->
          <div class="max-w-md mx-auto p-8 text-center bg-white rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mx-auto">
              <span class="material-symbols-outlined text-[24px]">info</span>
            </div>
            <h3 class="font-bold text-slate-900 text-base">Belum Ada Artikel Tersedia</h3>
            <p class="text-slate-500 text-xs leading-relaxed">
              Saat ini tabel <b>articles</b> di database Anda masih kosong atau belum terisi. Silakan tambahkan data artikel melalui database MySQL Anda.
            </p>
          </div>
        <?php endif; ?>

      </div>
    </section>

  </main>

  <!-- FLOATING WHATSAPP -->
  <a href="https://wa.me/6281234567890" target="_blank" class="wa-float-pod" title="Konsultasi Langsung via WhatsApp">
    <span class="material-symbols-outlined text-[32px]">chat</span>
  </a>

  <!-- FOOTER -->
  <footer class="bg-slate-50/80 border-t border-slate-200/80 pt-16 pb-12 text-slate-600 text-xs">
    <div class="max-w-[1240px] mx-auto px-6">
      
      <div class="grid grid-cols-1 md:grid-cols-12 gap-10 pb-12 border-b border-slate-200">
        <div class="md:col-span-5 space-y-4">
          <a class="flex items-center gap-2.5" href="index.php#beranda">
            <span class="font-extrabold text-xl tracking-tight">
              <span class="text-[#F43F5E]">Mar</span><span class="text-[#3B82F6]">ke</span><span class="text-[#10B981]">ti</span><span class="text-[#F59E0B]">va</span>
            </span>
          </a>
          <p class="font-bold text-slate-800 text-sm">
            Partner Digital Marketing untuk Pertumbuhan UMKM.
          </p>
          <p class="text-slate-500 leading-relaxed max-w-sm">
            <?= htmlspecialchars($settings['deskripsi_footer'] ?? 'Membantu bisnis lokal dan pelaku UMKM Indonesia.'); ?>
          </p>
        </div>

        <div class="md:col-span-3 space-y-3">
          <h4 class="font-extrabold text-slate-900 text-sm">Tautan Cepat</h4>
          <ul class="space-y-2 text-slate-600">
            <li><a href="index.php#beranda" class="hover:text-rose-600 transition-colors">Beranda</a></li>
            <li><a href="about.php" class="hover:text-rose-600 transition-colors">Tentang Kami</a></li>
            <li><a href="catalog.php" class="hover:text-rose-600 transition-colors">Layanan</a></li>
            <li><a href="article.php" class="hover:text-rose-600 transition-colors">Artikel</a></li>
          </ul>
        </div>

        <div class="md:col-span-4 space-y-4">
          <h4 class="font-extrabold text-slate-900 text-sm">Hubungi Kami</h4>
          <ul class="space-y-2.5 text-slate-600">
            <li class="flex items-center gap-2.5">
              <span class="material-symbols-outlined text-[18px] text-rose-500">mail</span>
              <span><?= htmlspecialchars($settings['email'] ?? 'halo@marketiva.co.id'); ?></span>
            </li>
            <li class="flex items-center gap-2.5">
              <span class="material-symbols-outlined text-[18px] text-sky-500">call</span>
              <span><?= htmlspecialchars($settings['telepon'] ?? '+62 812-3456-7890'); ?></span>
            </li>
          </ul>
        </div>
      </div>

      <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-slate-400">
        <p><?= htmlspecialchars($settings['copyright_text'] ?? '© 2026 Marketiva. All Rights Reserved.'); ?></p>
        <div class="flex items-center gap-6">
          <a href="#" class="hover:text-slate-600 transition-colors">Kebijakan Privasi</a>
          <a href="#" class="hover:text-slate-600 transition-colors">Syarat & Ketentuan</a>
        </div>
      </div>

    </div>
  </footer>

</body>
</html>