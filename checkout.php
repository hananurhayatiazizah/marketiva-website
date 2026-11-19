<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// =========================================================================
// KONFIGURASI MIDTRANS SANDBOX (Ganti dengan kunci dari Dashboard Sandbox)
// =========================================================================
define('MIDTRANS_IS_PRODUCTION', false);
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-Yi6QqSqeYZAUC_Ygh08c5~DO');
define('MIDTRANS_CLIENT_KEY', 'Mid-client-T5fLVg2Fn7oWy0dN');
define('MIDTRANS_SNAP_API_URL', MIDTRANS_IS_PRODUCTION
    ? 'https://app.midtrans.com/snap/v1/transactions'
    : 'https://app.sandbox.midtrans.com/snap/v1/transactions');
define('MIDTRANS_SNAP_JS_URL', MIDTRANS_IS_PRODUCTION
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js');

// =========================================================================
// ENDPOINT AJAX: UPDATE STATUS SETELAH PEMBAYARAN SANDBOX BERHASIL
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_midtrans_paid'])) {
    header('Content-Type: application/json');
    $paid_order_id = trim($_POST['order_id'] ?? '');
    $new_status    = trim($_POST['status'] ?? 'Diproses');

    if (!empty($paid_order_id)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->bind_param("ss", $new_status, $paid_order_id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok, 'status' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order ID kosong']);
    }
    exit;
}

$nama_layanan    = $_GET['layanan'] ?? $_POST['nama_layanan'] ?? '';
$harga           = $_GET['harga'] ?? $_POST['harga'] ?? 0;
$kategori        = $_GET['kategori'] ?? $_POST['kategori'] ?? 'Digital Marketing';
$resume_order_id = trim($_GET['order_id'] ?? $_POST['resume_order_id'] ?? '');

// Ambil data default user bila sudah login
$default_nama   = $_SESSION['user'] ?? '';
$default_usaha  = '';
$default_wa     = '';
$session_uid    = $_SESSION['user_id'] ?? null;
$session_email  = $_SESSION['email'] ?? '';

if (isset($_SESSION['user'])) {
    if ($session_uid) {
        $u_stmt = $conn->prepare("SELECT nama_lengkap, nama_umkm, whatsapp FROM users WHERE id = ? LIMIT 1");
        $u_stmt->bind_param("i", $session_uid);
    } else {
        $u_stmt = $conn->prepare("SELECT nama_lengkap, nama_umkm, whatsapp FROM users WHERE email = ? LIMIT 1");
        $u_stmt->bind_param("s", $session_email);
    }
    if ($u_stmt) {
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($u_row = $u_res->fetch_assoc()) {
            $default_nama  = $u_row['nama_lengkap'] ?: $default_nama;
            $default_usaha = $u_row['nama_umkm'] ?? '';
            $default_wa    = $u_row['whatsapp'] ?? '';
        }
        $u_stmt->close();
    }
}

// Jika melanjutkan pesanan yang sudah ada, sinkronkan info layanan jika kosong
$existing_order = null;
if (!empty($resume_order_id)) {
    $ex_stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
    $ex_stmt->bind_param("s", $resume_order_id);
    $ex_stmt->execute();
    $ex_res = $ex_stmt->get_result();
    $existing_order = $ex_res ? $ex_res->fetch_assoc() : null;
    $ex_stmt->close();

    if ($existing_order) {
        $st_existing = strtolower(trim($existing_order['status'] ?? ''));
        $masih_bisa_bayar = (strpos($st_existing, 'menunggu pembayaran') !== false) || (strpos($st_existing, 'menunggu konfirmasi') !== false);
        if ($masih_bisa_bayar) {
            if (empty($nama_layanan) && !empty($existing_order['nama_layanan'])) {
                $nama_layanan = $existing_order['nama_layanan'];
            }
            if ((empty($harga) || floatval($harga) <= 0) && isset($existing_order['harga'])) {
                $harga = $existing_order['harga'];
            }
            if (!empty($existing_order['kategori'])) {
                $kategori = $existing_order['kategori'];
            }
        } else {
            $resume_order_id = '';
            $existing_order  = null;
        }
    } else {
        $resume_order_id = '';
    }
}

$success_msg       = false;
$error_midtrans    = '';
$snap_token        = '';
$order_id_tercatat = '';
$metode_terpilih   = '';
$layanan_tercatat  = '';
$harga_tercatat    = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_process_payment'])) {
    $nama_lengkap    = trim($_POST['nama_lengkap'] ?? '');
    $nama_usaha      = trim($_POST['nama_usaha'] ?? '');
    $whatsapp        = trim($_POST['whatsapp'] ?? '');
    $layanan_dipesan = trim($_POST['nama_layanan'] ?? '');
    $harga_pesan     = floatval($_POST['harga'] ?? 0);
    $kategori_pesan  = trim($_POST['kategori'] ?? 'Digital Marketing');
    $metode_bayar    = trim($_POST['metode_bayar'] ?? 'Transfer Manual');
    $post_resume_id  = trim($_POST['resume_order_id'] ?? '');

    $user_id    = $_SESSION['user_id'] ?? 0;
    $user_email = $_SESSION['email'] ?? ($whatsapp . '@marketiva.com');

    $is_midtrans = (stripos($metode_bayar, 'Midtrans') !== false);
    $status_awal = $is_midtrans ? 'Menunggu Pembayaran' : 'Menunggu Konfirmasi';

    $is_updating_existing = false;
    $db_order_id          = 'ORD-' . strtoupper(uniqid());

    if (!empty($post_resume_id)) {
        $chk_stmt = $conn->prepare("SELECT order_id, status FROM orders WHERE order_id = ? LIMIT 1");
        $chk_stmt->bind_param("s", $post_resume_id);
        $chk_stmt->execute();
        $chk_res = $chk_stmt->get_result();
        $chk_row = $chk_res ? $chk_res->fetch_assoc() : null;
        $chk_stmt->close();

        if ($chk_row) {
            $st_chk = strtolower(trim($chk_row['status'] ?? ''));
            if (strpos($st_chk, 'menunggu pembayaran') !== false || strpos($st_chk, 'menunggu konfirmasi') !== false) {
                $is_updating_existing = true;
                $db_order_id          = $chk_row['order_id'];
            }
        }
    }

    if (!empty($nama_lengkap) && !empty($whatsapp) && !empty($layanan_dipesan)) {
        if ($is_updating_existing) {
            $stmt = $conn->prepare("UPDATE orders SET user_email = ?, nama_layanan = ?, kategori = ?, harga = ?, status = ?, user_id = ? WHERE order_id = ?");
            $stmt->bind_param("sssdsis", $user_email, $layanan_dipesan, $kategori_pesan, $harga_pesan, $status_awal, $user_id, $db_order_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO orders (order_id, user_email, nama_layanan, kategori, harga, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdsi", $db_order_id, $user_email, $layanan_dipesan, $kategori_pesan, $harga_pesan, $status_awal, $user_id);
        }

        if ($stmt->execute()) {
            $order_id_tercatat = $db_order_id;
            $metode_terpilih   = $metode_bayar;
            $layanan_tercatat  = $layanan_dipesan;
            $harga_tercatat    = $harga_pesan;

            if ($is_midtrans) {
                $gross_amount = (int) round($harga_pesan);
                if ($gross_amount < 1) {
                    $gross_amount = 10000;
                }

                // Gunakan ID transaksi Snap unik jika melanjutkan pesanan lama agar Midtrans tidak menolak order_id yang sudah pernah dipakai
                $snap_order_id = $is_updating_existing
                    ? ($db_order_id . '-R' . strtoupper(substr(uniqid(), -4)))
                    : $db_order_id;

                $payload = [
                    'transaction_details' => [
                        'order_id'     => $snap_order_id,
                        'gross_amount' => $gross_amount,
                    ],
                    'item_details' => [
                        [
                            'id'       => 'SRV-01',
                            'price'    => $gross_amount,
                            'quantity' => 1,
                            'name'     => mb_substr($layanan_dipesan, 0, 50),
                            'category' => mb_substr($kategori_pesan, 0, 30),
                        ]
                    ],
                    'customer_details' => [
                        'first_name' => $nama_lengkap,
                        'last_name'  => $nama_usaha ? '(' . $nama_usaha . ')' : '',
                        'email'      => filter_var($user_email, FILTER_VALIDATE_EMAIL) ? $user_email : 'customer@marketiva.com',
                        'phone'      => $whatsapp,
                    ]
                ];

                $ch = curl_init(MIDTRANS_SNAP_API_URL);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => [
                        'Accept: application/json',
                        'Content-Type: application/json',
                        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
                    ],
                    CURLOPT_POSTFIELDS     => json_encode($payload),
                    CURLOPT_TIMEOUT        => 30,
                ]);

                $response  = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_err  = curl_error($ch);
                curl_close($ch);

                $res_data = json_decode($response, true);

                if ($http_code === 201 && !empty($res_data['token'])) {
                    $snap_token  = $res_data['token'];
                    $success_msg = true;
                } else {
                    $detail_err = $res_data['error_messages'][0] ?? $curl_err ?? 'Gagal terhubung ke Midtrans Sandbox.';
                    $error_midtrans = 'Pesanan tersimpan (' . $db_order_id . '), tetapi token Midtrans gagal dibuat: ' . $detail_err;
                }
            } else {
                $success_msg = true;
            }
        }
        $stmt->close();
    }
}

