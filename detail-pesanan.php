<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

$order_param = trim($_GET['order_id'] ?? $_GET['id'] ?? '');
$order = null;
$msg_status = '';

// Update status pesanan jika form status dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_status'])) {
    $target_order = trim($_POST['order_id_hidden'] ?? '');
    $new_status   = trim($_POST['status_pesanan'] ?? '');

    if (!empty($target_order) && !empty($new_status)) {
        $stmt_upd = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ? LIMIT 1");
        if ($stmt_upd) {
            $stmt_upd->bind_param("ss", $new_status, $target_order);
            if ($stmt_upd->execute()) {
                $msg_status = "Status pesanan berhasil diperbarui menjadi: " . $new_status;
            }
            $stmt_upd->close();
        }
    }
    $order_param = $target_order;
}

// Ambil data pesanan dari tabel orders
if (!empty($order_param)) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $order_param);
        $stmt->execute();
        $res = $stmt->get_result();
        $order = $res->fetch_assoc();
        $stmt->close();
    }

    if (!$order && is_numeric($order_param)) {
        $id_num = intval($order_param);
        $stmt_id = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        if ($stmt_id) {
            $stmt_id->bind_param("i", $id_num);
            $stmt_id->execute();
            $res_id = $stmt_id->get_result();
            $order = $res_id->fetch_assoc();
            $stmt_id->close();
        }
    }
} else {
    // Jika dibuka langsung tanpa ?order_id=..., ambil pesanan terbaru dari tabel orders
    $res_latest = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC LIMIT 1");
    if ($res_latest) {
        $order = mysqli_fetch_assoc($res_latest);
    }
}

$is_logged_in   = isset($_SESSION['user']);
$user_name      = $_SESSION['user'] ?? '';
$user_role      = $_SESSION['role'] ?? 'user';
$dashboard_link = ($user_role === 'admin') ? 'admin-dashboard.php' : 'user-dashboard.php';

