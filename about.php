<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

function ensureAboutSchema($conn) {
    if (!$conn) return;
    $columns = [
        'tab_umkm_label'    => "VARCHAR(120) NOT NULL DEFAULT 'Pelaku UMKM & Brand'",
        'tab_umkm_desc'     => "TEXT NULL",
        'tab_agency_label'  => "VARCHAR(120) NOT NULL DEFAULT 'Tim & Sistem Marketiva'",
        'tab_agency_desc'   => "TEXT NULL",
        'three_words_quote' => "VARCHAR(255) NOT NULL DEFAULT 'KREATIF, TERUKUR, DAN TERPERCAYA — 3 KATA YANG PALING TEPAT MENGGAMBARKAN MARKETIVA'",
        'smm_title'         => "VARCHAR(180) NOT NULL DEFAULT 'Pengelolaan Akun Instagram & TikTok Terjadwal'",
        'smm_desc'          => "TEXT NULL",
        'content_title'     => "VARCHAR(180) NOT NULL DEFAULT 'Desain Visual Kreatif, Carousel & Video Pendek Reels'",
        'content_desc'      => "TEXT NULL",
        'ads_title'         => "VARCHAR(180) NOT NULL DEFAULT 'Iklan Berbayar Meta Ads & TikTok Ads Tertarget'",
        'ads_desc'          => "TEXT NULL",
    ];
    foreach ($columns as $col => $definition) {
        $safeCol = mysqli_real_escape_string($conn, $col);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `about_us` LIKE '{$safeCol}'");
        if ($res && mysqli_num_rows($res) === 0) {
            @mysqli_query($conn, "ALTER TABLE `about_us` ADD COLUMN `{$col}` {$definition}");
        }
    }
}

ensureAboutSchema($conn);

// Ambil data tentang kami dari database (ID = 1)
$query  = "SELECT * FROM `about_us` WHERE id = 1 LIMIT 1";
$result = mysqli_query($conn, $query);
$about  = $result ? (mysqli_fetch_assoc($result) ?: []) : [];

// Cek status login pengguna & data profil header
$is_logged_in   = isset($_SESSION['user']);
$user_name      = trim((string)($_SESSION['user'] ?? ''));
$user_role      = $_SESSION['role'] ?? 'user';
$user_email_hdr = $_SESSION['email'] ?? '';
$is_admin_hdr   = ($user_role === 'admin');
$dashboard_link = $is_admin_hdr ? 'admin-dashboard.php' : 'user-dashboard.php';
$role_label_hdr = $is_admin_hdr ? 'Administrator' : 'Member UMKM';
$avatar_char    = ($is_logged_in && $user_name !== '') ? strtoupper(mb_substr($user_name, 0, 1)) : 'M';

// Sinkronisasi seluruh field dari tabel about_us
$judul_about      = trim($about['judul'] ?? '') ?: 'Tentang Marketiva Digital Agency';
$deskripsi_about  = trim($about['deskripsi'] ?? '') ?: 'Marketiva mendampingi pelaku UMKM dan brand lokal bertumbuh lebih kuat melalui tiga layanan utama digital marketing: Social Media Management, Content Creation & Design, serta Digital Advertising (Ads) yang terukur.';
$visi_about       = trim($about['visi'] ?? '') ?: 'Menjadi mitra agensi digital marketing terdepan dan terpercaya bagi pertumbuhan bisnis UMKM di Indonesia.';
$misi_about       = trim($about['misi'] ?? '') ?: "1. Mengelola akun media sosial UMKM secara konsisten, aktif, dan profesional.\n2. Memproduksi konten visual kreatif, desain, dan video Reels yang relevan dengan pasar.\n3. Menjalankan kampanye Digital Ads yang terukur untuk memperluas jangkauan dan meningkatkan omset.";

