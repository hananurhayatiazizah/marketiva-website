<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

$nama_layanan = $_GET['layanan'] ?? $_POST['nama_layanan'] ?? '';
$harga        = $_GET['harga'] ?? $_POST['harga'] ?? 0;
$kategori     = $_GET['kategori'] ?? $_POST['kategori'] ?? 'Digital Marketing';

$success_msg = false;
$order_id_tercatat = '';
$metode_terpilih = '';
$layanan_tercatat = '';
$harga_tercatat = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_process_payment'])) {
    $nama_lengkap    = trim($_POST['nama_lengkap'] ?? '');
    $nama_usaha      = trim($_POST['nama_usaha'] ?? '');
    $whatsapp        = trim($_POST['whatsapp'] ?? '');
    $layanan_dipesan = trim($_POST['nama_layanan'] ?? '');
    $harga_pesan     = floatval($_POST['harga'] ?? 0);
    $kategori_pesan  = trim($_POST['kategori'] ?? 'Digital Marketing');
    $metode_bayar    = trim($_POST['metode_bayar'] ?? 'Transfer Manual');

    $order_id        = 'ORD-' . strtoupper(uniqid());
    $status_awal     = 'Menunggu Konfirmasi';
    $user_id         = $_SESSION['user_id'] ?? 0;
    $user_email      = $_SESSION['email'] ?? ($whatsapp . '@marketiva.com');

    if (!empty($nama_lengkap) && !empty($whatsapp) && !empty($layanan_dipesan)) {
        $stmt = $conn->prepare("INSERT INTO orders (order_id, user_email, nama_layanan, kategori, harga, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdsi", $order_id, $user_email, $layanan_dipesan, $kategori_pesan, $harga_pesan, $status_awal, $user_id);

        if ($stmt->execute()) {
            $success_msg = true;
            $order_id_tercatat = $order_id;
            $metode_terpilih = $metode_bayar;
            $layanan_tercatat = $layanan_dipesan;
            $harga_tercatat = $harga_pesan;
        }
        $stmt->close();
    }
}

