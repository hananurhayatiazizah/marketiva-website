<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

$session_role    = $_SESSION['role'] ?? '';
$is_admin_viewer = ($session_role === 'admin' && isset($_GET['view_user']) && intval($_GET['view_user']) > 0);

// Proteksi: Pastikan user sudah login (atau admin membuka mode read-only profil user)
if (!isset($_SESSION['user']) || ($session_role === 'admin' && !$is_admin_viewer)) {
    header("Location: login.php");
    exit();
}

$current_user  = $_SESSION['user'];
$user_id       = $is_admin_viewer ? intval($_GET['view_user']) : ($_SESSION['user_id'] ?? null);
$current_email = $_SESSION['email'] ?? '';

$update_message = '';
$update_error   = '';
$pass_message   = '';
$pass_error     = '';
$order_message  = '';
$order_error    = '';
$active_tab     = 'profile';
$just_saved_kpi = false;

// 0. KETIKA USER MEMBATALKAN PESANAN YANG BELUM DIBAYAR (Hanya pemilik akun)
if (!$is_admin_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $active_tab      = 'onprogress';
    $target_order_pk = intval($_POST['order_pk_id'] ?? 0);
    $safe_email      = mysqli_real_escape_string($conn, $current_email);
    $safe_id         = intval($user_id);

    if ($target_order_pk > 0) {
        $cek_q   = mysqli_query($conn, "SELECT id, status FROM orders WHERE id = $target_order_pk AND (user_id = $safe_id OR user_email = '$safe_email') LIMIT 1");
        $cek_row = $cek_q ? mysqli_fetch_assoc($cek_q) : null;

        if ($cek_row) {
            $st_lower    = strtolower(trim($cek_row['status'] ?? ''));
            $belum_bayar = (strpos($st_lower, 'menunggu pembayaran') !== false) || (strpos($st_lower, 'menunggu konfirmasi') !== false);

            if ($belum_bayar) {
                $upd_cancel = mysqli_prepare($conn, "UPDATE orders SET status = 'Dibatalkan' WHERE id = ?");
                mysqli_stmt_bind_param($upd_cancel, "i", $target_order_pk);
                if (mysqli_stmt_execute($upd_cancel)) {
                    $order_message = "Pesanan berhasil dibatalkan.";
                } else {
                    $order_error = "Gagal membatalkan pesanan di database.";
                }
            } else {
                $order_error = "Pesanan yang sudah dibayar atau diproses tidak dapat dibatalkan di sini.";
            }
        } else {
            $order_error = "Data pesanan tidak ditemukan.";
        }
    }
}

// 1. KETIKA FORM SIMPAN PROFIL DI-SUBMIT (Hanya pemilik akun)
if (!$is_admin_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $active_tab   = 'profile';
    $nama_baru    = trim($_POST['inputProfileName'] ?? '');
    $umkm_baru    = trim($_POST['inputProfileUmkm'] ?? '');
    $phone_baru   = trim($_POST['inputProfilePhone'] ?? '');
    $email_baru   = trim($_POST['inputProfileEmail'] ?? '');
    $address_baru = trim($_POST['inputProfileAddress'] ?? '');

    if (!empty($nama_baru) && !empty($email_baru)) {
        if ($user_id) {
            $update_stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, nama_umkm = ?, whatsapp = ?, email = ?, alamat = ? WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "sssssi", $nama_baru, $umkm_baru, $phone_baru, $email_baru, $address_baru, $user_id);
        } else {
            $update_stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, nama_umkm = ?, whatsapp = ?, email = ?, alamat = ? WHERE email = ?");
            mysqli_stmt_bind_param($update_stmt, "ssssss", $nama_baru, $umkm_baru, $phone_baru, $email_baru, $address_baru, $_SESSION['email']);
        }

        if (mysqli_stmt_execute($update_stmt)) {
            $_SESSION['user']  = $nama_baru;
            $_SESSION['email'] = $email_baru;
            $current_user      = $nama_baru;
            $current_email     = $email_baru;
            $update_message    = "Profil dan kelengkapan data usaha Anda berhasil disimpan!";
            $just_saved_kpi    = true;
        } else {
            $update_error = "Gagal memperbarui database: " . mysqli_error($conn);
        }
    } else {
        $update_error = "Nama Lengkap dan Email tidak boleh kosong.";
    }
}

// 2. KETIKA FORM GANTI SANDI DI-SUBMIT (Hanya pemilik akun)
if (!$is_admin_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $active_tab      = 'security';
    $sandi_lama      = trim($_POST['currentPassword'] ?? '');
    $sandi_baru      = trim($_POST['newPassword'] ?? '');
    $konfirmasi_baru = trim($_POST['confirmPassword'] ?? '');

    if (!empty($sandi_lama) && !empty($sandi_baru) && !empty($konfirmasi_baru)) {
        if ($sandi_baru === $konfirmasi_baru) {
            if ($user_id) {
                $check_stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
                mysqli_stmt_bind_param($check_stmt, "i", $user_id);
            } else {
                $check_stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE email = ?");
                mysqli_stmt_bind_param($check_stmt, "s", $_SESSION['email']);
            }
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);
            $user_row  = mysqli_fetch_assoc($check_res);

            if ($user_row && $user_row['password'] === $sandi_lama) {
                if ($user_id) {
                    $up_pass = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($up_pass, "si", $sandi_baru, $user_id);
                } else {
                    $up_pass = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = ?");
                    mysqli_stmt_bind_param($up_pass, "ss", $sandi_baru, $_SESSION['email']);
                }

                if (mysqli_stmt_execute($up_pass)) {
                    $pass_message = "Kata sandi Anda berhasil diperbarui!";
                } else {
                    $pass_error = "Gagal memperbarui kata sandi di database.";
                }
            } else {
                $pass_error = "Kata sandi lama yang Anda masukkan salah!";
            }
        } else {
            $pass_error = "Konfirmasi kata sandi baru tidak cocok!";
        }
    } else {
        $pass_error = "Semua kolom form sandi wajib diisi!";
    }
}

// Ambil data profil terbaru dari database MySQL
if ($user_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE nama_lengkap = ? OR email = ?");
    mysqli_stmt_bind_param($stmt, "ss", $current_user, $current_user);
}
mysqli_stmt_execute($stmt);
$result    = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);

if ($is_admin_viewer && !$user_data) {
    header("Location: admin-dashboard.php?tab=users");
    exit();
}

$target_user_id    = intval($user_data['id'] ?? $user_id ?? 0);
$target_user_email = $user_data['email'] ?? $current_email;

// Cek apakah profil sudah memiliki isi sehingga langsung tampil sebagai KPI Card
$has_profile_filled = (
    !empty(trim((string)($user_data['nama_lengkap'] ?? ''))) &&
    !empty(trim((string)($user_data['email'] ?? ''))) &&
    (
        !empty(trim((string)($user_data['nama_umkm'] ?? ''))) ||
        !empty(trim((string)($user_data['whatsapp'] ?? ''))) ||
        !empty(trim((string)($user_data['alamat'] ?? '')))
    )
);
$show_kpi_first = ($is_admin_viewer || $just_saved_kpi || ($has_profile_filled && empty($update_error)));

// Hitung kelengkapan profil (%)
$filled_fields = 0;
foreach (['nama_lengkap', 'email', 'nama_umkm', 'whatsapp', 'alamat'] as $f_key) {
    if (!empty(trim((string)($user_data[$f_key] ?? '')))) {
        $filled_fields++;
    }
}
$profile_completion = intval(round(($filled_fields / 5) * 100));