$wa_raw = preg_replace('/[^0-9]/', '', ($order['whatsapp'] ?? ''));
if (strpos($wa_raw, '0') === 0) {
    $wa_raw = '62' . substr($wa_raw, 1);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Pesanan - Marketiva</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #F8FAFC;
      color: #131b2e;
    }
    .card-studio {
      background-color: #FFFFFF;
      border-radius: 1rem;
      border: 1px solid #E2E8F0;
      box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.03);
    }
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between antialiased">

  <!-- TOPBAR -->
  <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200">
    <div class="max-w-[1240px] mx-auto px-6 h-20 flex items-center justify-between gap-4">
      <a href="index.php" class="font-extrabold text-2xl tracking-tight">
        <span class="text-[#F43F5E]">Mar</span><span class="text-[#3B82F6]">ke</span><span class="text-[#10B981]">ti</span><span class="text-[#F59E0B]">va</span>
      </a>

      <div class="flex items-center gap-3">
        <?php if ($is_logged_in): ?>
          <a href="<?= htmlspecialchars($dashboard_link); ?>" class="flex items-center gap-2.5 bg-slate-50 border border-slate-200 px-3.5 py-2 rounded-xl text-xs font-bold text-slate-800 hover:bg-slate-100">
            <span class="w-7 h-7 rounded-lg bg-rose-100 text-[#E11D48] flex items-center justify-center font-extrabold">
              <?= strtoupper(substr($user_name, 0, 1)); ?>
            </span>
            <span><?= htmlspecialchars($user_name); ?></span>
          </a>
        <?php else: ?>
          <a href="login.php" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold">Masuk</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="max-w-[1240px] mx-auto px-6 py-8 w-full flex-1 space-y-6">
    <?php if (!empty($msg_status)): ?>
      <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">check_circle</span>
        <span><?= htmlspecialchars($msg_status); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($order): ?>
      <?php
        $kode_order   = $order['order_id'] ?? ('#' . ($order['id'] ?? ''));
        $status_order = $order['status'] ?? 'Menunggu Konfirmasi';
        $harga_satuan = floatval($order['harga'] ?? 0);
      ?>

      <!-- HEADER DETAIL PESANAN -->
      <div class="card-studio p-5 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-lg md:text-xl font-extrabold text-slate-900">
            Detail Pesanan <?= htmlspecialchars($kode_order); ?>
          </h1>
          <span class="px-3 py-1 rounded-full text-xs font-bold
            <?= $status_order === 'Selesai' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($status_order === 'Diproses' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200'); ?>">
            <?= htmlspecialchars($status_order); ?>
          </span>
        </div>

        <a href="<?= htmlspecialchars($dashboard_link); ?>" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold inline-flex items-center gap-1.5 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span>
          <span>Kembali</span>
        </a>
      </div>

      <!-- GRID UTAMA -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- KOLOM KIRI -->
        <div class="lg:col-span-8 space-y-6">
          <!-- Status & Verifikasi Pembayaran -->
          <div class="card-studio p-6 space-y-4">
            <h2 class="text-sm font-extrabold text-slate-900 border-b border-slate-100 pb-3">
              Status & Verifikasi Pembayaran
            </h2>

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-3">
              <span class="material-symbols-outlined text-slate-600 text-[22px]">account_balance</span>
              <div class="text-xs space-y-1">
                <p class="font-extrabold text-slate-900">Metode Pembayaran</p>
                <p class="text-slate-600">
                  <?= htmlspecialchars($order['metode_bayar'] ?? 'Transfer Manual / Konfirmasi Admin'); ?>
                </p>
              </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-slate-600 text-[22px]">receipt_long</span>
                <div class="text-xs">
                  <p class="font-extrabold text-slate-900">Bukti Pembayaran</p>
                  <p class="text-slate-500">
                    <?= !empty($order['bukti_bayar']) ? 'File bukti pembayaran tersedia.' : 'Menunggu konfirmasi bukti pembayaran dari pelanggan.'; ?>
                  </p>
                </div>
              </div>
              <?php if (!empty($order['bukti_bayar'])): ?>
                <a href="<?= htmlspecialchars($order['bukti_bayar']); ?>" target="_blank" class="px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-100">
                  Buka File
                </a>
              <?php endif; ?>
            </div>
          </div>

          <!-- Daftar Produk / Layanan yang Dipesan -->
          <div class="card-studio p-6 space-y-4">
            <h2 class="text-sm font-extrabold text-slate-900 border-b border-slate-100 pb-3">
              Daftar Layanan yang Dipesan
            </h2>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
              <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px]">
                  <tr>
                    <th class="py-3 px-4">Layanan</th>
                    <th class="py-3 px-4">Harga Satuan</th>
                    <th class="py-3 px-4">Qty</th>
                    <th class="py-3 px-4 text-right">Subtotal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                  <tr>
                    <td class="py-3.5 px-4">
                      <p class="font-bold text-slate-900"><?= htmlspecialchars($order['nama_layanan'] ?? '-'); ?></p>
                      <p class="text-[11px] text-slate-500">Kategori: <?= htmlspecialchars($order['kategori'] ?? 'Digital Marketing'); ?></p>
                    </td>
                    <td class="py-3.5 px-4 font-semibold">Rp <?= number_format($harga_satuan, 0, ',', '.'); ?></td>
                    <td class="py-3.5 px-4">1</td>
                    <td class="py-3.5 px-4 text-right font-extrabold text-slate-900">Rp <?= number_format($harga_satuan, 0, ',', '.'); ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- KOLOM KANAN -->
        <div class="lg:col-span-4 space-y-6">
          <!-- Kontak Pembeli -->
          <div class="card-studio p-6 space-y-4">
            <h2 class="text-sm font-extrabold text-slate-900 border-b border-slate-100 pb-3">
              Kontak Pembeli
            </h2>

            <div class="space-y-2.5 text-xs">
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Nama Pemesan</span>
                <span class="font-bold text-slate-900"><?= htmlspecialchars($order['nama_lengkap'] ?? '-'); ?></span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Nama Usaha</span>
                <span class="font-semibold text-slate-700"><?= htmlspecialchars($order['nama_usaha'] ?? '-'); ?></span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Email</span>
                <span class="font-semibold text-slate-700"><?= htmlspecialchars($order['user_email'] ?? '-'); ?></span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">No. Handphone / WhatsApp</span>
                <span class="font-semibold text-slate-700"><?= htmlspecialchars($order['whatsapp'] ?? '-'); ?></span>
              </div>
            </div>

            <?php if (!empty($wa_raw)): ?>
              <a href="https://wa.me/<?= htmlspecialchars($wa_raw); ?>" target="_blank" rel="noopener noreferrer" class="w-full py-2.5 px-4 rounded-xl bg-amber-900/90 hover:bg-amber-900 text-white text-xs font-bold flex items-center justify-center gap-2 transition-all">
                <span class="material-symbols-outlined text-[16px]">chat</span>
                <span>Hubungi via WhatsApp</span>
              </a>
            <?php endif; ?>
          </div>

          <!-- Update Status Pesanan -->
          <div class="card-studio p-6 space-y-4">
            <h2 class="text-sm font-extrabold text-slate-900 border-b border-slate-100 pb-3">
              Update Status Pesanan
            </h2>

            <form method="POST" action="detail-pesanan.php?order_id=<?= urlencode($kode_order); ?>" class="space-y-3">
              <input type="hidden" name="action_update_status" value="1">
              <input type="hidden" name="order_id_hidden" value="<?= htmlspecialchars($kode_order); ?>">

              <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Status Pesanan</label>
                <select name="status_pesanan" class="w-full h-10 px-3 rounded-xl border border-slate-300 text-xs font-bold text-slate-800 bg-white">
                  <option value="Menunggu Konfirmasi" <?= $status_order === 'Menunggu Konfirmasi' ? 'selected' : ''; ?>>Menunggu Konfirmasi</option>
                  <option value="Diproses" <?= $status_order === 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                  <option value="Selesai" <?= $status_order === 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                </select>
              </div>

              <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-all">
                Simpan Status
              </button>
            </form>
          </div>
        </div>
      </div>

    <?php else: ?>
      <div class="card-studio p-8 max-w-md mx-auto text-center space-y-4">
        <h2 class="text-lg font-extrabold text-slate-900">Detail Pesanan Tidak Ditemukan</h2>
        <p class="text-xs text-slate-500">
          Masukkan parameter <code>?order_id=...</code> pada URL untuk melihat rincian pesanan dari tabel <code>orders</code>.
        </p>
        <a href="catalog.php" class="inline-block px-5 py-2.5 rounded-xl bg-[#E11D48] text-white text-xs font-bold">
          Kembali ke Katalog Layanan
        </a>
      </div>
    <?php endif; ?>
  </main>

  <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400 mt-12">
    © 2026 Marketiva Digital Agency. All Rights Reserved.
  </footer>

</body>
</html>