$is_logged_in   = isset($_SESSION['user']);
$user_name      = $_SESSION['user'] ?? '';
$user_role      = $_SESSION['role'] ?? 'user';
$dashboard_link = ($user_role === 'admin') ? 'admin-dashboard.php' : 'user-dashboard.php';
$harga_angka    = is_numeric($harga) ? floatval($harga) : 0;
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout & Pembayaran - Marketiva</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between antialiased">

  <!-- HEADER NAVBAR -->
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
          <a href="<?= htmlspecialchars($dashboard_link); ?>" class="flex items-center gap-3 bg-slate-50 border border-slate-200 px-4 py-2 rounded-2xl hover:bg-slate-100 transition-all">
            <div class="w-8 h-8 rounded-xl bg-rose-100 text-[#E11D48] flex items-center justify-center font-bold text-xs">
              <?= strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div class="text-left hidden sm:block">
              <p class="text-[10px] text-slate-400 font-bold uppercase"><?= ($user_role === 'admin') ? 'Administrator' : 'Pelanggan'; ?></p>
              <p class="text-xs font-extrabold text-slate-800"><?= htmlspecialchars($user_name); ?></p>
            </div>
          </a>
          <a href="login.php?action=logout" class="p-2.5 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 transition-all" title="Keluar">
            <span class="material-symbols-outlined text-[18px]">logout</span>
          </a>
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
    <!-- Breadcrumb -->
    <div class="mb-6 flex items-center gap-2 text-xs font-semibold text-slate-500">
      <a href="catalog.php" class="hover:text-[#E11D48] flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Katalog Layanan
      </a>
      <span>/</span>
      <span class="text-slate-900 font-bold">Checkout Pesanan</span>
    </div>

    <?php if ($success_msg): ?>
      <div class="card-studio p-8 md:p-10 max-w-xl mx-auto space-y-6">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl font-extrabold flex-shrink-0">✓</div>
          <div>
            <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-extrabold uppercase">Menunggu Konfirmasi</span>
            <h2 class="text-xl font-extrabold text-slate-900 mt-1">Pesanan Berhasil Dicatat</h2>
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

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <a href="user-dashboard.php" class="w-full py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs text-center transition-all">
            Lihat di Dashboard Saya
          </a>
          <a href="catalog.php" class="w-full py-3.5 rounded-xl bg-white border border-slate-200 hover:border-slate-300 text-slate-700 font-bold text-xs text-center transition-all">
            Pesan Layanan Lain
          </a>
        </div>
      </div>
    <?php else: ?>
      <form method="POST" action="checkout.php" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <input type="hidden" name="action_process_payment" value="1">
        <input type="hidden" name="nama_layanan" value="<?= htmlspecialchars($nama_layanan); ?>">
        <input type="hidden" name="harga" value="<?= htmlspecialchars($harga_angka); ?>">
        <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori); ?>">

        <!-- KOLOM KIRI: DATA PEMESAN & METODE PEMBAYARAN -->
        <div class="lg:col-span-7 space-y-6">
          <!-- Card Data Pemesan -->
          <div class="card-studio p-6 md:p-8 space-y-5">
            <div class="border-b border-slate-100 pb-4">
              <h2 class="text-lg font-extrabold text-slate-900">1. Informasi Pemesan & Usaha</h2>
              <p class="text-xs text-slate-500 mt-0.5">Lengkapi data kontak agar tim Marketiva dapat menghubungi Anda.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" class="input-studio" placeholder="Nama Anda" value="<?= htmlspecialchars($is_logged_in ? $user_name : ''); ?>" required>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nama Usaha / UMKM</label>
                <input type="text" name="nama_usaha" class="input-studio" placeholder="Contoh: Kopi Senja" required>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nomor WhatsApp Aktif</label>
              <input type="text" name="whatsapp" class="input-studio" placeholder="08123456789" required>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Catatan Tambahan (Opsional)</label>
              <textarea name="catatan" rows="3" class="input-studio" placeholder="Tuliskan target akun, referensi, atau permintaan khusus..."></textarea>
            </div>
          </div>

          <!-- Card Metode Pembayaran -->
          <div class="card-studio p-6 md:p-8 space-y-5">
            <div class="border-b border-slate-100 pb-4">
              <h2 class="text-lg font-extrabold text-slate-900">2. Metode Pembayaran</h2>
              <p class="text-xs text-slate-500 mt-0.5">Pilih metode pembayaran yang paling sesuai untuk transaksi Anda.</p>
            </div>

            <div class="space-y-3.5">
              <!-- Opsi Midtrans -->
              <label class="payment-option block p-4 rounded-2xl border-2 border-slate-200 cursor-pointer transition-all">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-start gap-3">
                    <input type="radio" name="metode_bayar" value="Midtrans Payment Gateway (Menunggu Aktivasi)" class="mt-1 accent-[#E11D48]">
                    <div>
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-extrabold text-slate-900">Midtrans Payment Gateway</span>
                        <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold">Proses Verifikasi Akun</span>
                      </div>
                      <p class="text-xs text-slate-500 mt-1">Pembayaran instan & otomatis setelah integrasi akun Midtrans aktif.</p>
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

              <!-- Opsi Transfer Manual BSI -->
              <label class="payment-option block p-4 rounded-2xl border-2 border-slate-200 cursor-pointer transition-all">
                <div class="flex items-start gap-3">
                  <input type="radio" name="metode_bayar" value="Transfer BSI (7368562097)" class="mt-1 accent-[#E11D48]" checked required>
                  <div>
                    <span class="text-sm font-extrabold text-slate-900">Transfer Manual Bank (Konfirmasi Admin)</span>
                    <p class="text-xs text-slate-600 mt-1">Transfer BSI <span class="font-extrabold text-slate-900">7368562097</span> a.n. Marketiva Digital Agency. Pesanan tercatat dengan status Menunggu Konfirmasi.</p>
                  </div>
                </div>
              </label>

              <!-- Opsi QRIS Manual -->
              <label class="payment-option block p-4 rounded-2xl border-2 border-slate-200 cursor-pointer transition-all">
                <div class="flex items-start gap-3">
                  <input type="radio" name="metode_bayar" value="QRIS All Payment" class="mt-1 accent-[#E11D48]">
                  <div>
                    <span class="text-sm font-extrabold text-slate-900">QRIS All Payment</span>
                    <p class="text-xs text-slate-500 mt-1">Scan kode QRIS melalui aplikasi bank atau e-wallet, lalu konfirmasi ke admin.</p>
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- KOLOM KANAN: RINCIAN PESANAN -->
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
            <span>Konfirmasi & Buat Pesanan</span>
          </button>

          <p class="text-[11px] text-slate-500 text-center leading-relaxed">
            Setelah dikirim, pesanan otomatis tersimpan ke database dan muncul pada menu Pesanan On Progress di Dashboard Pelanggan.
          </p>
        </div>
      </form>
    <?php endif; ?>
  </main>

  <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400 mt-12">
    © 2026 Marketiva Digital Agency. All Rights Reserved.
  </footer>

</body>
</html>