// Bangun peta gambar katalog dari folder uploads/ & tabel katalog bila tersedia
$catalog_img_map = [];
$featured_services = [];
foreach (['services', 'catalog', 'katalog', 'layanan', 'products', 'produk'] as $tbl_cand) {
    $t_chk = mysqli_query($conn, "SHOW TABLES LIKE '$tbl_cand'");
    if ($t_chk && mysqli_num_rows($t_chk) > 0) {
        $q_cat = mysqli_query($conn, "SELECT * FROM `$tbl_cand` ORDER BY id DESC");
        if ($q_cat) {
            while ($c_row = mysqli_fetch_assoc($q_cat)) {
                $c_name = strtolower(trim((string)($c_row['nama_layanan'] ?? $c_row['nama'] ?? $c_row['judul'] ?? $c_row['title'] ?? '')));
                $c_img  = trim((string)($c_row['gambar'] ?? $c_row['image'] ?? $c_row['foto'] ?? $c_row['thumbnail'] ?? ''));
                if ($c_name !== '' && $c_img !== '') {
                    $catalog_img_map[$c_name] = $c_img;
                }
                if (count($featured_services) < 3 && !empty($c_row['nama_layanan'])) {
                    $featured_services[] = $c_row;
                }
            }
        }
        if (!empty($featured_services)) {
            break;
        }
    }
}

$uploads_files = [];
$uploads_dir   = __DIR__ . '/uploads';
if (is_dir($uploads_dir)) {
    foreach (scandir($uploads_dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $full_p = $uploads_dir . '/' . $f;
        if (is_file($full_p) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)) {
            $uploads_files[] = 'uploads/' . $f;
        }
    }
}