$is_logged_in   = isset($_SESSION['user']);
$user_name      = trim((string)($_SESSION['user'] ?? ''));
$user_role      = $_SESSION['role'] ?? 'user';
$user_email_hdr = $_SESSION['email'] ?? '';
$is_admin_hdr   = ($user_role === 'admin');
$dashboard_link = $is_admin_hdr ? 'admin-dashboard.php' : 'user-dashboard.php';
$role_label_hdr = $is_admin_hdr ? 'Administrator' : 'Member UMKM';
$avatar_char    = ($is_logged_in && $user_name !== '') ? strtoupper(mb_substr($user_name, 0, 1)) : 'M';
$harga_angka    = is_numeric($harga) ? floatval($harga) : 0;
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout & Pembayaran - Marketiva</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script type="text/javascript" src="<?= htmlspecialchars(MIDTRANS_SNAP_JS_URL); ?>" data-client-key="<?= htmlspecialchars(MIDTRANS_CLIENT_KEY); ?>"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #FAF9F6;
      color: #131b2e;
    }
    .card-studio {
      background-color: #FFFFFF;
      border-radius: 1.5rem;
      border: 1.5px solid #F1F5F9;
      box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.04);
    }
    .input-studio {
      height: 46px;
      border-radius: 0.75rem;
      border: 1.5px solid #CBD5E1;
      padding: 0 1rem;
      width: 100%;
      font-size: 0.875rem;
      background-color: #FFFFFF;
      color: #1E293B;
      transition: all 0.2s;
    }
    .input-studio:focus {
      outline: none;
      border-color: #E11D48;
      box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.1);
    }
    textarea.input-studio {
      height: auto;
      padding-top: 0.75rem;
      padding-bottom: 0.75rem;
    }
    .payment-option:has(input:checked) {
      border-color: #E11D48;
      background-color: #FFF1F2;
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
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between antialiased">

  <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200/70">
    <div class="max-w-[1240px] mx-auto px-6 h-20 flex items-center justify-between gap-4">
      <a href="index.php" class="font-extrabold text-2xl tracking-tight">
        <span class="text-[#F43F5E]">Mar</span><span class="text-[#3B82F6]">ke</span><span class="text-[#10B981]">ti</span><span class="text-[#F59E0B]">va</span>
      </a>

      <nav class="hidden md:flex items-center gap-8 text-xs font-bold text-slate-600">
        <a href="index.php#beranda" class="hover:text-[#E11D48] transition-colors">Beranda</a>
        <a href="catalog.php" class="hover:text-[#E11D48] transition-colors">Katalog Layanan</a>
        <a href="article.php" class="hover:text-[#E11D48] transition-colors">Artikel</a>
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
          <a href="login.php" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px]">login</span>
            <span>Masuk / Daftar</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="max-w-[1240px] mx-auto px-6 py-10 w-full flex-1">
    <div class="mb-6 flex items-center gap-2 text-xs font-semibold text-slate-500">
      <a href="catalog.php" class="hover:text-[#E11D48] flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Katalog Layanan
      </a>
      <span>/</span>
      <span class="text-slate-900 font-bold">Checkout Pesanan</span>
    </div>

    <?php if (!empty($error_midtrans)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">
        <?= htmlspecialchars($error_midtrans); ?>
      </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
      <div class="card-studio p-8 md:p-10 max-w-xl mx-auto space-y-6">
        <div class="flex items-center gap-4">
          <div id="statusIcon" class="w-14 h-14 rounded-2xl <?= !empty($snap_token) ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'; ?> flex items-center justify-center text-2xl font-extrabold flex-shrink-0">
            <?= !empty($snap_token) ? '⏳' : '✓'; ?>
          </div>
          <div>
            <span id="statusBadge" class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-extrabold uppercase">
              <?= !empty($snap_token) ? 'Menunggu Pembayaran Sandbox' : 'Menunggu Konfirmasi'; ?>
            </span>
            <h2 id="statusTitle" class="text-xl font-extrabold text-slate-900 mt-1">
              <?= !empty($snap_token) ? 'Selesaikan Pembayaran Midtrans' : 'Pesanan Berhasil Dicatat'; ?>
            </h2>
          </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2.5 text-xs">
          <div class="flex justify-between">
            <span class="text-slate-500">ID Pesanan</span>
            <span class="font-extrabold text-slate-900"><?= htmlspecialchars($order_id_tercatat); ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Layanan</span>
            <span class="font-bold text-slate-800"><?= htmlspecialchars($layanan_tercatat); ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Metode Pembayaran</span>
            <span class="font-bold text-slate-800"><?= htmlspecialchars($metode_terpilih); ?></span>
          </div>
          <div class="flex justify-between pt-2 border-t border-slate-200">
            <span class="font-bold text-slate-700">Total Tagihan</span>
            <span class="font-extrabold text-emerald-600 text-sm">Rp <?= number_format($harga_tercatat, 0, ',', '.'); ?></span>
          </div>
        </div>

        <?php if (!empty($snap_token)): ?>
          <button type="button" id="btnPayMidtrans" class="w-full py-3.5 rounded-xl bg-[#E11D48] hover:bg-[#BE123C] text-white font-bold text-xs shadow-lg transition-all flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-[18px]">payments</span>
            <span>Buka Popup Pembayaran Midtrans Sandbox</span>
          </button>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <a href="<?= htmlspecialchars($dashboard_link); ?>" class="w-full py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs text-center transition-all">
            Lihat di Dashboard Saya
          </a>
          <a href="catalog.php" class="w-full py-3.5 rounded-xl bg-white border border-slate-200 hover:border-slate-300 text-slate-700 font-bold text-xs text-center transition-all">
            Pesan Layanan Lain
          </a>
        </div>
      </div>

      <?php if (!empty($snap_token)): ?>
      <script>
        const snapToken = <?= json_encode($snap_token); ?>;
        const orderId   = <?= json_encode($order_id_tercatat); ?>;

        function markOrderPaid(statusLabel) {
          const formData = new URLSearchParams();
          formData.append('action_midtrans_paid', '1');
          formData.append('order_id', orderId);
          formData.append('status', statusLabel);

          fetch('checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
          })
          .then(res => res.json())
          .then(() => {
            document.getElementById('statusIcon').textContent = '✓';
            document.getElementById('statusIcon').className = 'w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl font-extrabold flex-shrink-0';
            document.getElementById('statusBadge').textContent = statusLabel;
            document.getElementById('statusBadge').className = 'px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold uppercase';
            document.getElementById('statusTitle').textContent = 'Pembayaran Sandbox Berhasil!';
            const payBtn = document.getElementById('btnPayMidtrans');
            if (payBtn) payBtn.style.display = 'none';
          });
        }

        function triggerSnapPay() {
          if (typeof window.snap === 'undefined') {
            alert('Midtrans Snap JS belum termuat. Periksa koneksi internet dan Client Key Sandbox.');
            return;
          }
          window.snap.pay(snapToken, {
            onSuccess: function(result) {
              markOrderPaid('Diproses');
            },
            onPending: function(result) {
              document.getElementById('statusBadge').textContent = 'Menunggu Pembayaran';
              document.getElementById('statusTitle').textContent = 'Transaksi Pending di Sandbox';
            },
            onError: function(result) {
              alert('Terjadi kesalahan saat simulasi pembayaran Midtrans.');
            },
            onClose: function() {
              console.log('Popup Midtrans ditutup sebelum pembayaran selesai.');
            }
          });
        }

        document.getElementById('btnPayMidtrans').addEventListener('click', triggerSnapPay);
        window.addEventListener('DOMContentLoaded', triggerSnapPay);
      </script>
      <?php endif; ?>

    <?php else: ?>
      <?php if (!empty($resume_order_id)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold flex items-center gap-2.5">
          <span class="material-symbols-outlined text-[18px] text-amber-700">info</span>
          <span>Melanjutkan pembayaran untuk pesanan <strong class="font-extrabold"><?= htmlspecialchars($resume_order_id); ?></strong> (tidak akan membuat pesanan baru yang menumpuk).</span>
        </div>
      <?php endif; ?>

      <form method="POST" action="checkout.php" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <input type="hidden" name="action_process_payment" value="1">
        <input type="hidden" name="resume_order_id" value="<?= htmlspecialchars($resume_order_id); ?>">
        <input type="hidden" name="nama_layanan" value="<?= htmlspecialchars($nama_layanan); ?>">
        <input type="hidden" name="harga" value="<?= htmlspecialchars($harga_angka); ?>">
        <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori); ?>">

        <div class="lg:col-span-7 space-y-6">
          <div class="card-studio p-6 md:p-8 space-y-5">
            <div class="border-b border-slate-100 pb-4">
              <h2 class="text-lg font-extrabold text-slate-900">1. Informasi Pemesan & Usaha</h2>
              <p class="text-xs text-slate-500 mt-0.5">Lengkapi data kontak agar tim Marketiva dapat menghubungi Anda.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" class="input-studio" placeholder="Nama Anda" value="<?= htmlspecialchars($default_nama); ?>" required>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nama Usaha / UMKM</label>
                <input type="text" name="nama_usaha" class="input-studio" placeholder="Contoh: Kopi Senja" value="<?= htmlspecialchars($default_usaha); ?>" required>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nomor WhatsApp Aktif</label>
              <input type="text" name="whatsapp" class="input-studio" placeholder="08123456789" value="<?= htmlspecialchars($default_wa); ?>" required>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Catatan Tambahan (Opsional)</label>
              <textarea name="catatan" rows="3" class="input-studio" placeholder="Tuliskan target akun, referensi, atau permintaan khusus..."></textarea>
            </div>
          </div>

          <div class="card-studio p-6 md:p-8 space-y-5">
            <div class="border-b border-slate-100 pb-4">
              <h2 class="text-lg font-extrabold text-slate-900">2. Metode Pembayaran</h2>
              <p class="text-xs text-slate-500 mt-0.5">Pilih metode pembayaran yang paling sesuai untuk transaksi Anda.</p>
            </div>

            <div class="space-y-3.5">
              <label class="payment-option block p-4 rounded-2xl border-2 border-slate-200 cursor-pointer transition-all">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-start gap-3">
                    <input type="radio" name="metode_bayar" value="Midtrans Payment Gateway (Sandbox)" class="mt-1 accent-[#E11D48]" checked required>
                    <div>
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-extrabold text-slate-900">Midtrans Payment Gateway</span>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">Sandbox Mode Aktif</span>
                      </div>
                      <p class="text-xs text-slate-500 mt-1">Simulasi pembayaran otomatis via Midtrans Snap (Virtual Account, QRIS, GoPay, dll).</p>
                      <div class="flex flex-wrap gap-1.5 mt-2.5">
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">QRIS</span>
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">BCA VA</span>
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">Mandiri VA</span>
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">BRI / BNI</span>
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">GoPay / ShopeePay</span>
                      </div>
                    </div>
                  </div>
                </div>
              </label>

              <label class="payment-option block p-4 rounded-2xl border-2 border-slate-200 cursor-pointer transition-all">
                <div class="flex items-start gap-3">
                  <input type="radio" name="metode_bayar" value="Transfer BSI (7368562097)" class="mt-1 accent-[#E11D48]">
                  <div>
                    <span class="text-sm font-extrabold text-slate-900">Transfer Manual Bank (Konfirmasi Admin)</span>
                    <p class="text-xs text-slate-600 mt-1">Transfer BSI <span class="font-extrabold text-slate-900">7368562097</span> a.n. Marketiva Digital Agency. Pesanan tercatat dengan status Menunggu Konfirmasi.</p>
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <div class="lg:col-span-5 card-studio p-6 md:p-8 space-y-6 lg:sticky lg:top-28">
          <div class="border-b border-slate-100 pb-4">
            <h3 class="text-base font-extrabold text-slate-900">Rincian Pesanan</h3>
            <p class="text-xs text-slate-500 mt-0.5">Periksa kembali paket layanan sebelum membuat pesanan.</p>
          </div>

          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-1.5">
            <span class="inline-block px-2.5 py-0.5 rounded-full bg-rose-50 text-[#E11D48] border border-rose-200 text-[10px] font-bold">
              <?= htmlspecialchars($kategori ?: 'Digital Marketing'); ?>
            </span>
            <p class="text-sm font-extrabold text-slate-900">
              <?= htmlspecialchars($nama_layanan ?: 'Belum ada layanan dipilih'); ?>
            </p>
            <?php if (!empty($resume_order_id)): ?>
              <p class="text-[11px] font-bold text-slate-500">ID Pesanan: <span class="text-slate-800"><?= htmlspecialchars($resume_order_id); ?></span></p>
            <?php endif; ?>
          </div>

          <div class="space-y-3 text-xs border-b border-slate-200 pb-4">
            <div class="flex justify-between text-slate-600">
              <span>Harga Layanan</span>
              <span class="font-bold text-slate-800">Rp <?= number_format($harga_angka, 0, ',', '.'); ?></span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Biaya Admin</span>
              <span class="font-bold text-emerald-600">Gratis Promo</span>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-700 uppercase">Total Bayar</span>
            <span class="text-xl font-extrabold text-emerald-600">Rp <?= number_format($harga_angka, 0, ',', '.'); ?></span>
          </div>

          <button type="submit" class="w-full py-4 rounded-xl bg-[#E11D48] hover:bg-[#BE123C] text-white font-bold text-xs shadow-lg transition-all flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            <span><?= !empty($resume_order_id) ? 'Lanjutkan Pembayaran Pesanan' : 'Konfirmasi & Lanjut Bayar'; ?></span>
          </button>

          <p class="text-[11px] text-slate-500 text-center leading-relaxed">
            Setelah dikirim, pesanan otomatis tersimpan ke database dan popup pembayaran Midtrans Sandbox akan muncul.
          </p>
        </div>
      </form>
    <?php endif; ?>
  </main>

  <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400 mt-12">
    © 2026 Marketiva Digital Agency. All Rights Reserved.
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