$tab_umkm_label   = trim($about['tab_umkm_label'] ?? '') ?: 'Pelaku UMKM & Brand';
$tab_umkm_desc    = trim($about['tab_umkm_desc'] ?? '') ?: 'Dapatkan pendampingan terfokus pada tiga layanan utama Marketiva: Social Media Management (Instagram & TikTok), Content Creation & Design (foto produk, desain feed, & video Reels), serta Digital Ads (Meta & TikTok Ads) untuk menjangkau lebih banyak pembeli.';
$tab_agency_label = trim($about['tab_agency_label'] ?? '') ?: 'Tim & Sistem Marketiva';
$tab_agency_desc  = trim($about['tab_agency_desc'] ?? '') ?: 'Tim kreatif dan strategist kami memadukan riset audiens, desain visual modern, copywriting persuasif, serta pelaporan performa iklan dan media sosial secara berkala sesuai paket layanan yang Anda pilih.';

$three_words_quote = trim($about['three_words_quote'] ?? '') ?: 'KREATIF, TERUKUR, DAN TERPERCAYA — 3 KATA YANG PALING TEPAT MENGGAMBARKAN MARKETIVA';

$smm_title     = trim($about['smm_title'] ?? '') ?: 'Pengelolaan Akun Instagram & TikTok Terjadwal';
$smm_desc      = trim($about['smm_desc'] ?? '') ?: 'Perencanaan kalender konten, penjadwalan posting rutin, optimasi bio & highlight, serta interaksi audiens agar akun brand UMKM selalu aktif dan terlihat profesional.';
$content_title = trim($about['content_title'] ?? '') ?: 'Desain Visual Kreatif, Carousel & Video Pendek Reels';
$content_desc  = trim($about['content_desc'] ?? '') ?: 'Produksi desain grafis feed & story, pengeditan foto produk, konsep video pendek Reels/TikTok, serta copywriting persuasif yang selaras dengan karakter brand Anda.';
$ads_title     = trim($about['ads_title'] ?? '') ?: 'Iklan Berbayar Meta Ads & TikTok Ads Tertarget';
$ads_desc      = trim($about['ads_desc'] ?? '') ?: 'Riset target audiens, pengaturan materi iklan, pemantauan performa harian, dan laporan evaluasi untuk menjangkau pembeli potensial secara cepat dan terukur.';