function resolveOrderImage(array $row, array $catalogMap, array $uploadList): string {
    foreach (['gambar', 'image', 'foto', 'thumbnail'] as $col) {
        if (!empty($row[$col])) {
            $val = trim((string)$row[$col]);
            $rel = (stripos($val, 'uploads/') === 0) ? $val : 'uploads/' . ltrim($val, '/');
            if (file_exists(__DIR__ . '/' . ltrim($rel, '/'))) {
                return ltrim($rel, '/');
            }
        }
    }

    $nama_layanan = strtolower(trim((string)($row['nama_layanan'] ?? '')));
    if ($nama_layanan !== '' && isset($catalogMap[$nama_layanan])) {
        $val = $catalogMap[$nama_layanan];
        $rel = (stripos($val, 'uploads/') === 0) ? $val : 'uploads/' . ltrim($val, '/');
        if (file_exists(__DIR__ . '/' . ltrim($rel, '/'))) {
            return ltrim($rel, '/');
        }
    }

    if ($nama_layanan !== '' && !empty($uploadList)) {
        $tokens = preg_split('/[^a-z0-9]+/i', $nama_layanan, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($uploadList as $u_path) {
            $base = strtolower(pathinfo($u_path, PATHINFO_FILENAME));
            foreach ($tokens as $tok) {
                if (strlen($tok) >= 4 && strpos($base, $tok) !== false) {
                    return $u_path;
                }
            }
        }
    }

    return '';
}

function getServiceBadgeStyle(array $row): array {
    $nama     = strtolower($row['nama_layanan'] ?? '');
    $kategori = strtolower($row['kategori'] ?? '');

    if (strpos($nama, 'ads') !== false || strpos($kategori, 'ads') !== false) {
        return ['icon' => 'campaign', 'grad' => 'from-blue-500 to-indigo-600'];
    }
    if (strpos($nama, 'caption') !== false || strpos($nama, 'copywriting') !== false) {
        return ['icon' => 'edit_note', 'grad' => 'from-amber-500 to-orange-600'];
    }
    if (strpos($nama, 'story') !== false || strpos($nama, 'desain') !== false || strpos($kategori, 'desain') !== false) {
        return ['icon' => 'palette', 'grad' => 'from-rose-500 to-pink-600'];
    }
    return ['icon' => 'rocket_launch', 'grad' => 'from-emerald-500 to-teal-600'];
}

// 3. AMBIL DATA PESANAN
$count_progress  = 0;
$count_history   = 0;
$count_completed = 0;
$orders_progress = [];
$orders_history  = [];

$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'orders'");
if ($check_table && mysqli_num_rows($check_table) > 0) {
    $safe_email = mysqli_real_escape_string($conn, $target_user_email);
    $safe_id    = intval($target_user_id);

    $query_progress  = "SELECT * FROM orders WHERE (user_id = $safe_id OR user_email = '$safe_email') AND status NOT IN ('Selesai', 'Dibatalkan') ORDER BY id DESC";
    $result_progress = mysqli_query($conn, $query_progress);
    if ($result_progress) {
        while ($r = mysqli_fetch_assoc($result_progress)) {
            $r['_img'] = resolveOrderImage($r, $catalog_img_map, $uploads_files);
            $orders_progress[] = $r;
        }
        $count_progress = count($orders_progress);
    }

    $query_history  = "SELECT * FROM orders WHERE (user_id = $safe_id OR user_email = '$safe_email') AND status IN ('Selesai', 'Dibatalkan') ORDER BY id DESC";
    $result_history = mysqli_query($conn, $query_history);
    if ($result_history) {
        while ($r = mysqli_fetch_assoc($result_history)) {
            $r['_img'] = resolveOrderImage($r, $catalog_img_map, $uploads_files);
            if (strtolower(trim($r['status'] ?? '')) === 'selesai') {
                $count_completed++;
            }
            $orders_history[] = $r;
        }
        $count_history = count($orders_history);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $is_admin_viewer ? 'Profil Pelanggan (Read-Only) - Marketiva' : 'Dashboard Pelanggan - Marketiva'; ?></title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">

  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
    .card-studio { background-color: #FFFFFF; border-radius: 1rem; border: 1px solid #E2E8F0; padding: 1.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02); }
    .input-studio { height: 48px; border-radius: 0.75rem; border: 1.5px solid #CBD5E1; padding: 0 1rem; width: 100%; font-size: 0.875rem; background-color: #FFFFFF; color: #1E293B; transition: all 0.2s; }
    .input-studio:focus { outline: none; border-color: #E11D48; box-shadow: 0 0 0 3px rgba(225,29,72,0.1); }
    textarea.input-studio { height: auto; padding-top: 0.75rem; padding-bottom: 0.75rem; }
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between">

  <!-- TOP NAVBAR -->
  <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200">
    <div class="max-w-[1320px] mx-auto px-6 h-20 flex items-center justify-between">
      <a href="<?= $is_admin_viewer ? 'admin-dashboard.php?tab=users' : 'index.php'; ?>" class="flex items-center gap-2.5">
        <span class="font-extrabold text-2xl tracking-tight">
          <span class="text-[#F43F5E]">Mar</span><span class="text-[#3B82F6]">ke</span><span class="text-[#10B981]">ti</span><span class="text-[#F59E0B]">va</span>
        </span>
        <?php if ($is_admin_viewer): ?>
          <span class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 text-[10px] font-extrabold uppercase border border-indigo-200">Read-Only Profile View</span>
        <?php endif; ?>
      </a>

      <div class="flex items-center gap-4">
        <?php if ($is_admin_viewer): ?>
          <div class="hidden sm:flex items-center gap-2.5 bg-indigo-50 border border-indigo-200 px-3.5 py-2 rounded-2xl">
            <span class="material-symbols-outlined text-indigo-600 text-[18px]">admin_panel_settings</span>
            <p class="text-xs font-extrabold text-indigo-900">Mode Pratinjau Admin</p>
          </div>
          <a href="admin-dashboard.php?tab=users" class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-900 text-white hover:bg-slate-800 font-bold text-xs transition-all">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Kembali ke Data Pelanggan</span>
          </a>
        <?php else: ?>
          <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 px-4 py-2 rounded-2xl">
            <div class="w-8 h-8 rounded-xl bg-rose-100 text-[#E11D48] flex items-center justify-center font-bold text-xs">
              <?= strtoupper(substr($user_data['nama_lengkap'] ?? 'U', 0, 1)); ?>
            </div>
            <div>
              <p class="text-[10px] text-slate-400 font-bold uppercase">Masuk Sebagai</p>
              <p class="text-xs font-extrabold text-slate-800"><?= htmlspecialchars($user_data['nama_lengkap'] ?? $_SESSION['user']); ?></p>
            </div>
          </div>

          <a href="login.php?action=logout" class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 font-bold text-xs transition-all">
            <span class="material-symbols-outlined text-[18px]">logout</span>
            <span>Keluar</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- DASHBOARD CONTAINER -->
  <main class="max-w-[1320px] mx-auto px-6 py-10 w-full flex-1">

    <?php if (!empty($update_message)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[20px]">check_circle</span>
        <span><?= htmlspecialchars($update_message); ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($update_error)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[20px]">error</span>
        <span><?= htmlspecialchars($update_error); ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($pass_message)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[20px]">check_circle</span>
        <span><?= htmlspecialchars($pass_message); ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($pass_error)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[20px]">error</span>
        <span><?= htmlspecialchars($pass_error); ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($order_message)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[20px]">check_circle</span>
        <span><?= htmlspecialchars($order_message); ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($order_error)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[20px]">error</span>
        <span><?= htmlspecialchars($order_error); ?></span>
      </div>
    <?php endif; ?>

    <?php if (!$is_admin_viewer): ?>
      <!-- WELCOME & QUICK ACTION BANNER INTERAKTIF -->
      <div class="mb-8 rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-5">
        <div class="space-y-1.5">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-50 text-[#E11D48] text-[11px] font-extrabold">
            <span class="w-2 h-2 rounded-full bg-[#E11D48] animate-pulse"></span>
            <span>Ruang Kerja UMKM • <?= htmlspecialchars(!empty(trim((string)($user_data['nama_umkm'] ?? ''))) ? $user_data['nama_umkm'] : 'Lengkapi Nama Usaha'); ?></span>
          </div>
          <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900">
            Halo, <?= htmlspecialchars($user_data['nama_lengkap'] ?? $_SESSION['user']); ?>!
          </h1>
          <p class="text-xs text-slate-500">
            Kelengkapan profil Anda <strong class="text-slate-800"><?= $profile_completion; ?>%</strong> •
            <strong class="text-amber-700"><?= $count_progress; ?> pesanan aktif</strong> •
            <strong class="text-emerald-700"><?= $count_completed; ?> pesanan selesai</strong>.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <a href="index.php" class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition-all flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[17px]">home</span>
            <span>Lihat Beranda</span>
          </a>
          <a href="catalog.php" class="px-4 py-2.5 rounded-xl bg-[#E11D48] hover:bg-[#BE123C] text-white font-extrabold text-xs shadow-sm transition-all flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[17px]">add_shopping_cart</span>
            <span>Pesan Layanan</span>
          </a>
          <?php if ($count_progress > 0): ?>
            <button type="button" onclick="switchTab('onprogress')" class="px-4 py-2.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-extrabold text-xs transition-all flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[17px]">hourglass_top</span>
              <span>Cek Pesanan (<?= $count_progress; ?>)</span>
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

      <!-- SIDEBAR KIRI -->
      <div class="lg:col-span-3 card-studio space-y-4 p-4 flex flex-col justify-between">
        <div class="space-y-1.5">
          <div class="px-3 py-2">
            <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Menu Utama</p>
          </div>

          <button type="button" onclick="switchTab('profile')" id="btnTabProfile" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-rose-50 text-[#E11D48] transition-all">
            <span class="material-symbols-outlined text-[20px]">badge</span>
            <span>Profil & Data Usaha</span>
          </button>

          <?php if (!$is_admin_viewer): ?>
            <button type="button" onclick="switchTab('onprogress')" id="btnTabOnProgress" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
              <span class="material-symbols-outlined text-[20px]">hourglass_top</span>
              <span>Pesanan On Progress</span>
              <span class="ml-auto bg-slate-200 text-slate-700 text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $count_progress; ?></span>
            </button>

            <button type="button" onclick="switchTab('history')" id="btnTabHistory" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
              <span class="material-symbols-outlined text-[20px]">history</span>
              <span>Riwayat Pesanan</span>
              <span class="ml-auto bg-slate-200 text-slate-700 text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $count_history; ?></span>
            </button>

            <div class="pt-4 mt-4 border-t border-slate-100 space-y-1.5">
              <p class="px-3 pb-2 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Navigasi & Layanan</p>

              <a href="index.php" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all">
                <span class="material-symbols-outlined text-[20px]">home</span>
                <span>Lihat Beranda</span>
              </a>

              <a href="catalog.php" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-blue-600 hover:bg-blue-50 transition-all">
                <span class="material-symbols-outlined text-[20px]">storefront</span>
                <span>Katalog Layanan</span>
              </a>

              <a href="https://wa.me/6281244178641" target="_blank" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-emerald-600 hover:bg-emerald-50 transition-all">
                <span class="material-symbols-outlined text-[20px]">support_agent</span>
                <span>Bantuan WhatsApp</span>
              </a>

              <button type="button" onclick="switchTab('security')" id="btnTabSecurity" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
                <span class="material-symbols-outlined text-[20px]">lock_reset</span>
                <span>Keamanan & Sandi</span>
              </button>
            </div>
          <?php else: ?>
            <div class="pt-4 mt-4 border-t border-slate-100 space-y-2">
              <div class="p-3.5 rounded-2xl bg-indigo-50/70 border border-indigo-100 space-y-1.5">
                <p class="text-[11px] font-extrabold text-indigo-900 flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[16px]">lock</span> Akses Read-Only
                </p>
                <p class="text-[10px] text-indigo-700 leading-relaxed">
                  Admin hanya dapat melihat ringkasan KPI profil & data usaha pelanggan ini tanpa mengubah isinya.
                </p>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2 mt-6">
          <div class="flex items-center gap-2 text-slate-800">
            <span class="material-symbols-outlined text-rose-600 text-[18px]">verified</span>
            <p class="text-[11px] font-extrabold">Akun Terverifikasi</p>
          </div>
          <p class="text-[10px] text-slate-500 leading-relaxed">Nikmati layanan prioritas digital marketing bersama Marketiva.</p>
        </div>
      </div>

      <!-- KONTEN KANAN -->
      <div class="lg:col-span-9 space-y-6">

        <!-- TAB 1: PROFIL & DATA PRIBADI (KPI RINGKASAN DATA TERSIMPAN + FORM EDIT) -->
        <div id="tabContentProfile" class="card-studio space-y-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-4 gap-3">
            <div>
              <h2 class="text-xl font-extrabold text-slate-900">
                <?= $is_admin_viewer ? 'Profil & Informasi UMKM Pelanggan' : 'Profil & Informasi UMKM'; ?>
              </h2>
              <p class="text-slate-500 text-xs mt-0.5">
                <?= $is_admin_viewer
                    ? 'Ringkasan data identitas pribadi dan detail bisnis yang telah dilengkapi oleh pelanggan.'
                    : 'Ringkasan identitas akun serta detail usaha Anda yang tersimpan di sistem Marketiva.'; ?>
              </p>
            </div>
            <div class="flex items-center gap-2 self-start sm:self-auto">
              <span class="px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Terhubung ke Database
              </span>
            </div>
          </div>

          <!-- VIEW 1: KPI RINGKASAN PROFIL YANG TELAH DIISI (READ-ONLY) -->
          <div id="profileKpiView" class="<?= $show_kpi_first ? '' : 'hidden'; ?> space-y-6">
            <!-- Hero Banner Profil User -->
            <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-rose-950 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
              <div class="flex items-center gap-4 min-w-0">
                <div class="w-14 h-14 rounded-2xl bg-[#E11D48] text-white flex items-center justify-center font-extrabold text-xl shadow-md flex-shrink-0">
                  <span class="material-symbols-outlined text-[28px]">person</span>
                </div>
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-extrabold truncate"><?= htmlspecialchars($user_data['nama_lengkap'] ?? '-'); ?></h3>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-[10px] font-extrabold uppercase">
                      Mitra UMKM
                    </span>
                  </div>
                  <p class="text-xs text-rose-200 font-semibold flex items-center gap-1.5 mt-1 truncate">
                    <span class="material-symbols-outlined text-[15px]">storefront</span>
                    <span class="truncate"><?= htmlspecialchars(!empty(trim((string)($user_data['nama_umkm'] ?? ''))) ? $user_data['nama_umkm'] : 'Nama usaha belum diisi'); ?></span>
                  </p>
                </div>
              </div>

              <?php if (!$is_admin_viewer): ?>
                <button type="button" onclick="toggleProfileMode('edit')" class="self-start sm:self-auto px-4 py-2.5 rounded-xl bg-white text-slate-900 hover:bg-rose-50 hover:text-[#E11D48] font-extrabold text-xs transition-all flex items-center gap-1.5 shadow-sm flex-shrink-0">
                  <span class="material-symbols-outlined text-[17px]">edit_square</span>
                  <span>Perbarui Profil</span>
                </button>
              <?php endif; ?>
            </div>

            <!-- Baris KPI Statistik Profil & Aktivitas -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-rose-100 text-[#E11D48] flex items-center justify-center flex-shrink-0">
                  <span class="material-symbols-outlined text-[22px]">fact_check</span>
                </div>
                <div>
                  <p class="text-[10px] font-extrabold text-slate-400 uppercase">Kelengkapan Profil</p>
                  <p class="text-lg font-extrabold text-slate-900"><?= $profile_completion; ?>%</p>
                </div>
              </div>

              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                  <span class="material-symbols-outlined text-[22px]">pending_actions</span>
                </div>
                <div>
                  <p class="text-[10px] font-extrabold text-slate-400 uppercase">Pesanan Berjalan</p>
                  <p class="text-lg font-extrabold text-slate-900"><?= $count_progress; ?> Layanan</p>
                </div>
              </div>

              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                  <span class="material-symbols-outlined text-[22px]">task_alt</span>
                </div>
                <div>
                  <p class="text-[10px] font-extrabold text-slate-400 uppercase">Pesanan Selesai</p>
                  <p class="text-lg font-extrabold text-slate-900"><?= $count_completed; ?> Selesai</p>
                </div>
              </div>
            </div>

            <!-- Grid KPI Data Tersimpan -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="p-4 rounded-2xl border border-slate-200 bg-white space-y-1">
                <p class="text-[10px] font-extrabold text-slate-400 uppercase flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[15px] text-rose-500">badge</span> Nama Lengkap Pemilik
                </p>
                <p class="text-sm font-extrabold text-slate-900">
                  <?= htmlspecialchars(!empty(trim((string)($user_data['nama_lengkap'] ?? ''))) ? $user_data['nama_lengkap'] : '-'); ?>
                </p>
              </div>

              <div class="p-4 rounded-2xl border border-slate-200 bg-white space-y-1">
                <p class="text-[10px] font-extrabold text-slate-400 uppercase flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[15px] text-blue-500">mail</span> Alamat Email Aktif
                </p>
                <p class="text-sm font-extrabold text-slate-900 break-all">
                  <?= htmlspecialchars(!empty(trim((string)($user_data['email'] ?? ''))) ? $user_data['email'] : '-'); ?>
                </p>
              </div>

              <div class="p-4 rounded-2xl border border-slate-200 bg-white space-y-1">
                <p class="text-[10px] font-extrabold text-slate-400 uppercase flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[15px] text-indigo-500">storefront</span> Nama Usaha / Brand / UMKM
                </p>
                <p class="text-sm font-extrabold text-slate-900">
                  <?= htmlspecialchars(!empty(trim((string)($user_data['nama_umkm'] ?? ''))) ? $user_data['nama_umkm'] : 'Belum diisi'); ?>
                </p>
              </div>

              <div class="p-4 rounded-2xl border border-slate-200 bg-white space-y-1">
                <p class="text-[10px] font-extrabold text-slate-400 uppercase flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[15px] text-emerald-500">call</span> Nomor WhatsApp Usaha
                </p>
                <p class="text-sm font-extrabold text-slate-900">
                  <?= htmlspecialchars(!empty(trim((string)($user_data['whatsapp'] ?? ''))) ? $user_data['whatsapp'] : 'Belum diisi'); ?>
                </p>
              </div>

              <div class="sm:col-span-2 p-4 rounded-2xl border border-slate-200 bg-white space-y-1">
                <p class="text-[10px] font-extrabold text-slate-400 uppercase flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[15px] text-amber-500">location_on</span> Alamat Lokasi Usaha
                </p>
                <p class="text-sm font-bold text-slate-800 leading-relaxed">
                  <?= htmlspecialchars(!empty(trim((string)($user_data['alamat'] ?? ''))) ? $user_data['alamat'] : 'Belum diisi'); ?>
                </p>
              </div>
            </div>

            <?php if (!$is_admin_viewer && !empty($featured_services)): ?>
              <!-- REKOMENDASI LAYANAN POPULER -->
              <div class="pt-4 border-t border-slate-100 space-y-3">
                <div class="flex items-center justify-between">
                  <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Rekomendasi Layanan untuk UMKM</h3>
                  <a href="catalog.php" class="text-xs font-bold text-[#E11D48] hover:underline">Lihat Semua →</a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                  <?php foreach ($featured_services as $fs): ?>
                    <?php
                      $fs_name  = $fs['nama_layanan'] ?? 'Layanan Marketiva';
                      $fs_price = floatval($fs['harga'] ?? 0);
                      $fs_url   = 'checkout.php?layanan=' . urlencode($fs_name) . '&harga=' . urlencode((string)$fs_price);
                    ?>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-rose-200 transition-all flex flex-col justify-between gap-3">
                      <div>
                        <p class="text-xs font-extrabold text-slate-900 line-clamp-1"><?= htmlspecialchars($fs_name); ?></p>
                        <p class="text-xs font-extrabold text-[#E11D48] mt-1">Rp <?= number_format($fs_price, 0, ',', '.'); ?></p>
                      </div>
                      <a href="<?= htmlspecialchars($fs_url); ?>" class="text-[11px] font-extrabold text-slate-700 hover:text-[#E11D48] inline-flex items-center gap-1">
                        <span>Pesan Sekarang</span>
                        <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- VIEW 2: FORM EDIT / LENGKAPI PROFIL (Hanya untuk pemilik akun) -->
          <?php if (!$is_admin_viewer): ?>
            <form id="profileEditForm" method="POST" action="user-dashboard.php" class="space-y-5 <?= $show_kpi_first ? 'hidden' : ''; ?>">
              <input type="hidden" name="update_profile" value="1">

              <div>
                <p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-3">1. Informasi Personal</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nama Lengkap</label>
                    <input name="inputProfileName" type="text" class="input-studio" value="<?= htmlspecialchars($user_data['nama_lengkap'] ?? ''); ?>" required>
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Alamat Email Aktif</label>
                    <input name="inputProfileEmail" type="email" class="input-studio" value="<?= htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                  </div>
                </div>
              </div>

              <div class="pt-2">
                <p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-3">2. Informasi Bisnis & Kontak Usaha</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nama Usaha / Brand / UMKM</label>
                    <input name="inputProfileUmkm" type="text" class="input-studio" placeholder="Contoh: Biteme Bliss (FnB)" value="<?= htmlspecialchars($user_data['nama_umkm'] ?? ''); ?>">
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nomor WhatsApp</label>
                    <input name="inputProfilePhone" type="tel" class="input-studio" placeholder="081234567890" value="<?= htmlspecialchars($user_data['whatsapp'] ?? ''); ?>">
                  </div>
                </div>
              </div>

              <div class="pt-2">
                <p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-3">3. Alamat Lokasi Usaha</p>
                <div>
                  <textarea name="inputProfileAddress" rows="3" class="input-studio" placeholder="Masukkan alamat lengkap tempat usaha Anda..."><?= htmlspecialchars($user_data['alamat'] ?? ''); ?></textarea>
                </div>
              </div>

              <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <?php if ($has_profile_filled): ?>
                  <button type="button" onclick="toggleProfileMode('kpi')" class="px-5 py-3 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition-all">
                    Batal
                  </button>
                <?php endif; ?>
                <button type="submit" class="px-6 py-3 rounded-xl bg-slate-900 hover:bg-[#E11D48] text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[17px]">save</span>
                  <span>Simpan Perubahan Profil</span>
                </button>
              </div>
            </form>
          <?php endif; ?>
        </div>

        <?php if (!$is_admin_viewer): ?>
          <!-- TAB 2: PESANAN ON PROGRESS -->
          <div id="tabContentOnProgress" class="card-studio space-y-6 hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-4 gap-3">
              <div>
                <h2 class="text-xl font-extrabold text-slate-900">Pesanan Sedang Berjalan (On Progress)</h2>
                <p class="text-xs text-slate-500 mt-0.5">Kelola pembayaran, lihat rincian jasa, atau hubungi tim Marketiva.</p>
              </div>
              <div class="flex items-center gap-2 self-start sm:self-auto">
                <div class="relative">
                  <span class="material-symbols-outlined text-[17px] text-slate-400 absolute left-3 top-1/2 -translate-y-1/2">search</span>
                  <input type="text" onkeyup="filterUserOrders('progress', this.value)" placeholder="Cari pesanan..." class="h-9 pl-9 pr-3 text-xs border border-slate-300 rounded-xl outline-none focus:border-[#E11D48] w-44 bg-white">
                </div>
                <span class="px-3 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold"><?= $count_progress; ?> Aktif</span>
              </div>
            </div>

            <?php if ($count_progress > 0): ?>
              <div class="space-y-4">
                <?php foreach ($orders_progress as $row): ?>
                  <?php
                    $badge_style  = getServiceBadgeStyle($row);
                    $status_raw   = trim($row['status'] ?? 'Menunggu Pembayaran');
                    $st_lower     = strtolower($status_raw);
                    $belum_bayar  = (strpos($st_lower, 'menunggu pembayaran') !== false) || (strpos($st_lower, 'menunggu konfirmasi') !== false);
                    $harga_val    = floatval($row['harga'] ?? 0);
                    $harga_fmt    = 'Rp ' . number_format($harga_val, 0, ',', '.');
                    $kategori_lbl = $row['kategori'] ?? 'Digital Marketing';
                    $order_code   = $row['order_id'] ?? ('#' . $row['id']);
                    $waktu_order  = $row['created_at'] ?? $row['tanggal'] ?? $row['waktu_pesan'] ?? '-';
                    $metode_lbl   = $row['metode_bayar'] ?? $row['metode_pembayaran'] ?? ($belum_bayar ? 'Menunggu Metode / Midtrans' : 'Midtrans / Terkonfirmasi');
                    $pay_url      = 'checkout.php?layanan=' . urlencode($row['nama_layanan'] ?? '')
                                  . '&harga=' . urlencode((string)$harga_val)
                                  . '&kategori=' . urlencode($kategori_lbl)
                                  . '&order_id=' . urlencode($order_code);
                    $search_p     = strtolower(($row['nama_layanan'] ?? '') . ' ' . $kategori_lbl . ' ' . $order_code . ' ' . $status_raw);

                    $detail_payload = json_encode([
                        'order_id'   => $order_code,
                        'layanan'    => $row['nama_layanan'] ?? '-',
                        'kategori'   => $kategori_lbl,
                        'status'     => $status_raw,
                        'harga_fmt'  => $harga_fmt,
                        'metode'     => $metode_lbl,
                        'waktu'      => $waktu_order,
                        'img'        => $row['_img'],
                        'icon'       => $badge_style['icon'],
                        'grad'       => $badge_style['grad'],
                        'is_history' => false,
                        'canceled'   => false,
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

                    $cancel_payload = json_encode([
                        'pk_id'     => intval($row['id']),
                        'order_id'  => $order_code,
                        'layanan'   => $row['nama_layanan'] ?? '-',
                        'harga_fmt' => $harga_fmt,
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                  ?>
                  <div class="progress-order-card rounded-2xl bg-white border border-slate-200 hover:border-slate-300 shadow-sm overflow-hidden transition-all" data-search="<?= htmlspecialchars($search_p); ?>">
                    <!-- Header Bar -->
                    <div class="px-5 py-3 bg-slate-50/90 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                      <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded bg-[#E11D48] text-white text-[10px] font-extrabold uppercase tracking-wide">Marketiva</span>
                        <span class="text-xs font-extrabold text-slate-800"><?= htmlspecialchars($kategori_lbl); ?></span>
                        <span class="text-[11px] font-semibold text-slate-400">• <?= htmlspecialchars($order_code); ?></span>
                      </div>
                      <span class="text-xs font-extrabold uppercase <?= $belum_bayar ? 'text-amber-600' : 'text-blue-600'; ?>">
                        <?= htmlspecialchars($status_raw); ?>
                      </span>
                    </div>

                    <!-- Body Item -->
                    <div class="p-5 flex items-start sm:items-center justify-between gap-4 border-b border-slate-100">
                      <div class="flex items-start sm:items-center gap-4 min-w-0">
                        <?php if (!empty($row['_img'])): ?>
                          <img src="<?= htmlspecialchars($row['_img']); ?>" alt="<?= htmlspecialchars($row['nama_layanan'] ?? 'Layanan'); ?>" class="w-20 h-20 rounded-xl object-cover border border-slate-200 flex-shrink-0">
                        <?php else: ?>
                          <div class="w-20 h-20 rounded-xl bg-gradient-to-br <?= $badge_style['grad']; ?> text-white flex flex-col items-center justify-center flex-shrink-0 shadow-sm">
                            <span class="material-symbols-outlined text-[28px]"><?= $badge_style['icon']; ?></span>
                            <span class="text-[9px] font-bold uppercase mt-0.5 opacity-90">Jasa</span>
                          </div>
                        <?php endif; ?>

                        <div class="min-w-0 space-y-1">
                          <h3 class="text-sm sm:text-base font-extrabold text-slate-900 truncate"><?= htmlspecialchars($row['nama_layanan'] ?? ''); ?></h3>
                          <div class="flex items-center justify-between sm:justify-start gap-4 text-xs text-slate-500">
                            <span>Paket Layanan</span>
                            <span class="font-bold text-slate-600">x1</span>
                          </div>
                          <p class="text-xs font-bold text-slate-500 sm:hidden">
                            Tarif: <span class="text-slate-900 font-extrabold"><?= $harga_fmt; ?></span>
                          </p>
                        </div>
                      </div>

                      <div class="hidden sm:block text-right flex-shrink-0">
                        <p class="text-[11px] text-slate-400 font-semibold">Tarif Layanan</p>
                        <p class="text-sm font-extrabold text-slate-900"><?= $harga_fmt; ?></p>
                      </div>
                    </div>

                    <!-- Ringkasan Total -->
                    <div class="px-5 py-3 bg-rose-50/30 border-b border-slate-100 flex items-center justify-end gap-2">
                      <span class="text-xs text-slate-600 font-medium">Total Pesanan (1 layanan):</span>
                      <span class="text-base font-extrabold text-[#E11D48]"><?= $harga_fmt; ?></span>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="px-5 py-3.5 bg-white flex flex-wrap items-center justify-end gap-2.5">
                      <button type="button" onclick='openOrderDetailModal(<?= $detail_payload; ?>)' class="px-4 py-2 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-all">
                        Detail Pesanan
                      </button>

                      <?php if ($belum_bayar): ?>
                        <button type="button" onclick='openCancelOrderModal(<?= $cancel_payload; ?>)' class="px-4 py-2 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs transition-all">
                          Batalkan
                        </button>

                        <a href="<?= htmlspecialchars($pay_url); ?>" class="px-5 py-2 rounded-xl bg-[#E11D48] hover:bg-[#BE123C] text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5">
                          <span class="material-symbols-outlined text-[16px]">payments</span>
                          <span>Bayar Sekarang</span>
                        </a>
                      <?php else: ?>
                        <a href="https://wa.me/6281244178641" target="_blank" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-all">
                          Hubungi Tim
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-center py-12 px-4 space-y-3 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                <span class="material-symbols-outlined text-[48px] text-slate-300">hourglass_empty</span>
                <p class="text-sm font-bold text-slate-700">Belum ada pesanan aktif saat ini.</p>
                <a href="catalog.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#E11D48] text-white font-bold text-xs">Lihat Katalog Layanan</a>
              </div>
            <?php endif; ?>
          </div>

          <!-- TAB 3: RIWAYAT PESANAN -->
          <div id="tabContentHistory" class="card-studio space-y-6 hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-4 gap-3">
              <div>
                <h2 class="text-xl font-extrabold text-slate-900">Riwayat Pesanan</h2>
                <p class="text-xs text-slate-500 mt-0.5">Daftar pesanan jasa yang telah selesai atau dibatalkan beserta total pembayarannya.</p>
              </div>
              <div class="flex items-center gap-2 self-start sm:self-auto">
                <div class="relative">
                  <span class="material-symbols-outlined text-[17px] text-slate-400 absolute left-3 top-1/2 -translate-y-1/2">search</span>
                  <input type="text" onkeyup="filterUserOrders('history', this.value)" placeholder="Cari riwayat..." class="h-9 pl-9 pr-3 text-xs border border-slate-300 rounded-xl outline-none focus:border-[#E11D48] w-44 bg-white">
                </div>
                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold">Total: <?= $count_history; ?></span>
              </div>
            </div>

            <?php if ($count_history > 0): ?>
              <div class="space-y-4">
                <?php foreach ($orders_history as $h_row): ?>
                  <?php
                    $h_style     = getServiceBadgeStyle($h_row);
                    $h_status    = trim($h_row['status'] ?? 'Selesai');
                    $is_canceled = (strtolower($h_status) === 'dibatalkan');
                    $h_harga     = floatval($h_row['harga'] ?? 0);
                    $h_harga_fmt = 'Rp ' . number_format($h_harga, 0, ',', '.');
                    $h_kategori  = $h_row['kategori'] ?? 'Digital Marketing';
                    $h_code      = $h_row['order_id'] ?? ('#' . $h_row['id']);
                    $h_waktu     = $h_row['created_at'] ?? $h_row['tanggal'] ?? $h_row['waktu_pesan'] ?? '-';
                    $h_metode    = $h_row['metode_bayar'] ?? $h_row['metode_pembayaran'] ?? ($is_canceled ? 'Dibatalkan Sebelum Bayar' : 'Midtrans / Terkonfirmasi');
                    $reorder_url = 'checkout.php?layanan=' . urlencode($h_row['nama_layanan'] ?? '')
                                 . '&harga=' . urlencode((string)$h_harga)
                                 . '&kategori=' . urlencode($h_kategori);
                    $search_h    = strtolower(($h_row['nama_layanan'] ?? '') . ' ' . $h_kategori . ' ' . $h_code . ' ' . $h_status);

                    $h_detail_payload = json_encode([
                        'order_id'   => $h_code,
                        'layanan'    => $h_row['nama_layanan'] ?? '-',
                        'kategori'   => $h_kategori,
                        'status'     => $h_status,
                        'harga_fmt'  => $h_harga_fmt,
                        'metode'     => $h_metode,
                        'waktu'      => $h_waktu,
                        'img'        => $h_row['_img'],
                        'icon'       => $h_style['icon'],
                        'grad'       => $h_style['grad'],
                        'is_history' => true,
                        'canceled'   => $is_canceled,
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                  ?>
                  <div class="history-order-card rounded-2xl bg-white border border-slate-200 hover:border-slate-300 shadow-sm overflow-hidden transition-all" data-search="<?= htmlspecialchars($search_h); ?>">
                    <!-- Header Bar -->
                    <div class="px-5 py-3 bg-slate-50/90 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                      <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded <?= $is_canceled ? 'bg-slate-600' : 'bg-emerald-600'; ?> text-white text-[10px] font-extrabold uppercase tracking-wide">Marketiva</span>
                        <span class="text-xs font-extrabold text-slate-800"><?= htmlspecialchars($h_kategori); ?></span>
                        <span class="text-[11px] font-semibold text-slate-400">• <?= htmlspecialchars($h_code); ?></span>
                      </div>
                      <span class="text-xs font-extrabold uppercase <?= $is_canceled ? 'text-rose-600' : 'text-emerald-600'; ?>">
                        <?= htmlspecialchars($h_status); ?>
                      </span>
                    </div>

                    <!-- Body Item -->
                    <div class="p-5 flex items-start sm:items-center justify-between gap-4 border-b border-slate-100">
                      <div class="flex items-start sm:items-center gap-4 min-w-0">
                        <?php if (!empty($h_row['_img'])): ?>
                          <img src="<?= htmlspecialchars($h_row['_img']); ?>" alt="<?= htmlspecialchars($h_row['nama_layanan'] ?? 'Layanan'); ?>" class="w-20 h-20 rounded-xl object-cover border border-slate-200 flex-shrink-0 <?= $is_canceled ? 'grayscale opacity-75' : ''; ?>">
                        <?php else: ?>
                          <div class="w-20 h-20 rounded-xl bg-gradient-to-br <?= $is_canceled ? 'from-slate-400 to-slate-500' : $h_style['grad']; ?> text-white flex flex-col items-center justify-center flex-shrink-0 shadow-sm">
                            <span class="material-symbols-outlined text-[28px]"><?= $h_style['icon']; ?></span>
                            <span class="text-[9px] font-bold uppercase mt-0.5 opacity-90">Jasa</span>
                          </div>
                        <?php endif; ?>

                        <div class="min-w-0 space-y-1">
                          <h3 class="text-sm sm:text-base font-extrabold text-slate-900 truncate"><?= htmlspecialchars($h_row['nama_layanan'] ?? ''); ?></h3>
                          <div class="flex items-center gap-4 text-xs text-slate-500">
                            <span>Paket Layanan</span>
                            <span class="font-bold text-slate-600">x1</span>
                          </div>
                          <p class="text-xs font-bold text-slate-500 sm:hidden">
                            <?= $is_canceled ? 'Nominal:' : 'Dibayar:'; ?>
                            <span class="<?= $is_canceled ? 'text-slate-500 line-through' : 'text-slate-900'; ?> font-extrabold"><?= $h_harga_fmt; ?></span>
                          </p>
                        </div>
                      </div>

                      <div class="hidden sm:block text-right flex-shrink-0">
                        <p class="text-[11px] text-slate-400 font-semibold"><?= $is_canceled ? 'Nominal Pesanan' : 'Sudah Dibayar'; ?></p>
                        <p class="text-sm font-extrabold <?= $is_canceled ? 'text-slate-400 line-through' : 'text-slate-900'; ?>">
                          <?= $h_harga_fmt; ?>
                        </p>
                      </div>
                    </div>

                    <!-- Ringkasan Total Dibayar -->
                    <div class="px-5 py-3 <?= $is_canceled ? 'bg-slate-50/60' : 'bg-emerald-50/30'; ?> border-b border-slate-100 flex items-center justify-end gap-2">
                      <span class="text-xs text-slate-600 font-medium">
                        <?= $is_canceled ? 'Total Dibatalkan:' : 'Total Dibayar (1 layanan):'; ?>
                      </span>
                      <span class="text-base font-extrabold <?= $is_canceled ? 'text-slate-500' : 'text-emerald-700'; ?>">
                        <?= $h_harga_fmt; ?>
                      </span>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="px-5 py-3.5 bg-white flex flex-wrap items-center justify-end gap-2.5">
                      <button type="button" onclick='openOrderDetailModal(<?= $h_detail_payload; ?>)' class="px-4 py-2 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-all">
                        Detail Pesanan
                      </button>

                      <a href="<?= htmlspecialchars($reorder_url); ?>" class="px-5 py-2 rounded-xl <?= $is_canceled ? 'bg-slate-900 hover:bg-slate-800' : 'bg-[#E11D48] hover:bg-[#BE123C]'; ?> text-white font-bold text-xs shadow-sm transition-all">
                        Pesan Lagi
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-center py-12 px-4 space-y-2 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                <p class="text-xs font-bold text-slate-600">Belum ada riwayat transaksi yang selesai.</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- TAB 4: KEAMANAN & SANDI -->
          <div id="tabContentSecurity" class="card-studio space-y-6 hidden">
            <div class="border-b border-slate-100 pb-4">
              <h2 class="text-xl font-extrabold text-slate-900">Keamanan & Ubah Kata Sandi</h2>
            </div>
            <form method="POST" action="user-dashboard.php" class="space-y-4 max-w-xl">
              <input type="hidden" name="update_password" value="1">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Kata Sandi Lama</label>
                <input type="password" name="currentPassword" class="input-studio" required>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Kata Sandi Baru</label>
                <input type="password" name="newPassword" class="input-studio" required>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Konfirmasi Sandi Baru</label>
                <input type="password" name="confirmPassword" class="input-studio" required>
              </div>
              <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold text-xs">Perbarui Kata Sandi</button>
            </form>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <?php if (!$is_admin_viewer): ?>
    <!-- MODAL RINCIAN PESANAN -->
    <div id="orderDetailModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4" onclick="closeOrderDetailModal(event)">
      <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden" onclick="event.stopPropagation()">
        <!-- Top Bar -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <button type="button" onclick="closeOrderDetailModal()" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-600">
              <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </button>
            <h3 class="text-base font-extrabold text-slate-900">Rincian Pesanan</h3>
          </div>
          <span id="modalDetailStatus" class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
            SELESAI
          </span>
        </div>

        <div class="p-6 space-y-5 max-h-[80vh] overflow-y-auto">
          <!-- Ringkasan Layanan -->
          <div class="flex items-center gap-4 pb-4 border-b border-slate-100">
            <img id="modalDetailImg" src="" alt="Layanan" class="w-16 h-16 rounded-xl object-cover border border-slate-200 hidden flex-shrink-0">
            <div id="modalDetailIconBox" class="w-16 h-16 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 text-white flex items-center justify-center flex-shrink-0 shadow-sm">
              <span id="modalDetailIcon" class="material-symbols-outlined text-[28px]">design_services</span>
            </div>
            <div class="min-w-0 flex-1">
              <p id="modalDetailKategori" class="text-[11px] font-bold text-[#E11D48] uppercase">Kategori</p>
              <h4 id="modalDetailLayanan" class="text-sm font-extrabold text-slate-900 truncate">Nama Layanan</h4>
              <p class="text-xs text-slate-500 mt-0.5">Jumlah: <strong class="text-slate-700">1 Paket Jasa</strong></p>
            </div>
            <div class="text-right">
              <p class="text-[10px] text-slate-400 font-semibold">Total</p>
              <p id="modalDetailHarga" class="text-sm font-extrabold text-slate-900">Rp 0</p>
            </div>
          </div>

          <!-- Bantuan & Kontak Tim -->
          <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-3">
            <p class="text-xs font-extrabold text-slate-800">Butuh Bantuan Terkait Pesanan?</p>
            <a href="https://wa.me/6281244178641" target="_blank" class="flex items-center justify-between py-1.5 text-xs font-bold text-slate-700 hover:text-emerald-600 transition-colors">
              <span class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[18px] text-emerald-600">support_agent</span>
                <span>Hubungi Tim Marketiva (WhatsApp)</span>
              </span>
              <span class="material-symbols-outlined text-[18px] text-slate-400">chevron_right</span>
            </a>
          </div>

          <!-- Informasi Pesanan -->
          <div class="space-y-2.5 text-xs border-t border-slate-100 pt-4">
            <div class="flex items-center justify-between gap-2">
              <span class="text-slate-500">No. Pesanan</span>
              <div class="flex items-center gap-2">
                <span id="modalDetailOrderId" class="font-extrabold text-slate-900">-</span>
                <button type="button" id="btnCopyOrderCode" onclick="copyOrderCode()" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold transition-all">
                  Salin
                </button>
              </div>
            </div>

            <div class="flex items-center justify-between gap-2">
              <span class="text-slate-500">Metode Pembayaran</span>
              <span id="modalDetailMetode" class="font-bold text-slate-800 text-right">-</span>
            </div>

            <div class="flex items-center justify-between gap-2">
              <span class="text-slate-500">Waktu Tercatat</span>
              <span id="modalDetailWaktu" class="font-semibold text-slate-700 text-right">-</span>
            </div>
          </div>
        </div>

        <!-- Footer Tombol -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
          <button type="button" onclick="closeOrderDetailModal()" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-all">
            Tutup Rincian
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL KONFIRMASI BATALKAN PESANAN -->
    <div id="cancelOrderModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4" onclick="closeCancelOrderModal(event)">
      <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center">
              <span class="material-symbols-outlined text-[20px]">cancel</span>
            </div>
            <div>
              <h3 class="text-base font-extrabold text-slate-900">Batalkan Pesanan?</h3>
              <p class="text-[11px] text-slate-500">Konfirmasi pembatalan transaksi belum dibayar</p>
            </div>
          </div>
          <button type="button" onclick="closeCancelOrderModal()" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
            <span class="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <div class="p-6 space-y-4">
          <p class="text-xs text-slate-600 leading-relaxed">
            Pesanan ini akan dibatalkan dan dipindahkan ke tab <strong class="text-slate-800">Riwayat Pesanan</strong> dengan status <strong class="text-rose-600">Dibatalkan</strong>.
          </p>

          <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2 text-xs">
            <div class="flex justify-between gap-2">
              <span class="text-slate-500">No. Pesanan</span>
              <span id="cancelModalOrderCode" class="font-extrabold text-slate-900">-</span>
            </div>
            <div class="flex justify-between gap-2">
              <span class="text-slate-500">Layanan</span>
              <span id="cancelModalServiceName" class="font-bold text-slate-800 text-right">-</span>
            </div>
            <div class="flex justify-between gap-2 pt-2 border-t border-slate-200">
              <span class="text-slate-500">Total Tagihan</span>
              <span id="cancelModalPrice" class="font-extrabold text-[#E11D48]">-</span>
            </div>
          </div>
        </div>

        <form method="POST" action="user-dashboard.php" class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
          <input type="hidden" name="cancel_order" value="1">
          <input type="hidden" name="order_pk_id" id="cancelModalOrderPkId" value="">
          <button type="button" onclick="closeCancelOrderModal()" class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs transition-all">
            Kembali
          </button>
          <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#E11D48] hover:bg-[#BE123C] text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px]">delete_forever</span>
            <span>Ya, Batalkan Pesanan</span>
          </button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400">
    © 2026 Marketiva Digital Agency. Member Portal Dashboard.
  </footer>

  <script>
    function toggleProfileMode(mode) {
      const kpiView  = document.getElementById('profileKpiView');
      const editForm = document.getElementById('profileEditForm');
      if (!kpiView || !editForm) return;

      if (mode === 'edit') {
        kpiView.classList.add('hidden');
        editForm.classList.remove('hidden');
      } else {
        editForm.classList.add('hidden');
        kpiView.classList.remove('hidden');
      }
    }

    function filterUserOrders(type, query) {
      const q = String(query || '').toLowerCase().trim();
      const selector = type === 'progress' ? '.progress-order-card' : '.history-order-card';
      document.querySelectorAll(selector).forEach(card => {
        const hay = card.getAttribute('data-search') || '';
        card.classList.toggle('hidden', Boolean(q) && !hay.includes(q));
      });
    }

    function switchTab(tabName) {
      const elProfile    = document.getElementById('tabContentProfile');
      const elOnProgress = document.getElementById('tabContentOnProgress');
      const elHistory    = document.getElementById('tabContentHistory');
      const elSecurity   = document.getElementById('tabContentSecurity');

      if (elProfile) elProfile.classList.add('hidden');
      if (elOnProgress) elOnProgress.classList.add('hidden');
      if (elHistory) elHistory.classList.add('hidden');
      if (elSecurity) elSecurity.classList.add('hidden');

      const btnProfile    = document.getElementById('btnTabProfile');
      const btnOnProgress = document.getElementById('btnTabOnProgress');
      const btnHistory    = document.getElementById('btnTabHistory');
      const btnSecurity   = document.getElementById('btnTabSecurity');

      if (btnProfile) btnProfile.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      if (btnOnProgress) btnOnProgress.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      if (btnHistory) btnHistory.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      if (btnSecurity) btnSecurity.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";

      if (tabName === 'profile' && elProfile) {
        elProfile.classList.remove('hidden');
        if (btnProfile) btnProfile.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-rose-50 text-[#E11D48] transition-all";
      } else if (tabName === 'onprogress' && elOnProgress) {
        elOnProgress.classList.remove('hidden');
        if (btnOnProgress) btnOnProgress.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-amber-50 text-amber-800 transition-all";
      } else if (tabName === 'history' && elHistory) {
        elHistory.classList.remove('hidden');
        if (btnHistory) btnHistory.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-slate-100 text-slate-900 transition-all";
      } else if (tabName === 'security' && elSecurity) {
        elSecurity.classList.remove('hidden');
        if (btnSecurity) btnSecurity.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      }
    }

    function openOrderDetailModal(data) {
      const modal   = document.getElementById('orderDetailModal');
      const imgEl   = document.getElementById('modalDetailImg');
      const iconBox = document.getElementById('modalDetailIconBox');
      const iconEl  = document.getElementById('modalDetailIcon');

      document.getElementById('modalDetailStatus').textContent   = data.status || '-';
      document.getElementById('modalDetailKategori').textContent = data.kategori || 'Digital Marketing';
      document.getElementById('modalDetailLayanan').textContent  = data.layanan || '-';
      document.getElementById('modalDetailHarga').textContent    = data.harga_fmt || 'Rp 0';
      document.getElementById('modalDetailOrderId').textContent  = data.order_id || '-';
      document.getElementById('modalDetailMetode').textContent   = data.metode || '-';
      document.getElementById('modalDetailWaktu').textContent    = data.waktu || '-';

      if (data.img) {
        imgEl.src = data.img;
        imgEl.alt = data.layanan || 'Layanan';
        imgEl.classList.remove('hidden');
        iconBox.classList.add('hidden');
      } else {
        imgEl.classList.add('hidden');
        iconBox.className = 'w-16 h-16 rounded-xl bg-gradient-to-br ' + (data.grad || 'from-rose-500 to-pink-600') + ' text-white flex items-center justify-center flex-shrink-0 shadow-sm';
        iconEl.textContent = data.icon || 'design_services';
        iconBox.classList.remove('hidden');
      }

      modal.classList.remove('hidden');
    }

    function closeOrderDetailModal(event) {
      if (!event || event.target.id === 'orderDetailModal') {
        document.getElementById('orderDetailModal').classList.add('hidden');
      }
    }

    function openCancelOrderModal(data) {
      document.getElementById('cancelModalOrderPkId').value         = data.pk_id || '';
      document.getElementById('cancelModalOrderCode').textContent   = data.order_id || '-';
      document.getElementById('cancelModalServiceName').textContent = data.layanan || '-';
      document.getElementById('cancelModalPrice').textContent       = data.harga_fmt || '-';
      document.getElementById('cancelOrderModal').classList.remove('hidden');
    }

    function closeCancelOrderModal(event) {
      if (!event || event.target.id === 'cancelOrderModal') {
        document.getElementById('cancelOrderModal').classList.add('hidden');
      }
    }

    function copyOrderCode() {
      const code = document.getElementById('modalDetailOrderId').textContent.trim();
      const btn  = document.getElementById('btnCopyOrderCode');
      if (code && code !== '-') {
        navigator.clipboard.writeText(code);
        btn.textContent = 'Tersalin ✓';
        btn.className = 'px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-700 text-[10px] font-bold transition-all';
        setTimeout(() => {
          btn.textContent = 'Salin';
          btn.className = 'px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold transition-all';
        }, 1800);
      }
    }

    window.addEventListener('DOMContentLoaded', function() {
      switchTab(<?= json_encode($active_tab); ?>);
    });
  </script>
</body>
</html>