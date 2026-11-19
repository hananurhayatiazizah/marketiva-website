<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// Ambil semua data layanan digabung dengan kategori
$query = "SELECT s.*, c.nama_kategori FROM services s LEFT JOIN categories c ON s.kategori_id = c.id ORDER BY s.id DESC";
$result = mysqli_query($conn, $query);

$services_all = [];
$bundling_items = [];

while ($row = mysqli_fetch_assoc($result)) {
    $services_all[] = $row;
    $nama_kat = strtolower($row['nama_kategori'] ?? '');
    if (strpos($nama_kat, 'bundling') !== false || strpos($nama_kat, 'paket') !== false) {
        $bundling_items[] = $row;
    }
}

// Jika kategori bundling belum ada, ambil 3 data teratas sebagai cadangan
if (empty($bundling_items)) {
    $bundling_items = array_slice($services_all, 0, 3);
}

// Status Sesi Login
$is_logged_in   = isset($_SESSION['user']);
$user_name      = trim((string)($_SESSION['user'] ?? ''));
$user_role      = $_SESSION['role'] ?? 'user';
$user_email_hdr = $_SESSION['email'] ?? '';
$is_admin_hdr   = ($user_role === 'admin');
$dashboard_link = $is_admin_hdr ? 'admin-dashboard.php' : 'user-dashboard.php';
$role_label_hdr = $is_admin_hdr ? 'Administrator' : 'Member UMKM';
$avatar_char    = ($is_logged_in && $user_name !== '') ? strtoupper(mb_substr($user_name, 0, 1)) : 'M';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Katalog Layanan Digital - Marketiva Agency</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
    .card-studio { background-color: #FFFFFF; border-radius: 1.25rem; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); transition: all 0.3s ease; }
    .card-studio:hover { transform: translateY(-3px); box-shadow: 0 12px 20px -5px rgba(225, 29, 72, 0.08); border-color: #FDA4AF; }

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
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between">

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

        <a class="hover:text-[#E11D48] transition-colors flex items-center gap-1.5" href="article.php">
          <span class="material-symbols-outlined text-[18px]">grid_view</span>
          <span>Article</span>
        </a>
      </nav>
      
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
          <a href="login.php" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px]">login</span>
            <span>Masuk / Daftar</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- MAIN CONTENT -->
  <main class="max-w-[1320px] mx-auto px-6 py-12 w-full flex-1 space-y-16">
    
    <!-- HERO SECTION: BUNDLING -->
    <section class="space-y-6">
      <div class="flex flex-col items-center text-center max-w-2xl mx-auto space-y-3">
        <span class="px-3.5 py-1.5 rounded-full bg-rose-50 text-[#E11D48] text-xs font-extrabold uppercase border border-rose-200 flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[16px]">verified</span> Paket Bundling & Unggulan
        </span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900">Solusi Terbaik untuk Pertumbuhan UMKM</h1>
        <p class="text-xs text-slate-500">Pilihan paket spesial paling direkomendasikan untuk mendongkrak omset dan visibilitas digital Anda.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($bundling_items as $row): ?>
          <div class="card-studio flex flex-col justify-between">
            <div>
              <div class="h-48 w-full bg-slate-100 relative overflow-hidden">
                <?php if (!empty($row['gambar'])): ?>
                  <img src="<?= htmlspecialchars($row['gambar']); ?>" class="w-full h-full object-cover">
                <?php else: ?>
                  <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-rose-50 to-indigo-50 text-slate-400 gap-1">
                    <span class="material-symbols-outlined text-[36px]">image</span>
                    <span class="text-[10px] font-bold uppercase">Marketiva Bundling</span>
                  </div>
                <?php endif; ?>
                <div class="absolute top-3 left-3">
                  <span class="px-3 py-1 rounded-lg bg-rose-600 text-white text-[10px] font-extrabold uppercase shadow-sm">
                    <?= htmlspecialchars($row['nama_kategori'] ?? 'Bundling'); ?>
                  </span>
                </div>
              </div>
              <div class="p-6 space-y-3">
                <h3 class="text-lg font-extrabold text-slate-900"><?= htmlspecialchars($row['nama_layanan']); ?></h3>
                <p class="text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars($row['deskripsi'] ?: 'Layanan profesional berstandar agensi.'); ?></p>
              </div>
            </div>
            <div class="px-6 pb-6 pt-2 flex items-center justify-between border-t border-slate-100 mt-auto">
              <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase">Mulai Dari</p>
                <p class="text-base font-extrabold text-emerald-600">Rp <?= number_format($row['harga'], 0, ',', '.'); ?></p>
              </div>
              <a href="checkout.php?layanan=<?= urlencode($row['nama_layanan']); ?>&harga=<?= $row['harga']; ?>" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs flex items-center gap-1.5 transition-all">
                <span>Pesan Jasa</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- KATALOG LENGKAP -->
    <section class="space-y-8 pt-6 border-t border-slate-200">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-900">Semua Katalog Layanan</h2>
          <p class="text-xs text-slate-500 mt-0.5">Dikelompokkan rapi berdasarkan lini kategori digital.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php if (!empty($services_all)): ?>
          <?php foreach ($services_all as $row): ?>
            <div class="card-studio flex flex-col justify-between">
              <div>
                <div class="h-48 w-full bg-slate-100 relative overflow-hidden">
                  <?php if (!empty($row['gambar'])): ?>
                    <img src="<?= htmlspecialchars($row['gambar']); ?>" class="w-full h-full object-cover">
                  <?php else: ?>
                    <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-indigo-50 to-slate-50 text-slate-400 gap-1">
                      <span class="material-symbols-outlined text-[36px]">image</span>
                    </div>
                  <?php endif; ?>
                  <div class="absolute top-3 left-3">
                    <span class="px-3 py-1 rounded-lg bg-white/90 backdrop-blur-md text-indigo-700 text-[10px] font-extrabold uppercase shadow-sm">
                      <?= htmlspecialchars($row['nama_kategori'] ?? 'Digital'); ?>
                    </span>
                  </div>
                </div>
                <div class="p-6 space-y-3">
                  <h3 class="text-lg font-extrabold text-slate-900"><?= htmlspecialchars($row['nama_layanan']); ?></h3>
                  <p class="text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars($row['deskripsi'] ?: 'Layanan profesional pilihan agensi.'); ?></p>
                </div>
              </div>
              <div class="px-6 pb-6 pt-2 flex items-center justify-between border-t border-slate-100 mt-auto">
                <div>
                  <p class="text-[10px] font-extrabold text-slate-400 uppercase">Mulai Dari</p>
                  <p class="text-base font-extrabold text-emerald-600">Rp <?= number_format($row['harga'], 0, ',', '.'); ?></p>
                </div>
                <a href="checkout.php?layanan=<?= urlencode($row['nama_layanan']); ?>&harga=<?= $row['harga']; ?>" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs flex items-center gap-1.5 transition-all">
                  <span>Pesan Jasa</span>
                  <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-span-3 text-center py-16 card-studio space-y-3 p-8">
            <span class="material-symbols-outlined text-[48px] text-slate-300">inventory_2</span>
            <p class="text-sm font-bold text-slate-700">Belum ada layanan yang tersedia di katalog.</p>
          </div>
        <?php endif; ?>
      </div>
    </section>

  </main>

  <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400 mt-12">
    © 2026 Marketiva Digital Agency.
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