$pillar_payload = [
    'smm' => [
        'badge' => 'Terpercaya • Social Media Management',
        'badgeClass' => 'inline-block px-3 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[10px] font-extrabold uppercase',
        'title' => $smm_title,
        'desc'  => $smm_desc,
    ],
    'content' => [
        'badge' => 'Kreatif • Content Creation & Design',
        'badgeClass' => 'inline-block px-3 py-0.5 rounded-full bg-rose-100 text-[#E11D48] text-[10px] font-extrabold uppercase',
        'title' => $content_title,
        'desc'  => $content_desc,
    ],
    'ads' => [
        'badge' => 'Terukur • Digital Ads',
        'badgeClass' => 'inline-block px-3 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-extrabold uppercase',
        'title' => $ads_title,
        'desc'  => $ads_desc,
    ],
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($judul_about); ?> - Marketiva</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
  <style>
    :root {
      --canvas-warm: #FAF9F6;
      --primary-blue: #2563EB;
      --secondary-crimson: #E11D48;
      --brand-pink: #F43F5E;
      --fresh-green: #10B981;
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

    .card-studio {
      background-color: #FFFFFF;
      border-radius: 1.5rem;
      border: 1.5px solid #F1F5F9;
      box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.04);
      transition: all 0.25s ease;
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

    /* Modern Profile Pill & Dropdown */
    .profile-pill-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.35rem 0.75rem 0.35rem 0.4rem;
      border-radius: 9999px;
      background: #FFFFFF;
      border: 1.5px solid #E2E8F0;
      box-shadow: 0 4px 14px -4px rgba(15, 23, 42, 0.08);
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .profile-pill-btn:hover,
    .profile-pill-btn[aria-expanded="true"] {
      border-color: #FDA4AF;
      background: #FFF1F2;
      box-shadow: 0 8px 20px -6px rgba(225, 29, 72, 0.18);
    }

    .profile-avatar-ring {
      width: 36px;
      height: 36px;
      border-radius: 9999px;
      background: linear-gradient(135deg, #E11D48 0%, #EC4899 55%, #2563EB 100%);
      color: #FFFFFF;
      font-weight: 800;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      flex-shrink: 0;
    }

    .profile-online-dot {
      position: absolute;
      bottom: -1px;
      right: -1px;
      width: 10px;
      height: 10px;
      border-radius: 9999px;
      background: #10B981;
      border: 2px solid #FFFFFF;
    }

    .profile-dropdown-menu {
      position: absolute;
      right: 0;
      top: calc(100% + 0.65rem);
      width: 268px;
      background: #FFFFFF;
      border: 1.5px solid #E2E8F0;
      border-radius: 1.25rem;
      box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.16);
      padding: 0.5rem;
      z-index: 80;
      transform-origin: top right;
      transition: opacity 0.18s ease, transform 0.18s ease;
    }

    .profile-dropdown-menu.is-closed {
      opacity: 0;
      transform: translateY(-6px) scale(0.97);
      pointer-events: none;
    }

    .profile-dropdown-menu.is-open {
      opacity: 1;
      transform: translateY(0) scale(1);
      pointer-events: auto;
    }

    .profile-menu-item {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      width: 100%;
      padding: 0.65rem 0.75rem;
      border-radius: 0.85rem;
      font-size: 0.78rem;
      font-weight: 700;
      color: #334155;
      text-decoration: none;
      transition: all 0.15s ease;
    }

    .profile-menu-item:hover {
      background: #F8FAFC;
      color: #0F172A;
    }

    .profile-menu-item.danger:hover {
      background: #FFF1F2;
      color: #E11D48;
    }

    .role-tab-btn.is-active {
      background-color: #FFF1F2;
      color: #E11D48;
      border-bottom: 3px solid #E11D48;
    }

    .pillar-tab-card.is-active {
      border-color: #E11D48;
      background-color: #FFF1F2;
      transform: translateY(-3px);
      box-shadow: 0 12px 24px -8px rgba(225, 29, 72, 0.16);
    }
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between antialiased">

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

        <a class="text-[#E11D48] font-bold transition-colors flex items-center gap-1.5" href="about.php">
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

        <a class="hover:text-[#E11D48] transition-colors flex items-center gap-1.5" href="article.php">
          <span class="material-symbols-outlined text-[18px]">grid_view</span>
          <span>Article</span>
        </a>
      </nav>

      <!-- PROFIL INTERAKTIF KANAN ATAS (TANPA KATA HALO) -->
      <div class="flex items-center gap-3">
        <?php if ($is_logged_in): ?>
          <div class="relative" id="profileDropdownWrapper">
            <button type="button"
                    id="profileTriggerBtn"
                    aria-expanded="false"
                    aria-controls="profileDropdownPanel"
                    onclick="toggleProfileDropdown(event)"
                    class="profile-pill-btn">
              <div class="profile-avatar-ring">
                <span><?= htmlspecialchars($avatar_char); ?></span>
                <span class="profile-online-dot"></span>
              </div>
              <div class="hidden sm:flex flex-col items-start text-left leading-tight pr-1">
                <span class="text-xs font-extrabold text-slate-900 max-w-[140px] truncate">
                  <?= htmlspecialchars($user_name); ?>
                </span>
                <span class="text-[10px] font-bold <?= $is_admin_hdr ? 'text-rose-600' : 'text-blue-600'; ?>">
                  <?= htmlspecialchars($role_label_hdr); ?>
                </span>
              </div>
              <span id="profileChevronIcon" class="material-symbols-outlined text-[18px] text-slate-500 transition-transform duration-200">
                expand_more
              </span>
            </button>

            <div id="profileDropdownPanel" class="profile-dropdown-menu is-closed" role="menu">
              <div class="px-3 py-2.5 rounded-2xl bg-slate-50 border border-slate-100 mb-1.5">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-extrabold text-slate-900 truncate"><?= htmlspecialchars($user_name); ?></span>
                  <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase <?= $is_admin_hdr ? 'bg-rose-100 text-rose-700' : 'bg-blue-100 text-blue-700'; ?>">
                    <?= $is_admin_hdr ? 'PRO ADMIN' : 'UMKM'; ?>
                  </span>
                </div>
                <?php if (!empty($user_email_hdr)): ?>
                  <p class="text-[11px] text-slate-500 truncate mt-0.5"><?= htmlspecialchars($user_email_hdr); ?></p>
                <?php endif; ?>
              </div>

              <a href="<?= htmlspecialchars($dashboard_link); ?>" class="profile-menu-item" role="menuitem">
                <span class="material-symbols-outlined text-[18px] text-rose-600">space_dashboard</span>
                <span><?= $is_admin_hdr ? 'Admin Control Panel' : 'Dashboard Akun'; ?></span>
              </a>

              <a href="catalog.php" class="profile-menu-item" role="menuitem">
                <span class="material-symbols-outlined text-[18px] text-blue-600">storefront</span>
                <span>Jelajah Katalog Layanan</span>
              </a>

              <a href="<?= htmlspecialchars($dashboard_link); ?>?tab=profile" class="profile-menu-item" role="menuitem">
                <span class="material-symbols-outlined text-[18px] text-emerald-600">manage_accounts</span>
                <span>Pengaturan Profil</span>
              </a>

              <div class="my-1 border-t border-slate-100"></div>

              <a href="login.php?action=logout" class="profile-menu-item danger" role="menuitem">
                <span class="material-symbols-outlined text-[18px] text-rose-500">logout</span>
                <span>Keluar dari Akun</span>
              </a>
            </div>
          </div>
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

  <!-- KONTEN UTAMA TENTANG KAMI (EDITORIAL + INTERAKTIF + TERSINKRON DATABASE) -->
  <main class="pt-28 pb-20 max-w-[1140px] mx-auto px-6 w-full flex-1 space-y-12">

    <!-- 1. HERO STATEMENT & DESKRIPSI UTAMA -->
    <section class="card-studio p-8 md:p-12 text-center space-y-5 relative overflow-hidden">
      <span class="inline-block px-4 py-1.5 rounded-full bg-rose-50 text-[#E11D48] border border-rose-200 text-xs font-extrabold uppercase tracking-wider">
        Tentang Marketiva
      </span>

      <h1 class="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-snug max-w-4xl mx-auto">
        “<?= htmlspecialchars($judul_about); ?>”
      </h1>

      <div class="w-14 h-1 bg-[#E11D48] mx-auto rounded-full"></div>

      <p class="text-slate-600 text-sm sm:text-base max-w-3xl mx-auto leading-relaxed whitespace-pre-line">
        <?= htmlspecialchars($deskripsi_about); ?>
      </p>

      <!-- INTERACTIVE AUDIENCE SWITCHER (TERHUBUNG KE DB about_us) -->
      <div class="pt-6 border-t border-slate-100 max-w-3xl mx-auto">
        <div class="grid grid-cols-2 rounded-2xl border border-slate-200 bg-slate-50/80 overflow-hidden">
          <button type="button"
                  id="tabBtnUmkm"
                  onclick="switchAudienceTab('umkm')"
                  class="role-tab-btn is-active py-3.5 px-4 text-xs sm:text-sm font-extrabold uppercase tracking-wide transition-all">
            <?= htmlspecialchars($tab_umkm_label); ?>
          </button>
          <button type="button"
                  id="tabBtnAgency"
                  onclick="switchAudienceTab('agency')"
                  class="role-tab-btn py-3.5 px-4 text-xs sm:text-sm font-extrabold uppercase tracking-wide text-slate-600 hover:text-slate-900 transition-all">
            <?= htmlspecialchars($tab_agency_label); ?>
          </button>
        </div>

        <div id="audiencePanelUmkm" class="mt-4 p-5 rounded-2xl bg-rose-50/40 border border-rose-100 text-left space-y-2">
          <p class="text-xs font-extrabold text-[#E11D48] uppercase"><?= htmlspecialchars($tab_umkm_label); ?></p>
          <p class="text-xs sm:text-sm text-slate-700 leading-relaxed whitespace-pre-line">
            <?= htmlspecialchars($tab_umkm_desc); ?>
          </p>
        </div>

        <div id="audiencePanelAgency" class="hidden mt-4 p-5 rounded-2xl bg-blue-50/50 border border-blue-100 text-left space-y-2">
          <p class="text-xs font-extrabold text-blue-700 uppercase"><?= htmlspecialchars($tab_agency_label); ?></p>
          <p class="text-xs sm:text-sm text-slate-700 leading-relaxed whitespace-pre-line">
            <?= htmlspecialchars($tab_agency_desc); ?>
          </p>
        </div>
      </div>
    </section>

    <!-- 2. 3 KATA UTAMA & 3 LAYANAN INTERAKTIF MARKETIVA (TERHUBUNG KE DB about_us) -->
    <section class="card-studio p-8 md:p-10 space-y-8">
      <div class="text-center space-y-2 max-w-2xl mx-auto">
        <p class="text-xs sm:text-sm font-extrabold text-slate-800 uppercase tracking-wide">
          “<?= htmlspecialchars($three_words_quote); ?>”
        </p>
        <div class="w-12 h-1 bg-[#E11D48] mx-auto rounded-full"></div>
        <p class="text-xs text-slate-500">Klik salah satu dari 3 layanan utama Marketiva di bawah untuk melihat rinciannya.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <button type="button"
                id="pillarCardSmm"
                onclick="selectPillar('smm')"
                class="pillar-tab-card is-active p-5 rounded-2xl border-2 border-slate-200 bg-white text-center space-y-2 transition-all cursor-pointer">
          <span class="material-symbols-outlined text-[36px] text-sky-600">share</span>
          <p class="text-sm font-extrabold uppercase text-slate-900">Social Media Management</p>
          <p class="text-[11px] font-bold text-sky-700">Terpercaya & Konsisten</p>
        </button>

        <button type="button"
                id="pillarCardContent"
                onclick="selectPillar('content')"
                class="pillar-tab-card p-5 rounded-2xl border-2 border-slate-200 bg-white text-center space-y-2 transition-all cursor-pointer">
          <span class="material-symbols-outlined text-[36px] text-[#E11D48]">palette</span>
          <p class="text-sm font-extrabold uppercase text-slate-900">Content Creation & Design</p>
          <p class="text-[11px] font-bold text-[#E11D48]">Kreatif & Visual Menarik</p>
        </button>

        <button type="button"
                id="pillarCardAds"
                onclick="selectPillar('ads')"
                class="pillar-tab-card p-5 rounded-2xl border-2 border-slate-200 bg-white text-center space-y-2 transition-all cursor-pointer">
          <span class="material-symbols-outlined text-[36px] text-amber-600">ads_click</span>
          <p class="text-sm font-extrabold uppercase text-slate-900">Digital Ads</p>
          <p class="text-[11px] font-bold text-amber-700">Terukur & Tertarget</p>
        </button>
      </div>

      <!-- Detail Dinamis Layanan Terpilih -->
      <div id="pillarDetailBox" class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1.5 max-w-2xl">
          <span id="pillarBadge" class="inline-block px-3 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[10px] font-extrabold uppercase">
            Terpercaya • Social Media Management
          </span>
          <h3 id="pillarTitle" class="text-base font-extrabold text-slate-900">
            <?= htmlspecialchars($smm_title); ?>
          </h3>
          <p id="pillarDesc" class="text-xs sm:text-sm text-slate-600 leading-relaxed whitespace-pre-line">
            <?= htmlspecialchars($smm_desc); ?>
          </p>
        </div>
        <a href="catalog.php" class="btn-primary-hero text-xs py-3 px-5 whitespace-nowrap self-start md:self-center">
          <span>Lihat Paket di Katalog</span>
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </a>
      </div>
    </section>

    <!-- 3. VISI & MISI DARI DATABASE -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="card-studio p-7 space-y-3 border-t-4 border-t-indigo-500">
        <div class="flex items-center gap-2.5">
          <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
            <span class="material-symbols-outlined text-[20px]">visibility</span>
          </div>
          <h2 class="text-sm font-extrabold text-indigo-700 uppercase tracking-wider">Visi Kami</h2>
        </div>
        <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">
          <?= htmlspecialchars($visi_about); ?>
        </p>
      </div>

      <div class="card-studio p-7 space-y-3 border-t-4 border-t-rose-500">
        <div class="flex items-center gap-2.5">
          <div class="w-10 h-10 rounded-xl bg-rose-50 text-[#E11D48] flex items-center justify-center">
            <span class="material-symbols-outlined text-[20px]">flag</span>
          </div>
          <h2 class="text-sm font-extrabold text-[#E11D48] uppercase tracking-wider">Misi Kami</h2>
        </div>
        <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">
          <?= htmlspecialchars($misi_about); ?>
        </p>
      </div>
    </section>

  </main>

  <!-- FOOTER -->
  <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400">
    © 2026 Marketiva Digital Agency. All rights reserved.
  </footer>

  <script>
    function toggleProfileDropdown(event) {
      event.stopPropagation();
      const btn = document.getElementById('profileTriggerBtn');
      const panel = document.getElementById('profileDropdownPanel');
      const chevron = document.getElementById('profileChevronIcon');
      if (!btn || !panel) return;

      const isOpen = panel.classList.contains('is-open');
      if (isOpen) {
        closeProfileDropdown();
      } else {
        panel.classList.remove('is-closed');
        panel.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
      }
    }

    function closeProfileDropdown() {
      const btn = document.getElementById('profileTriggerBtn');
      const panel = document.getElementById('profileDropdownPanel');
      const chevron = document.getElementById('profileChevronIcon');
      if (!btn || !panel) return;

      panel.classList.remove('is-open');
      panel.classList.add('is-closed');
      btn.setAttribute('aria-expanded', 'false');
      if (chevron) chevron.style.transform = 'rotate(0deg)';
    }

    function switchAudienceTab(mode) {
      const btnUmkm = document.getElementById('tabBtnUmkm');
      const btnAgency = document.getElementById('tabBtnAgency');
      const panelUmkm = document.getElementById('audiencePanelUmkm');
      const panelAgency = document.getElementById('audiencePanelAgency');

      if (mode === 'agency') {
        btnAgency.classList.add('is-active');
        btnAgency.classList.remove('text-slate-600');
        btnUmkm.classList.remove('is-active');
        btnUmkm.classList.add('text-slate-600');
        panelAgency.classList.remove('hidden');
        panelUmkm.classList.add('hidden');
      } else {
        btnUmkm.classList.add('is-active');
        btnUmkm.classList.remove('text-slate-600');
        btnAgency.classList.remove('is-active');
        btnAgency.classList.add('text-slate-600');
        panelUmkm.classList.remove('hidden');
        panelAgency.classList.add('hidden');
      }
    }

    const pillarData = <?= json_encode($pillar_payload, JSON_UNESCAPED_UNICODE); ?>;

    function selectPillar(key) {
      const data = pillarData[key];
      if (!data) return;

      document.getElementById('pillarCardSmm').classList.toggle('is-active', key === 'smm');
      document.getElementById('pillarCardContent').classList.toggle('is-active', key === 'content');
      document.getElementById('pillarCardAds').classList.toggle('is-active', key === 'ads');

      const badgeEl = document.getElementById('pillarBadge');
      badgeEl.className = data.badgeClass;
      badgeEl.textContent = data.badge;

      document.getElementById('pillarTitle').textContent = data.title;
      document.getElementById('pillarDesc').textContent = data.desc;
    }

    document.addEventListener('click', function(event) {
      const wrapper = document.getElementById('profileDropdownWrapper');
      if (wrapper && !wrapper.contains(event.target)) {
        closeProfileDropdown();
      }
    });

    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        closeProfileDropdown();
      }
    });
  </script>
</body>
</html>