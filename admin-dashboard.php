<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_name  = $_SESSION['user'];
$admin_id    = $_SESSION['user_id'] ?? null;
$admin_email = $_SESSION['email'] ?? '';
$msg_success = '';
$msg_error   = '';
$initial_tab = $_GET['tab'] ?? 'services';

// Fungsi helper mencatat log aktivitas
function recordLog($conn, $admin, $aktivitas) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (admin_name, aktivitas) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ss", $admin, $aktivitas);
        $stmt->execute();
        $stmt->close();
    }
}

// Fungsi helper memastikan kolom tambahan pada tabel about_us tersedia
function ensureAboutUsColumns($conn) {
    if (!$conn) return;
    $cols = [
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
    foreach ($cols as $col => $def) {
        $safe = mysqli_real_escape_string($conn, $col);
        $chk  = mysqli_query($conn, "SHOW COLUMNS FROM `about_us` LIKE '{$safe}'");
        if ($chk && mysqli_num_rows($chk) === 0) {
            @mysqli_query($conn, "ALTER TABLE `about_us` ADD COLUMN `{$col}` {$def}");
        }
    }
}

ensureAboutUsColumns($conn);

// 0. LIHAT PROFIL USER (READ-ONLY) DARI DATA PELANGGAN
if (isset($_GET['view_user'])) {
    $target_uid = intval($_GET['view_user']);
    if ($target_uid > 0) {
        $stmt_u = $conn->prepare("SELECT id, nama_lengkap, email FROM users WHERE id = ? AND role = 'user' LIMIT 1");
        $stmt_u->bind_param("i", $target_uid);
        $stmt_u->execute();
        $res_u = $stmt_u->get_result();
        if ($target_user = $res_u->fetch_assoc()) {
            $uid_safe = intval($target_user['id']);
            recordLog($conn, $admin_name, "Melihat profil read-only pelanggan: " . $target_user['nama_lengkap'] . " (" . $target_user['email'] . ")");
            $stmt_u->close();
            header("Location: user-dashboard.php?view_user=" . $uid_safe);
            exit();
        }
        $stmt_u->close();
    }
    $msg_error   = "Akun pelanggan tidak ditemukan.";
    $initial_tab = 'users';
}

// 1. PROSES TAMBAH KATEGORI BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_category'])) {
    $nama_kategori = trim($_POST['nama_kategori'] ?? '');
    if (!empty($nama_kategori)) {
        $query_cat = "INSERT INTO categories (nama_kategori) VALUES ('$nama_kategori')";
        if (mysqli_query($conn, $query_cat)) {
            $msg_success = "Kategori baru berhasil ditambahkan!";
            recordLog($conn, $admin_name, "Menambahkan kategori baru: $nama_kategori");
        } else {
            $msg_error = "Gagal menambah kategori: " . mysqli_error($conn);
        }
    } else {
        $msg_error = "Nama kategori tidak boleh kosong!";
    }
}

// 2. PROSES TAMBAH ATAU EDIT LAYANAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_service'])) {
    $service_id   = intval($_POST['service_id'] ?? 0);
    $nama_layanan = trim($_POST['nama_layanan'] ?? '');
    $kategori_id  = intval($_POST['kategori_id'] ?? 1);
    $harga        = floatval($_POST['harga'] ?? 0);
    $deskripsi    = trim($_POST['deskripsi'] ?? '');
    $gambar_path  = trim($_POST['old_gambar'] ?? '');

    if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp   = $_FILES['gambar_file']['tmp_name'];
        $file_name  = time() . '_' . basename($_FILES['gambar_file']['name']);
        $upload_dir = 'uploads/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($file_tmp, $target_file)) {
            if (!empty($gambar_path) && file_exists($gambar_path)) {
                @unlink($gambar_path);
            }
            $gambar_path = $target_file;
        }
    }

    if (!empty($nama_layanan) && $harga > 0) {
        if ($service_id > 0) {
            $query_update = "UPDATE services SET nama_layanan='$nama_layanan', kategori_id='$kategori_id', harga='$harga', deskripsi='$deskripsi', gambar='$gambar_path' WHERE id=$service_id";
            if (mysqli_query($conn, $query_update)) {
                $msg_success = "Layanan berhasil diperbarui!";
                recordLog($conn, $admin_name, "Memperbarui layanan: $nama_layanan");
            } else {
                $msg_error = "Gagal memperbarui layanan: " . mysqli_error($conn);
            }
        } else {
            $query_insert = "INSERT INTO services (nama_layanan, kategori_id, harga, deskripsi, gambar) VALUES ('$nama_layanan', '$kategori_id', '$harga', '$deskripsi', '$gambar_path')";
            if (mysqli_query($conn, $query_insert)) {
                $msg_success = "Layanan baru berhasil ditambahkan!";
                recordLog($conn, $admin_name, "Menambah layanan baru: $nama_layanan");
            } else {
                $msg_error = "Gagal menambah layanan: " . mysqli_error($conn);
            }
        }
    } else {
        $msg_error = "Nama layanan dan harga wajib diisi dengan benar!";
    }
}

// 3. PROSES UPDATE STATUS PESANAN (GET & POST MODAL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_order_status'])) {
    $o_id     = intval($_POST['order_id'] ?? 0);
    $o_status = trim($_POST['order_status'] ?? '');
    if ($o_id > 0 && !empty($o_status)) {
        $stmt_ord = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt_ord->bind_param("si", $o_status, $o_id);
        $stmt_ord->execute();
        $stmt_ord->close();
        recordLog($conn, $admin_name, "Mengubah status pesanan #$o_id menjadi $o_status");
        header("Location: admin-dashboard.php?tab=orders&status_updated=1");
        exit();
    }
}

if (isset($_GET['update_order_status']) && isset($_GET['order_id'])) {
    $o_id     = intval($_GET['order_id']);
    $o_status = trim($_GET['update_order_status']);
    if ($o_id > 0 && !empty($o_status)) {
        $stmt_ord = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt_ord->bind_param("si", $o_status, $o_id);
        $stmt_ord->execute();
        $stmt_ord->close();
        recordLog($conn, $admin_name, "Mengubah status pesanan #$o_id menjadi $o_status");
    }
    header("Location: admin-dashboard.php?tab=orders&status_updated=1");
    exit();
}

if (isset($_GET['status_updated'])) {
    $msg_success = "Status pesanan berhasil diperbarui!";
    $initial_tab = 'orders';
}

// 4. PROSES HAPUS LAYANAN
if (isset($_GET['delete_service'])) {
    $id_del  = intval($_GET['delete_service']);
    $get_img = mysqli_query($conn, "SELECT nama_layanan, gambar FROM services WHERE id = $id_del");
    if ($row_img = mysqli_fetch_assoc($get_img)) {
        if (!empty($row_img['gambar']) && file_exists($row_img['gambar'])) {
            @unlink($row_img['gambar']);
        }
        recordLog($conn, $admin_name, "Menghapus layanan: " . $row_img['nama_layanan']);
    }
    mysqli_query($conn, "DELETE FROM services WHERE id = $id_del");
    header("Location: admin-dashboard.php");
    exit();
}

// 5. PROSES UPDATE PROFIL ADMIN (TERHUBUNG KE TABEL USERS & SESSION)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_admin'])) {
    $initial_tab      = 'profile';
    $target_admin_id  = intval($_POST['selected_admin_id'] ?? ($admin_id ?? 0));
    $new_name         = trim($_POST['admin_name'] ?? '');
    $new_email        = trim($_POST['admin_email'] ?? '');
    $new_whatsapp     = trim($_POST['admin_whatsapp'] ?? '');
    $new_alamat       = trim($_POST['admin_alamat'] ?? '');
    $new_password     = trim($_POST['admin_new_password'] ?? '');

    if (!empty($new_name) && !empty($new_email)) {
        if ($target_admin_id > 0) {
            if (!empty($new_password)) {
                $stmt_adm = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, whatsapp = ?, alamat = ?, password = ? WHERE id = ? AND role = 'admin'");
                $stmt_adm->bind_param("sssssi", $new_name, $new_email, $new_whatsapp, $new_alamat, $new_password, $target_admin_id);
            } else {
                $stmt_adm = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, whatsapp = ?, alamat = ? WHERE id = ? AND role = 'admin'");
                $stmt_adm->bind_param("ssssi", $new_name, $new_email, $new_whatsapp, $new_alamat, $target_admin_id);
            }
            $ok_adm = $stmt_adm->execute();
            $stmt_adm->close();

            if ($ok_adm) {
                if (!$admin_id || intval($admin_id) === $target_admin_id) {
                    $_SESSION['user_id'] = $target_admin_id;
                    $_SESSION['user']    = $new_name;
                    $_SESSION['email']   = $new_email;
                    $admin_name          = $new_name;
                    $admin_email         = $new_email;
                }
                $msg_success = "Identitas profil administrator berhasil disimpan ke database!";
                recordLog($conn, $admin_name, "Memperbarui identitas profil admin: $new_name ($new_email)");
            } else {
                $msg_error = "Gagal memperbarui profil administrator: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['user']  = $new_name;
            $_SESSION['email'] = $new_email;
            $admin_name        = $new_name;
            $admin_email       = $new_email;
            $msg_success       = "Profil administrator berhasil diperbarui!";
            recordLog($conn, $admin_name, "Memperbarui nama profil admin.");
        }
    } else {
        $msg_error = "Nama administrator dan email wajib diisi!";
    }
}

// 6. PROSES UPDATE KONTEN "TENTANG KAMI" (TERHUBUNG PENUH KE KOLOM about_us)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_about'])) {
    $initial_tab      = 'about';
    $judul            = trim($_POST['about_judul'] ?? '');
    $deskripsi        = trim($_POST['about_deskripsi'] ?? '');
    $visi             = trim($_POST['about_visi'] ?? '');
    $misi             = trim($_POST['about_misi'] ?? '');
    $tab_umkm_label   = trim($_POST['tab_umkm_label'] ?? 'Pelaku UMKM & Brand');
    $tab_umkm_desc    = trim($_POST['tab_umkm_desc'] ?? '');
    $tab_agency_label = trim($_POST['tab_agency_label'] ?? 'Tim & Sistem Marketiva');
    $tab_agency_desc  = trim($_POST['tab_agency_desc'] ?? '');
    $three_words_q    = trim($_POST['three_words_quote'] ?? 'KREATIF, TERUKUR, DAN TERPERCAYA — 3 KATA YANG PALING TEPAT MENGGAMBARKAN MARKETIVA');
    $smm_title        = trim($_POST['smm_title'] ?? '');
    $smm_desc         = trim($_POST['smm_desc'] ?? '');
    $content_title    = trim($_POST['content_title'] ?? '');
    $content_desc     = trim($_POST['content_desc'] ?? '');
    $ads_title        = trim($_POST['ads_title'] ?? '');
    $ads_desc         = trim($_POST['ads_desc'] ?? '');

    if (!empty($judul) && !empty($deskripsi)) {
        $check_about = mysqli_query($conn, "SELECT id FROM `about_us` WHERE id = 1");
        if ($check_about && mysqli_num_rows($check_about) > 0) {
            $stmt_ab = $conn->prepare("UPDATE `about_us` SET
                judul = ?, deskripsi = ?, visi = ?, misi = ?,
                tab_umkm_label = ?, tab_umkm_desc = ?, tab_agency_label = ?, tab_agency_desc = ?,
                three_words_quote = ?, smm_title = ?, smm_desc = ?,
                content_title = ?, content_desc = ?, ads_title = ?, ads_desc = ?
                WHERE id = 1");
            $stmt_ab->bind_param(
                "sssssssssssssss",
                $judul, $deskripsi, $visi, $misi,
                $tab_umkm_label, $tab_umkm_desc, $tab_agency_label, $tab_agency_desc,
                $three_words_q, $smm_title, $smm_desc,
                $content_title, $content_desc, $ads_title, $ads_desc
            );
            $ok_ab = $stmt_ab->execute();
            $stmt_ab->close();
        } else {
            $stmt_ab = $conn->prepare("INSERT INTO `about_us` (
                id, judul, deskripsi, visi, misi,
                tab_umkm_label, tab_umkm_desc, tab_agency_label, tab_agency_desc,
                three_words_quote, smm_title, smm_desc,
                content_title, content_desc, ads_title, ads_desc
            ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_ab->bind_param(
                "sssssssssssssss",
                $judul, $deskripsi, $visi, $misi,
                $tab_umkm_label, $tab_umkm_desc, $tab_agency_label, $tab_agency_desc,
                $three_words_q, $smm_title, $smm_desc,
                $content_title, $content_desc, $ads_title, $ads_desc
            );
            $ok_ab = $stmt_ab->execute();
            $stmt_ab->close();
        }

        if ($ok_ab) {
            $msg_success = "Konten 'Tentang Kami' berhasil diperbarui dan disinkronkan ke database!";
            recordLog($conn, $admin_name, "Memperbarui halaman Tentang Kami.");
        } else {
            $msg_error = "Gagal memperbarui konten Tentang Kami: " . mysqli_error($conn);
        }
    } else {
        $msg_error = "Judul dan Deskripsi Tentang Kami tidak boleh kosong!";
    }
}

// 7. PROSES TAMBAH / EDIT HERO SLIDE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_slide'])) {
    $slide_id  = intval($_POST['slide_id'] ?? 0);
    $badge     = trim($_POST['badge'] ?? '');
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if (!empty($judul)) {
        if ($slide_id > 0) {
            $stmt = $conn->prepare("UPDATE hero_slides SET badge = ?, judul = ?, deskripsi = ? WHERE id = ?");
            $stmt->bind_param("sssi", $badge, $judul, $deskripsi, $slide_id);
            $stmt->execute();
            $stmt->close();
            $msg_success = "Banner Slide Hero berhasil diperbarui!";
        } else {
            $stmt = $conn->prepare("INSERT INTO hero_slides (badge, judul, deskripsi) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $badge, $judul, $deskripsi);
            $stmt->execute();
            $stmt->close();
            $msg_success = "Banner Slide Hero baru berhasil ditambahkan!";
        }
        recordLog($conn, $admin_name, "Memperbarui Hero Slides Beranda");
    } else {
        $msg_error = "Judul Banner Slide tidak boleh kosong!";
    }
}

// 8. PROSES HAPUS HERO SLIDE
if (isset($_GET['delete_slide'])) {
    $id_slide = intval($_GET['delete_slide']);
    mysqli_query($conn, "DELETE FROM hero_slides WHERE id = $id_slide");
    recordLog($conn, $admin_name, "Menghapus Hero Slide #$id_slide");
    header("Location: admin-dashboard.php");
    exit();
}

// 9. PROSES UPDATE PAKET HARGA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_paket'])) {
    $paket_id    = intval($_POST['paket_id'] ?? 0);
    $nama_paket  = trim($_POST['nama_paket'] ?? '');
    $sub_judul   = trim($_POST['sub_judul'] ?? '');
    $harga       = floatval($_POST['harga'] ?? 0);
    $fitur       = trim($_POST['fitur'] ?? '');
    $rekomendasi = intval($_POST['rekomendasi'] ?? 0);

    if ($paket_id > 0 && !empty($nama_paket)) {
        $stmt_pkt = $conn->prepare("UPDATE paket_harga SET nama_paket = ?, sub_judul = ?, harga = ?, fitur = ?, rekomendasi = ? WHERE id = ?");
        $stmt_pkt->bind_param("ssdsii", $nama_paket, $sub_judul, $harga, $fitur, $rekomendasi, $paket_id);
        $stmt_pkt->execute();
        $stmt_pkt->close();
        $msg_success = "Paket harga berhasil diperbarui!";
        recordLog($conn, $admin_name, "Memperbarui paket harga: $nama_paket");
    }
}

// 10. PROSES UPDATE BANNER CTA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_cta'])) {
    $badge_cta = trim($_POST['cta_badge'] ?? '');
    $judul_cta = trim($_POST['cta_judul'] ?? '');
    $desc_cta  = trim($_POST['cta_deskripsi'] ?? '');

    if (!empty($judul_cta)) {
        $check_cta = mysqli_query($conn, "SELECT id FROM cta_banner WHERE id = 1");
        if ($check_cta && mysqli_num_rows($check_cta) > 0) {
            $stmt_cta = $conn->prepare("UPDATE cta_banner SET badge = ?, judul = ?, deskripsi = ? WHERE id = 1");
            $stmt_cta->bind_param("sss", $badge_cta, $judul_cta, $desc_cta);
            $stmt_cta->execute();
            $stmt_cta->close();
        } else {
            $stmt_cta = $conn->prepare("INSERT INTO cta_banner (id, badge, judul, deskripsi) VALUES (1, ?, ?, ?)");
            $stmt_cta->bind_param("sss", $badge_cta, $judul_cta, $desc_cta);
            $stmt_cta->execute();
            $stmt_cta->close();
        }
        $msg_success = "Banner CTA berhasil diperbarui!";
        recordLog($conn, $admin_name, "Memperbarui Banner CTA.");
    }
}

// 11. PROSES TAMBAH ARTIKEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_tambah_artikel'])) {
    $title    = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $date     = trim($_POST['date'] ?? '');
    $excerpt  = trim($_POST['excerpt'] ?? '');

    $input_slug = trim($_POST['slug'] ?? '');
    if (!empty($input_slug)) {
        if (strpos($input_slug, 'http://') === 0 || strpos($input_slug, 'https://') === 0) {
            $slug = $input_slug;
        } elseif (strpos($input_slug, '.') !== false || strpos($input_slug, '/') !== false) {
            $slug = 'https://' . ltrim($input_slug, '/');
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $input_slug), '-'));
        }
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }

    $image_path = 'uploads/default-article.jpg';

    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp   = $_FILES['image_file']['tmp_name'];
        $file_name  = time() . '_' . basename($_FILES['image_file']['name']);
        $upload_dir = 'uploads/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($file_tmp, $target_file)) {
            $image_path = $target_file;
        }
    }

    if (!empty($title) && !empty($excerpt)) {
        $stmt_art = $conn->prepare("INSERT INTO articles (title, category, date, excerpt, image, slug) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_art->bind_param("ssssss", $title, $category, $date, $excerpt, $image_path, $slug);
        $stmt_art->execute();
        $stmt_art->close();
        $msg_success = "Artikel berhasil dipublikasikan dengan URL slug: $slug!";
        recordLog($conn, $admin_name, "Menambahkan artikel baru: $title");
    } else {
        $msg_error = "Judul dan ringkasan artikel wajib diisi!";
    }
}

// 12. PROSES HAPUS ARTIKEL
if (isset($_GET['delete_article'])) {
    $id_art = intval($_GET['delete_article']);
    mysqli_query($conn, "DELETE FROM articles WHERE id = $id_art");
    recordLog($conn, $admin_name, "Menghapus artikel ID #$id_art");
    header("Location: admin-dashboard.php");
    exit();
}

$count_services = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM services"))['total'] ?? 0;
$count_users    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'user'"))['total'] ?? 0;
$count_orders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders"))['total'] ?? 0;

$categories_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY nama_kategori ASC");
$services_res   = mysqli_query($conn, "SELECT s.*, c.nama_kategori FROM services s LEFT JOIN categories c ON s.kategori_id = c.id ORDER BY s.id DESC");
$logs_res       = mysqli_query($conn, "SELECT * FROM activity_logs ORDER BY id DESC LIMIT 15");

// Ambil semua pesanan + gabungkan dengan data user bila kolom nama_lengkap/nama_usaha kosong
$orders_list   = [];
$order_counts  = [
    'all'                  => 0,
    'Menunggu Pembayaran'  => 0,
    'Menunggu Konfirmasi'  => 0,
    'Diproses'             => 0,
    'Selesai'              => 0,
    'Dibatalkan'           => 0,
];
$orders_res = mysqli_query($conn, "SELECT o.*, u.nama_lengkap AS u_nama, u.nama_umkm AS u_umkm, u.whatsapp AS u_wa FROM orders o LEFT JOIN users u ON (o.user_id = u.id OR (o.user_email <> '' AND o.user_email = u.email)) ORDER BY o.id DESC");
if ($orders_res) {
    while ($ord = mysqli_fetch_assoc($orders_res)) {
        if (empty($ord['nama_lengkap']) && !empty($ord['u_nama'])) {
            $ord['nama_lengkap'] = $ord['u_nama'];
        }
        if (empty($ord['nama_usaha']) && !empty($ord['u_umkm'])) {
            $ord['nama_usaha'] = $ord['u_umkm'];
        }
        if (empty($ord['whatsapp']) && !empty($ord['u_wa'])) {
            $ord['whatsapp'] = $ord['u_wa'];
        }
        $st = trim($ord['status'] ?? 'Menunggu Pembayaran');
        $orders_list[] = $ord;
        $order_counts['all']++;
        if (isset($order_counts[$st])) {
            $order_counts[$st]++;
        }
    }
}

// Ambil seluruh data pelanggan + statistik pesanan tiap pelanggan
$customers_list = [];
$users_res = mysqli_query($conn, "SELECT * FROM users WHERE role = 'user' ORDER BY id DESC");
if ($users_res) {
    while ($u = mysqli_fetch_assoc($users_res)) {
        $uid_int = intval($u['id']);
        $u_mail  = mysqli_real_escape_string($conn, $u['email'] ?? '');
        $stat_q  = mysqli_query($conn, "SELECT
            COUNT(*) AS total_ord,
            SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) AS selesai_ord,
            SUM(CASE WHEN status NOT IN ('Selesai', 'Dibatalkan') THEN 1 ELSE 0 END) AS aktif_ord
            FROM orders WHERE user_id = $uid_int OR (user_email <> '' AND user_email = '$u_mail')");
        $stat_row = $stat_q ? mysqli_fetch_assoc($stat_q) : ['total_ord' => 0, 'selesai_ord' => 0, 'aktif_ord' => 0];
        $u['total_ord']   = intval($stat_row['total_ord'] ?? 0);
        $u['selesai_ord'] = intval($stat_row['selesai_ord'] ?? 0);
        $u['aktif_ord']   = intval($stat_row['aktif_ord'] ?? 0);
        $customers_list[] = $u;
    }
}

// Ambil seluruh akun administrator untuk pengaturan profil
$admins_list   = [];
$current_admin = null;
$admins_res    = mysqli_query($conn, "SELECT * FROM users WHERE role = 'admin' ORDER BY id ASC");
if ($admins_res) {
    while ($adm = mysqli_fetch_assoc($admins_res)) {
        $admins_list[] = $adm;
        if (($admin_id && intval($adm['id']) === intval($admin_id)) || (!empty($admin_email) && $adm['email'] === $admin_email) || $adm['nama_lengkap'] === $admin_name) {
            $current_admin = $adm;
        }
    }
}
if (!$current_admin && !empty($admins_list)) {
    $current_admin = $admins_list[0];
}

$about_data   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM `about_us` WHERE id = 1")) ?? [];
$slides_res   = mysqli_query($conn, "SELECT * FROM hero_slides ORDER BY id DESC");
$paket_res    = mysqli_query($conn, "SELECT * FROM paket_harga ORDER BY id ASC");
$cta_data     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM cta_banner WHERE id = 1")) ?? ['badge' => '', 'judul' => '', 'deskripsi' => ''];
$articles_res = mysqli_query($conn, "SELECT * FROM articles ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Control Panel - Marketiva</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
    .card-studio { background-color: #FFFFFF; border-radius: 1rem; border: 1px solid #E2E8F0; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); }
    .input-studio { height: 46px; border-radius: 0.75rem; border: 1.5px solid #CBD5E1; padding: 0 1rem; width: 100%; font-size: 0.875rem; background-color: #FFFFFF; color: #1E293B; transition: all 0.2s; }
    .input-studio:focus { outline: none; border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    textarea.input-studio { height: auto; padding-top: 0.75rem; padding-bottom: 0.75rem; }
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between">

  <!-- TOPBAR -->
  <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200">
    <div class="max-w-[1360px] mx-auto px-6 h-20 flex items-center justify-between">
      <a href="admin-dashboard.php" class="flex items-center gap-3">
        <span class="font-extrabold text-2xl tracking-tight">
          <span class="text-[#F43F5E]">Mar</span><span class="text-[#3B82F6]">ke</span><span class="text-[#10B981]">ti</span><span class="text-[#F59E0B]">va</span>
        </span>
        <span class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 text-[10px] font-extrabold uppercase border border-indigo-200">Admin Panel</span>
      </a>
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 px-4 py-2 rounded-2xl">
          <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-xs"><?= strtoupper(substr($admin_name, 0, 1)); ?></div>
          <p class="text-xs font-extrabold text-slate-800"><?= htmlspecialchars($admin_name); ?></p>
        </div>
        <a href="login.php?action=logout" class="px-4 py-2.5 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 font-bold text-xs transition-all flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[18px]">logout</span> Keluar
        </a>
      </div>
    </div>
  </header>

  <!-- MAIN CONTAINER -->
  <main class="max-w-[1360px] mx-auto px-6 py-8 w-full flex-1">

    <?php if (!empty($msg_success)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
        <span class="material-symbols-outlined text-[20px]">check_circle</span> <?= htmlspecialchars($msg_success); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($msg_error)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
        <span class="material-symbols-outlined text-[20px]">error</span> <?= htmlspecialchars($msg_error); ?>
      </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
      <div class="card-studio flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center"><span class="material-symbols-outlined text-[26px]">design_services</span></div>
        <div><p class="text-[11px] font-extrabold text-slate-400 uppercase">Total Layanan</p><p class="text-2xl font-extrabold text-slate-900"><?= $count_services; ?></p></div>
      </div>
      <div class="card-studio flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><span class="material-symbols-outlined text-[26px]">shopping_bag</span></div>
        <div><p class="text-[11px] font-extrabold text-slate-400 uppercase">Pesanan Masuk</p><p class="text-2xl font-extrabold text-slate-900"><?= $count_orders; ?></p></div>
      </div>
      <div class="card-studio flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center"><span class="material-symbols-outlined text-[26px]">groups</span></div>
        <div><p class="text-[11px] font-extrabold text-slate-400 uppercase">Pelanggan UMKM</p><p class="text-2xl font-extrabold text-slate-900"><?= $count_users; ?></p></div>
      </div>
    </div>

    <!-- GRID LAYOUT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

      <!-- SIDEBAR -->
      <div class="lg:col-span-3 card-studio space-y-4 p-4">
        <div class="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-indigo-950 text-white space-y-3 shadow-sm">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-extrabold text-base shadow-md">
              <?= strtoupper(substr($admin_name, 0, 1)); ?>
            </div>
            <div class="overflow-hidden">
              <p class="text-xs font-extrabold truncate"><?= htmlspecialchars($admin_name); ?></p>
              <p class="text-[10px] text-indigo-300 font-semibold flex items-center gap-1 mt-0.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block animate-pulse"></span> Super Administrator
              </p>
            </div>
          </div>
        </div>

        <div class="space-y-1">
          <p class="px-3 py-2 text-[10px] font-extrabold text-slate-400 uppercase">Navigasi Utama</p>
          <button onclick="switchAdminTab('services')" id="btnTabServices" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all">
            <span class="material-symbols-outlined text-[20px]">inventory_2</span> Katalog Layanan
          </button>
          <button onclick="openAddForm()" id="btnTabAddService" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">add_circle</span> Tambah Layanan Baru
          </button>
          <button onclick="switchAdminTab('orders')" id="btnTabOrders" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">shopping_cart</span> Pesanan Masuk
          </button>
          <button onclick="switchAdminTab('users')" id="btnTabUsers" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">group</span> Data Pelanggan
          </button>

          <!-- TAB KELOLA SLIDES BERANDA -->
          <button onclick="switchAdminTab('slides')" id="btnTabSlides" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">view_carousel</span> Hero Slides Beranda
          </button>

          <!-- TAB KELOLA PAKET HARGA -->
          <button onclick="switchAdminTab('paket')" id="btnTabPaket" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">payments</span> Kelola Paket Harga
          </button>

          <!-- TAB KELOLA BANNER CTA -->
          <button onclick="switchAdminTab('cta')" id="btnTabCta" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">campaign</span> Kelola Banner CTA
          </button>

          <!-- TAB KELOLA ARTIKEL & TIPS -->
          <button onclick="switchAdminTab('articles')" id="btnTabArticles" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">article</span> Kelola Artikel
          </button>

          <!-- TAB KELOLA TENTANG KAMI -->
          <button onclick="switchAdminTab('about')" id="btnTabAbout" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">info</span> Kelola Tentang Kami
          </button>

          <button onclick="switchAdminTab('profile')" id="btnTabProfile" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">manage_accounts</span> Pengaturan Profil
          </button>
        </div>

        <div class="pt-3 space-y-1 border-t border-slate-100">
          <p class="px-3 py-2 text-[10px] font-extrabold text-slate-400 uppercase">Pintasan Website</p>
          <a href="index.php" target="_blank" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">home</span> Lihat Beranda
          </a>
          <a href="catalog.php" target="_blank" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-blue-600 hover:bg-blue-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">open_in_new</span> Pratinjau Katalog
          </a>
          <a href="article.php" target="_blank" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">article</span> Pratinjau Artikel
          </a>
          <a href="about.php" target="_blank" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-indigo-600 hover:bg-indigo-50 transition-all">
            <span class="material-symbols-outlined text-[20px]">visibility</span> Pratinjau Tentang Kami
          </a>
        </div>
      </div>

      <!-- KONTEN KANAN -->
      <div class="lg:col-span-9 space-y-6">

        <!-- TAB 1: KATALOG & PENCARIAN -->
        <div id="adminTabServices" class="card-studio space-y-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
              <h2 class="text-xl font-extrabold text-slate-900">Kelola Katalog Layanan</h2>
              <p class="text-slate-500 text-xs mt-0.5">Daftar paket & jasa digital marketing.</p>
            </div>
            <div class="flex items-center gap-3">
              <input type="text" id="searchCatalog" onkeyup="filterCatalog()" placeholder="Cari layanan..." class="h-10 px-3 text-xs border border-slate-300 rounded-xl outline-none focus:border-indigo-500 w-48">
              <button onclick="openAddForm()" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-2 shadow-md whitespace-nowrap">
                <span class="material-symbols-outlined text-[18px]">add</span> Tambah
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full text-left text-xs" id="catalogTable">
              <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 uppercase font-extrabold text-[10px]">
                <tr>
                  <th class="py-3.5 px-4">Gambar</th>
                  <th class="py-3.5 px-4">Layanan</th>
                  <th class="py-3.5 px-4">Kategori</th>
                  <th class="py-3.5 px-4">Harga</th>
                  <th class="py-3.5 px-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                <?php if ($services_res && mysqli_num_rows($services_res) > 0): ?>
                  <?php while ($s = mysqli_fetch_assoc($services_res)): ?>
                    <tr class="hover:bg-slate-50 transition-colors service-row">
                      <td class="py-3 px-4">
                        <div class="w-12 h-10 rounded-lg bg-slate-100 overflow-hidden border border-slate-200 flex-shrink-0">
                          <?php if (!empty($s['gambar'])): ?>
                            <img src="<?= htmlspecialchars($s['gambar']); ?>" class="w-full h-full object-cover">
                          <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-slate-400"><span class="material-symbols-outlined text-[16px]">image</span></div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="py-3 px-4 font-bold text-slate-900 service-name"><?= htmlspecialchars($s['nama_layanan']); ?></td>
                      <td class="py-3 px-4"><span class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 font-extrabold text-[10px]"><?= htmlspecialchars($s['nama_kategori'] ?? 'Umum'); ?></span></td>
                      <td class="py-3 px-4 font-bold text-emerald-600">Rp <?= number_format($s['harga'], 0, ',', '.'); ?></td>
                      <td class="py-3 px-4 text-center flex items-center justify-center gap-1.5">
                        <button onclick='openEditForm(<?= json_encode($s); ?>)' class="p-2 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 inline-flex items-center" title="Edit">
                          <span class="material-symbols-outlined text-[16px]">edit</span>
                        </button>
                        <a href="admin-dashboard.php?delete_service=<?= $s['id']; ?>" onclick="return confirm('Yakin ingin menghapus layanan ini?')" class="p-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 inline-flex items-center" title="Hapus">
                          <span class="material-symbols-outlined text-[16px]">delete</span>
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="5" class="py-12 text-center text-slate-400 font-bold">Belum ada data layanan di database.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TAB 2: FORM TAMBAH / EDIT LAYANAN -->
        <div id="adminTabAddService" class="card-studio space-y-6 hidden">
          <div class="border-b border-slate-100 pb-4">
            <h2 id="formTitle" class="text-xl font-extrabold text-slate-900">Input Layanan Baru</h2>
            <p id="formSubtitle" class="text-slate-500 text-xs mt-0.5">Tambah kategori baru atau unggah paket jasa digital dari perangkat Anda.</p>
          </div>

          <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-100 space-y-3">
            <p class="text-xs font-extrabold text-indigo-900 uppercase">Tambah Kategori Baru</p>
            <form method="POST" action="admin-dashboard.php" class="flex gap-3">
              <input type="hidden" name="action_add_category" value="1">
              <input type="text" name="nama_kategori" class="input-studio flex-1 bg-white" placeholder="Nama Kategori (misal: Bundling Spesial)" required>
              <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs whitespace-nowrap shadow-md">
                + Tambah Kategori
              </button>
            </form>
          </div>

          <form method="POST" action="admin-dashboard.php" enctype="multipart/form-data" class="space-y-4 pt-2">
            <input type="hidden" name="action_save_service" value="1">
            <input type="hidden" name="service_id" id="service_id" value="">
            <input type="hidden" name="old_gambar" id="old_gambar" value="">

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nama Layanan / Paket</label>
              <input type="text" name="nama_layanan" id="nama_layanan" class="input-studio" placeholder="Contoh: Paket Bundling Instagram UMKM" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Pilih Kategori</label>
                <select name="kategori_id" id="kategori_id" class="input-studio" required>
                  <option value="">-- Pilih Kategori --</option>
                  <?php
                  mysqli_data_seek($categories_res, 0);
                  while ($cat = mysqli_fetch_assoc($categories_res)):
                  ?>
                    <option value="<?= $cat['id']; ?>"><?= htmlspecialchars($cat['nama_kategori']); ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Harga (Rp)</label>
                <input type="number" name="harga" id="harga" class="input-studio" placeholder="500000" min="0" required>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Pilih File Gambar</label>
              <input type="file" name="gambar_file" accept="image/*" class="input-studio py-2.5 file:mr-4 file:py-1 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700">
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Deskripsi Lengkap</label>
              <textarea name="deskripsi" id="deskripsi" rows="4" class="input-studio" placeholder="Jelaskan detail layanan..."></textarea>
            </div>

            <div class="pt-2 flex gap-3">
              <button type="submit" id="submitBtnText" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md">Simpan Layanan</button>
              <button type="button" onclick="switchAdminTab('services')" class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">Batal</button>
            </div>
          </form>
        </div>

        <!-- TAB 3: PESANAN MASUK (FILTER STATUS + MODERN AKSI & POPUP KONFIRMASI) -->
        <div id="adminTabOrders" class="card-studio space-y-6 hidden">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
              <h2 class="text-xl font-extrabold text-slate-900">Daftar Pesanan Masuk</h2>
              <p class="text-slate-500 text-xs mt-0.5">Filter pesanan berdasarkan status transaksi dan perbarui progres pengerjaan.</p>
            </div>
            <div class="relative w-full md:w-64">
              <span class="material-symbols-outlined text-[18px] text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2">search</span>
              <input type="text" id="orderSearchInput" onkeyup="applyOrderFilters()" placeholder="Cari klien, layanan, ID..." class="h-10 pl-10 pr-3 text-xs border border-slate-300 rounded-xl outline-none focus:border-indigo-500 w-full bg-white">
            </div>
          </div>

          <!-- CONTROL BAR FILTER STATUS -->
          <div class="flex flex-wrap items-center gap-2 bg-slate-50 p-2 rounded-2xl border border-slate-200/80" id="orderFilterBar">
            <button type="button" data-filter="all" onclick="setOrderStatusFilter('all')" class="order-filter-pill px-3.5 py-2 rounded-xl text-xs font-extrabold bg-slate-900 text-white shadow-xs transition-all flex items-center gap-1.5">
              <span>Semua</span>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white/20 text-white"><?= $order_counts['all']; ?></span>
            </button>
            <button type="button" data-filter="Menunggu Pembayaran" onclick="setOrderStatusFilter('Menunggu Pembayaran')" class="order-filter-pill px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-white hover:text-slate-900 transition-all flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-amber-500"></span>
              <span>Menunggu Pembayaran</span>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200/80 text-slate-700"><?= $order_counts['Menunggu Pembayaran']; ?></span>
            </button>
            <button type="button" data-filter="Menunggu Konfirmasi" onclick="setOrderStatusFilter('Menunggu Konfirmasi')" class="order-filter-pill px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-white hover:text-slate-900 transition-all flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-orange-500"></span>
              <span>Menunggu Konfirmasi</span>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200/80 text-slate-700"><?= $order_counts['Menunggu Konfirmasi']; ?></span>
            </button>
            <button type="button" data-filter="Diproses" onclick="setOrderStatusFilter('Diproses')" class="order-filter-pill px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-white hover:text-slate-900 transition-all flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-blue-500"></span>
              <span>Diproses</span>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200/80 text-slate-700"><?= $order_counts['Diproses']; ?></span>
            </button>
            <button type="button" data-filter="Selesai" onclick="setOrderStatusFilter('Selesai')" class="order-filter-pill px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-white hover:text-slate-900 transition-all flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>Selesai</span>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200/80 text-slate-700"><?= $order_counts['Selesai']; ?></span>
            </button>
            <button type="button" data-filter="Dibatalkan" onclick="setOrderStatusFilter('Dibatalkan')" class="order-filter-pill px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-white hover:text-slate-900 transition-all flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-rose-500"></span>
              <span>Dibatalkan</span>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200/80 text-slate-700"><?= $order_counts['Dibatalkan']; ?></span>
            </button>
          </div>

          <div class="overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 uppercase font-extrabold text-[10px]">
                <tr>
                  <th class="py-3.5 px-4">Klien / Usaha</th>
                  <th class="py-3.5 px-4">Layanan Dipesan</th>
                  <th class="py-3.5 px-4">Kontak</th>
                  <th class="py-3.5 px-4">Status</th>
                  <th class="py-3.5 px-4 text-center">Aksi / Update Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-700" id="ordersTableBody">
                <?php if (count($orders_list) > 0): ?>
                  <?php foreach ($orders_list as $ord): ?>
                    <?php
                      $st_val   = trim($ord['status'] ?? 'Menunggu Pembayaran');
                      $kode_ord = $ord['order_id'] ?? ('#' . $ord['id']);
                      $badge_cls = 'bg-amber-50 text-amber-700 border-amber-200';
                      $dot_cls   = 'bg-amber-500';
                      if ($st_val === 'Selesai') {
                          $badge_cls = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                          $dot_cls   = 'bg-emerald-500';
                      } elseif ($st_val === 'Diproses') {
                          $badge_cls = 'bg-blue-50 text-blue-700 border-blue-200';
                          $dot_cls   = 'bg-blue-500';
                      } elseif ($st_val === 'Menunggu Konfirmasi') {
                          $badge_cls = 'bg-orange-50 text-orange-700 border-orange-200';
                          $dot_cls   = 'bg-orange-500';
                      } elseif ($st_val === 'Dibatalkan') {
                          $badge_cls = 'bg-rose-50 text-rose-700 border-rose-200';
                          $dot_cls   = 'bg-rose-500';
                      }
                      $search_blob = strtolower(
                          ($ord['nama_lengkap'] ?? '') . ' ' .
                          ($ord['nama_usaha'] ?? '') . ' ' .
                          ($ord['nama_layanan'] ?? '') . ' ' .
                          ($ord['user_email'] ?? '') . ' ' .
                          $kode_ord
                      );
                    ?>
                    <tr class="hover:bg-slate-50/90 transition-colors order-data-row"
                        data-status="<?= htmlspecialchars($st_val); ?>"
                        data-search="<?= htmlspecialchars($search_blob); ?>">
                      <td class="py-3.5 px-4">
                        <p class="font-extrabold text-slate-900"><?= htmlspecialchars($ord['nama_lengkap'] ?: 'Pelanggan Marketiva'); ?></p>
                        <p class="text-[11px] font-semibold text-indigo-600"><?= htmlspecialchars($ord['nama_usaha'] ?: 'UMKM Mitra'); ?></p>
                        <p class="text-[10px] font-mono text-slate-400 mt-0.5"><?= htmlspecialchars($kode_ord); ?></p>
                      </td>
                      <td class="py-3.5 px-4">
                        <p class="font-bold text-slate-900"><?= htmlspecialchars($ord['nama_layanan']); ?></p>
                        <div class="flex items-center gap-2 mt-1">
                          <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold"><?= htmlspecialchars($ord['kategori'] ?? 'Digital Marketing'); ?></span>
                          <span class="text-[11px] font-extrabold text-emerald-600">Rp <?= number_format(floatval($ord['harga'] ?? 0), 0, ',', '.'); ?></span>
                        </div>
                      </td>
                      <td class="py-3.5 px-4">
                        <p class="text-slate-700 font-semibold"><?= htmlspecialchars($ord['user_email'] ?? '-'); ?></p>
                        <?php if (!empty($ord['whatsapp'])): ?>
                          <p class="text-[11px] text-slate-400 mt-0.5"><?= htmlspecialchars($ord['whatsapp']); ?></p>
                        <?php endif; ?>
                      </td>
                      <td class="py-3.5 px-4">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border font-extrabold text-[11px] <?= $badge_cls; ?>">
                          <span class="w-1.5 h-1.5 rounded-full <?= $dot_cls; ?>"></span>
                          <?= htmlspecialchars($st_val); ?>
                        </span>
                      </td>
                      <td class="py-3.5 px-4 text-center">
                        <div class="inline-flex items-center justify-center gap-2">
                          <button type="button" onclick='openOrderDetail(<?= json_encode($ord); ?>)' class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs inline-flex items-center gap-1 transition-all">
                            <span class="material-symbols-outlined text-[15px]">visibility</span>
                            <span>Detail</span>
                          </button>
                          <button type="button" onclick='openStatusUpdateModal(<?= json_encode($ord); ?>)' class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs inline-flex items-center gap-1.5 transition-all">
                            <span class="material-symbols-outlined text-[15px]">sync_alt</span>
                            <span>Ubah Status</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="ordersEmptyFilterRow" class="hidden">
                    <td colspan="5" class="py-12 text-center text-slate-400 font-bold">
                      Tidak ada pesanan yang sesuai dengan filter status atau pencarian ini.
                    </td>
                  </tr>
                <?php else: ?>
                  <tr><td colspan="5" class="py-10 text-center text-slate-400 font-bold">Belum ada pesanan masuk dari katalog.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- PANEL DETAIL PESANAN (TERINTEGRASI DI ADMIN DASHBOARD) -->
        <div id="adminTabOrderDetail" class="card-studio space-y-6 hidden">
          <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div class="flex flex-wrap items-center gap-3">
              <h2 id="detailOrderTitle" class="text-xl font-extrabold text-slate-900">Detail Pesanan</h2>
              <span id="detailOrderStatusBadge" class="px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700">
                Menunggu Konfirmasi
              </span>
            </div>
            <button type="button" onclick="switchAdminTab('orders')" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold inline-flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <div class="lg:col-span-8 space-y-6">
              <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-4">
                <h3 class="text-sm font-extrabold text-slate-900 border-b border-slate-200 pb-2.5">
                  Status & Verifikasi Pembayaran
                </h3>
                <div class="p-4 rounded-xl bg-white border border-slate-200 text-xs space-y-1">
                  <p class="font-extrabold text-slate-900">Metode Pembayaran</p>
                  <p id="detailMetodeBayar" class="text-slate-600">Midtrans Payment Gateway / Transfer Manual</p>
                </div>
                <div class="p-4 rounded-xl bg-white border border-slate-200 text-xs space-y-1">
                  <p class="font-extrabold text-slate-900">Bukti Pembayaran</p>
                  <p id="detailBuktiBayar" class="text-slate-500">Status transaksi tercatat otomatis di database pesanan.</p>
                </div>
              </div>

              <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-4">
                <h3 class="text-sm font-extrabold text-slate-900 border-b border-slate-200 pb-2.5">
                  Daftar Layanan yang Dipesan
                </h3>
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
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
                          <p id="detailNamaLayanan" class="font-bold text-slate-900">-</p>
                          <p id="detailKategori" class="text-[11px] text-slate-500">Kategori: -</p>
                        </td>
                        <td id="detailHargaSatuan" class="py-3.5 px-4 font-semibold">Rp 0</td>
                        <td class="py-3.5 px-4">1</td>
                        <td id="detailSubtotal" class="py-3.5 px-4 text-right font-extrabold text-slate-900">Rp 0</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="lg:col-span-4 space-y-6">
              <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-4">
                <h3 class="text-sm font-extrabold text-slate-900 border-b border-slate-200 pb-2.5">
                  Kontak Pembeli
                </h3>
                <div class="space-y-2.5 text-xs">
                  <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Nama Pemesan</span>
                    <span id="detailNamaPemesan" class="font-bold text-slate-900">-</span>
                  </div>
                  <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Nama Usaha</span>
                    <span id="detailNamaUsaha" class="font-semibold text-slate-700">-</span>
                  </div>
                  <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Email</span>
                    <span id="detailEmail" class="font-semibold text-slate-700">-</span>
                  </div>
                  <div>
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">No. Handphone / WhatsApp</span>
                    <span id="detailWhatsapp" class="font-semibold text-slate-700">-</span>
                  </div>
                </div>
                <a id="detailWaLink" href="#" target="_blank" rel="noopener noreferrer" class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold flex items-center justify-center gap-2">
                  <span class="material-symbols-outlined text-[16px]">chat</span> Hubungi via WhatsApp
                </a>
              </div>

              <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-4">
                <h3 class="text-sm font-extrabold text-slate-900 border-b border-slate-200 pb-2.5">
                  Update Status Pesanan
                </h3>
                <div class="space-y-3">
                  <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Status Pesanan</label>
                    <select id="detailStatusSelect" class="input-studio bg-white text-xs font-bold">
                      <option value="Menunggu Pembayaran">Menunggu Pembayaran</option>
                      <option value="Menunggu Konfirmasi">Menunggu Konfirmasi</option>
                      <option value="Diproses">Diproses</option>
                      <option value="Selesai">Selesai</option>
                      <option value="Dibatalkan">Dibatalkan</option>
                    </select>
                  </div>
                  <button type="button" onclick="saveOrderDetailStatus()" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                    Simpan Status
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 4: DATA PELANGGAN -->
        <div id="adminTabUsers" class="card-studio space-y-6 hidden">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
              <h2 class="text-xl font-extrabold text-slate-900">Data Pelanggan UMKM</h2>
              <p class="text-slate-500 text-xs mt-0.5">Ringkasan KPI profil usaha, kontak lengkap, dan akses pratinjau profil read-only pelanggan.</p>
            </div>
            <div class="flex items-center gap-3">
              <div class="relative">
                <span class="material-symbols-outlined text-[18px] text-slate-400 absolute left-3 top-1/2 -translate-y-1/2">search</span>
                <input type="text" id="customerSearchInput" onkeyup="filterCustomerCards()" placeholder="Cari pelanggan / bisnis..." class="h-10 pl-9 pr-3 text-xs border border-slate-300 rounded-xl outline-none focus:border-indigo-500 w-56 bg-white">
              </div>
              <span class="px-3 py-2 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-extrabold whitespace-nowrap">
                <?= count($customers_list); ?> Pelanggan
              </span>
            </div>
          </div>

          <?php if (count($customers_list) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5" id="customersKpiGrid">
              <?php
                $avatar_palettes = [
                    'from-rose-500 to-pink-600',
                    'from-indigo-500 to-blue-600',
                    'from-emerald-500 to-teal-600',
                    'from-amber-500 to-orange-600',
                ];
              ?>
              <?php foreach ($customers_list as $idx => $u): ?>
                <?php
                  $palette    = $avatar_palettes[$idx % count($avatar_palettes)];
                  $bisnis_lbl = !empty(trim((string)($u['nama_umkm'] ?? ''))) ? $u['nama_umkm'] : 'Belum mengisi nama bisnis';
                  $wa_clean   = preg_replace('/[^0-9]/', '', (string)($u['whatsapp'] ?? ''));
                  if (strpos($wa_clean, '0') === 0) {
                      $wa_clean = '62' . substr($wa_clean, 1);
                  }
                  $cust_search = strtolower(
                      ($u['nama_lengkap'] ?? '') . ' ' .
                      ($u['nama_umkm'] ?? '') . ' ' .
                      ($u['email'] ?? '') . ' ' .
                      ($u['whatsapp'] ?? '') . ' ' .
                      ($u['alamat'] ?? '')
                  );
                ?>
                <div class="customer-kpi-card rounded-2xl border border-slate-200 bg-white hover:border-indigo-300 hover:shadow-md transition-all p-5 flex flex-col justify-between gap-4"
                     data-search="<?= htmlspecialchars($cust_search); ?>">
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3.5 min-w-0">
                      <div class="w-12 h-12 rounded-2xl bg-gradient-to-br <?= $palette; ?> text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                        <span class="material-symbols-outlined text-[24px]">person</span>
                      </div>
                      <div class="min-w-0">
                        <div class="flex items-center gap-2">
                          <h3 class="text-sm font-extrabold text-slate-900 truncate"><?= htmlspecialchars($u['nama_lengkap']); ?></h3>
                          <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold">#<?= intval($u['id']); ?></span>
                        </div>
                        <p class="text-xs font-bold text-indigo-600 flex items-center gap-1 mt-0.5 truncate">
                          <span class="material-symbols-outlined text-[15px]">storefront</span>
                          <span class="truncate"><?= htmlspecialchars($bisnis_lbl); ?></span>
                        </p>
                      </div>
                    </div>

                    <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold flex-shrink-0">
                      UMKM Aktif
                    </span>
                  </div>

                  <div class="grid grid-cols-3 gap-2.5 p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-center">
                    <div>
                      <p class="text-[10px] font-bold text-slate-400 uppercase">Total Order</p>
                      <p class="text-sm font-extrabold text-slate-900 mt-0.5"><?= $u['total_ord']; ?></p>
                    </div>
                    <div class="border-x border-slate-200">
                      <p class="text-[10px] font-bold text-slate-400 uppercase">On Progress</p>
                      <p class="text-sm font-extrabold text-amber-600 mt-0.5"><?= $u['aktif_ord']; ?></p>
                    </div>
                    <div>
                      <p class="text-[10px] font-bold text-slate-400 uppercase">Selesai</p>
                      <p class="text-sm font-extrabold text-emerald-600 mt-0.5"><?= $u['selesai_ord']; ?></p>
                    </div>
                  </div>

                  <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between gap-2 py-1 border-b border-slate-100">
                      <span class="text-slate-400 font-semibold flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px]">mail</span> Email
                      </span>
                      <span class="font-bold text-slate-800 truncate"><?= htmlspecialchars($u['email']); ?></span>
                    </div>
                    <div class="flex items-center justify-between gap-2 py-1 border-b border-slate-100">
                      <span class="text-slate-400 font-semibold flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px]">call</span> WhatsApp
                      </span>
                      <span class="font-bold text-slate-800"><?= htmlspecialchars(!empty($u['whatsapp']) ? $u['whatsapp'] : '-'); ?></span>
                    </div>
                    <div class="flex items-start justify-between gap-2 py-1">
                      <span class="text-slate-400 font-semibold flex items-center gap-1.5 flex-shrink-0">
                        <span class="material-symbols-outlined text-[15px]">location_on</span> Alamat
                      </span>
                      <span class="font-semibold text-slate-600 text-right line-clamp-2">
                        <?= htmlspecialchars(!empty(trim((string)($u['alamat'] ?? ''))) ? $u['alamat'] : 'Alamat usaha belum dilengkapi'); ?>
                      </span>
                    </div>
                  </div>

                  <div class="pt-2 border-t border-slate-100 flex items-center gap-2.5">
                    <a href="admin-dashboard.php?view_user=<?= intval($u['id']); ?>"
                       class="flex-1 py-2.5 px-3.5 rounded-xl bg-slate-900 hover:bg-indigo-600 text-white font-bold text-xs transition-all flex items-center justify-center gap-1.5 shadow-xs">
                      <span class="material-symbols-outlined text-[16px]">visibility</span>
                      <span>Lihat Profil User</span>
                    </a>

                    <?php if (!empty($wa_clean)): ?>
                      <a href="https://wa.me/<?= htmlspecialchars($wa_clean); ?>" target="_blank" rel="noopener noreferrer"
                         class="py-2.5 px-3.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 font-bold text-xs transition-all flex items-center justify-center gap-1"
                         title="Chat WhatsApp Pelanggan">
                        <span class="material-symbols-outlined text-[16px]">chat</span>
                        <span>WA</span>
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="py-12 text-center text-slate-400 font-bold bg-slate-50 rounded-2xl border border-dashed border-slate-200">
              Belum ada pelanggan terdaftar.
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB KELOLA HERO SLIDES BERANDA -->
        <div id="adminTabSlides" class="card-studio space-y-6 hidden">
          <div class="border-b border-slate-100 pb-4">
            <h2 class="text-xl font-extrabold text-slate-900">Kelola Hero Slide Banner (Beranda)</h2>
            <p class="text-slate-500 text-xs mt-0.5">Atur teks banner promosi utama yang tampil pada halaman depan index.php.</p>
          </div>

          <form method="POST" action="admin-dashboard.php" class="space-y-4">
            <input type="hidden" name="action_save_slide" value="1">
            <input type="hidden" name="slide_id" id="slide_id" value="">

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Badge Teks</label>
              <input type="text" name="badge" id="slide_badge" class="input-studio" placeholder="Contoh: 🚀 Partner Pertumbuhan Digital UMKM">
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Judul Utama Banner Slide</label>
              <input type="text" name="judul" id="slide_judul" class="input-studio" placeholder="Contoh: Bantu UMKM Tumbuh Lebih Besar di Dunia Digital." required>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Deskripsi Singkat Banner</label>
              <textarea name="deskripsi" id="slide_deskripsi" rows="3" class="input-studio" placeholder="Penjelasan promo/layanan..." required></textarea>
            </div>

            <div class="pt-2 flex gap-3">
              <button type="submit" id="slideSubmitBtn" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md">Simpan Banner Slide</button>
              <button type="button" onclick="resetSlideForm()" class="px-4 py-3 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs">Reset Form</button>
            </div>
          </form>

          <div class="overflow-x-auto rounded-2xl border border-slate-200 mt-6">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 uppercase font-extrabold text-[10px]">
                <tr>
                  <th class="py-3.5 px-4">Badge</th>
                  <th class="py-3.5 px-4">Judul Banner</th>
                  <th class="py-3.5 px-4">Deskripsi</th>
                  <th class="py-3.5 px-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                <?php if ($slides_res && mysqli_num_rows($slides_res) > 0): ?>
                  <?php while ($sl = mysqli_fetch_assoc($slides_res)): ?>
                    <tr class="hover:bg-slate-50">
                      <td class="py-3 px-4 font-bold text-rose-600"><?= htmlspecialchars($sl['badge']); ?></td>
                      <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($sl['judul']); ?></td>
                      <td class="py-3 px-4 text-slate-500 max-w-xs truncate"><?= htmlspecialchars($sl['deskripsi']); ?></td>
                      <td class="py-3 px-4 text-center flex items-center justify-center gap-1.5">
                        <button onclick='editSlide(<?= json_encode($sl); ?>)' class="p-2 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 inline-flex items-center" title="Edit">
                          <span class="material-symbols-outlined text-[16px]">edit</span>
                        </button>
                        <a href="admin-dashboard.php?delete_slide=<?= $sl['id']; ?>" onclick="return confirm('Hapus banner slide ini?')" class="p-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 inline-flex items-center" title="Hapus">
                          <span class="material-symbols-outlined text-[16px]">delete</span>
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TAB KELOLA PAKET HARGA -->
        <div id="adminTabPaket" class="card-studio space-y-6 hidden">
          <div class="border-b border-slate-100 pb-4">
            <h2 class="text-xl font-extrabold text-slate-900">Kelola Paket Harga (Beranda)</h2>
            <p class="text-slate-500 text-xs mt-0.5">Ubah nama paket, harga, atau daftar fitur layanan.</p>
          </div>

          <div class="space-y-6">
            <?php
            mysqli_data_seek($paket_res, 0);
            while ($pkt = mysqli_fetch_assoc($paket_res)):
            ?>
              <form method="POST" action="admin-dashboard.php" class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-4">
                <input type="hidden" name="action_save_paket" value="1">
                <input type="hidden" name="paket_id" value="<?= $pkt['id']; ?>">

                <div class="flex items-center justify-between">
                  <h3 class="text-sm font-extrabold text-slate-800">Edit Paket ID #<?= $pkt['id']; ?>: <?= htmlspecialchars($pkt['nama_paket']); ?></h3>
                  <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                    <input type="checkbox" name="rekomendasi" value="1" <?= $pkt['rekomendasi'] == 1 ? 'checked' : ''; ?> class="rounded accent-rose-600 w-4 h-4">
                    Jadikan Rekomendasi (⭐ Populer)
                  </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nama Paket</label>
                    <input type="text" name="nama_paket" value="<?= htmlspecialchars($pkt['nama_paket']); ?>" class="input-studio bg-white" required>
                  </div>
                  <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Sub Judul</label>
                    <input type="text" name="sub_judul" value="<?= htmlspecialchars($pkt['sub_judul']); ?>" class="input-studio bg-white" required>
                  </div>
                  <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Harga (Rp)</label>
                    <input type="number" name="harga" value="<?= $pkt['harga']; ?>" class="input-studio bg-white" min="0" required>
                  </div>
                </div>

                <div>
                  <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Daftar Fitur (Pisahkan dengan koma ,)</label>
                  <input type="text" name="fitur" value="<?= htmlspecialchars($pkt['fitur']); ?>" class="input-studio bg-white" required>
                </div>

                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md">
                  Simpan Perubahan Paket
                </button>
              </form>
            <?php endwhile; ?>
          </div>
        </div>

        <!-- TAB KELOLA BANNER CTA -->
        <div id="adminTabCta" class="card-studio space-y-6 hidden">
          <div class="border-b border-slate-100 pb-4">
            <h2 class="text-xl font-extrabold text-slate-900">Kelola Banner CTA Checkout</h2>
            <p class="text-slate-500 text-xs mt-0.5">Ubah teks ajakan (Call to Action) bagian bawah beranda.</p>
          </div>

          <form method="POST" action="admin-dashboard.php" class="space-y-4">
            <input type="hidden" name="action_save_cta" value="1">

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Badge Teks</label>
              <input type="text" name="cta_badge" value="<?= htmlspecialchars($cta_data['badge'] ?? ''); ?>" class="input-studio" required>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Judul Utama Banner</label>
              <input type="text" name="cta_judul" value="<?= htmlspecialchars($cta_data['judul'] ?? ''); ?>" class="input-studio" required>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Deskripsi Singkat</label>
              <textarea name="cta_deskripsi" rows="3" class="input-studio" required><?= htmlspecialchars($cta_data['deskripsi'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md">
              Simpan Banner CTA
            </button>
          </form>
        </div>

        <!-- TAB KELOLA ARTIKEL & TIPS -->
        <div id="adminTabArticles" class="card-studio space-y-6 hidden">
          <div class="border-b border-slate-100 pb-4">
            <h2 class="text-xl font-extrabold text-slate-900">Kelola Artikel & Tips Web</h2>
            <p class="text-slate-500 text-xs mt-0.5">Tambah artikel dengan filter kalender, dropdown kategori layanan, unggah gambar komputer, dan custom URL slug.</p>
          </div>

          <form method="POST" action="admin-dashboard.php" enctype="multipart/form-data" class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-4">
            <input type="hidden" name="action_tambah_artikel" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Judul Artikel</label>
                <input type="text" name="title" class="input-studio bg-white" placeholder="Contoh: Tips SEO Website UMKM" required>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Custom URL Slug (Link Artikel)</label>
                <input type="text" name="slug" class="input-studio bg-white" placeholder="Contoh: tips-seo-website (Boleh dikosongkan)">
                <span class="text-[10px] text-slate-400 mt-0.5 block">Jika kosong, URL akan digenerate otomatis dari judul.</span>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Kategori Layanan Jasa</label>
                <select name="category" class="input-studio bg-white" required>
                  <option value="">-- Pilih Kategori --</option>
                  <option value="Content Marketing">Content Marketing</option>
                  <option value="Social Media Management">Social Media Management</option>
                  <option value="Digital Advertising">Digital Advertising (Ads)</option>
                  <option value="Business Growth">Business Growth & Strategy</option>
                  <option value="Web & Digital Tips">Web & Digital Tips</option>
                </select>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Tanggal Publikasi (Filter Kalender)</label>
                <input type="date" name="date" class="input-studio bg-white" required>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pilih File Gambar Komputer</label>
                <input type="file" name="image_file" accept="image/*" class="input-studio bg-white py-2 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700" required>
              </div>
            </div>

            <div>
              <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Ringkasan / Excerpt</label>
              <textarea name="excerpt" rows="2" class="input-studio bg-white" placeholder="Tulis ringkasan singkat artikel..." required></textarea>
            </div>

            <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md flex items-center gap-2">
              <span class="material-symbols-outlined text-[18px]">publish</span> Publikasikan Artikel Baru
            </button>
          </form>

          <div class="overflow-x-auto rounded-2xl border border-slate-200 mt-6">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 uppercase font-extrabold text-[10px]">
                <tr>
                  <th class="py-3.5 px-4">Gambar</th>
                  <th class="py-3.5 px-4">Judul & Link URL (Slug)</th>
                  <th class="py-3.5 px-4">Kategori Layanan</th>
                  <th class="py-3.5 px-4">Tanggal</th>
                  <th class="py-3.5 px-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                <?php if ($articles_res && mysqli_num_rows($articles_res) > 0): ?>
                  <?php while ($art = mysqli_fetch_assoc($articles_res)): ?>
                    <tr class="hover:bg-slate-50">
                      <td class="py-3 px-4">
                        <div class="w-12 h-10 rounded-lg bg-slate-100 overflow-hidden border border-slate-200">
                          <img src="<?= htmlspecialchars($art['image']); ?>" class="w-full h-full object-cover">
                        </div>
                      </td>
                      <td class="py-3 px-4">
                        <p class="font-bold text-slate-900"><?= htmlspecialchars($art['title']); ?></p>
                        <p class="text-[10px] text-indigo-600 font-mono">detail-artikel.php?slug=<?= htmlspecialchars($art['slug']); ?></p>
                      </td>
                      <td class="py-3 px-4"><span class="px-2.5 py-1 rounded-md bg-rose-50 text-rose-600 font-extrabold text-[10px]"><?= htmlspecialchars($art['category']); ?></span></td>
                      <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($art['date']); ?></td>
                      <td class="py-3 px-4 text-center">
                        <a href="admin-dashboard.php?delete_article=<?= $art['id']; ?>" onclick="return confirm('Hapus artikel ini?')" class="p-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 inline-flex items-center gap-1 font-bold" title="Hapus">
                          <span class="material-symbols-outlined text-[16px]">delete</span> Hapus
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="5" class="py-8 text-center text-slate-400 font-bold">Belum ada artikel tersimpan di database.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TAB KELOLA TENTANG KAMI (TERSINKRON KE DB about_us) -->
        <div id="adminTabAbout" class="card-studio space-y-6 hidden">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
              <h2 class="text-xl font-extrabold text-slate-900">Kelola Halaman "Tentang Kami"</h2>
              <p class="text-slate-500 text-xs mt-0.5">Perbarui profil agensi, tab audiens, 3 kata utama, rincian 3 layanan, serta visi & misi yang tersinkron dengan about.php.</p>
            </div>
            <a href="about.php" target="_blank" class="px-4 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs inline-flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[16px]">open_in_new</span> Pratinjau about.php
            </a>
          </div>

          <form method="POST" action="admin-dashboard.php" class="space-y-6">
            <input type="hidden" name="action_update_about" value="1">

            <!-- 1. JUDUL & DESKRIPSI UTAMA -->
            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-200 space-y-4">
              <p class="text-xs font-extrabold text-slate-800 uppercase">1. Profil Utama</p>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Judul Utama Halaman</label>
                <input type="text" name="about_judul" value="<?= htmlspecialchars($about_data['judul'] ?? 'Tentang Marketiva Digital Agency'); ?>" class="input-studio bg-white" required>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Deskripsi Profil Utama</label>
                <textarea name="about_deskripsi" rows="4" class="input-studio bg-white" required><?= htmlspecialchars($about_data['deskripsi'] ?? ''); ?></textarea>
              </div>
            </div>

            <!-- 2. TAB INTERAKTIF DUA SISI -->
            <div class="p-4 rounded-2xl bg-rose-50/40 border border-rose-200/70 space-y-4">
              <p class="text-xs font-extrabold text-[#E11D48] uppercase">2. Tab Interaktif (Pelaku UMKM vs Tim Marketiva)</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-white border border-slate-200 space-y-3">
                  <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Label Tab Kiri</label>
                    <input type="text" name="tab_umkm_label" value="<?= htmlspecialchars($about_data['tab_umkm_label'] ?? 'Pelaku UMKM & Brand'); ?>" class="input-studio" required>
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Deskripsi Tab Kiri</label>
                    <textarea name="tab_umkm_desc" rows="3" class="input-studio"><?= htmlspecialchars($about_data['tab_umkm_desc'] ?? ''); ?></textarea>
                  </div>
                </div>

                <div class="p-4 rounded-xl bg-white border border-slate-200 space-y-3">
                  <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Label Tab Kanan</label>
                    <input type="text" name="tab_agency_label" value="<?= htmlspecialchars($about_data['tab_agency_label'] ?? 'Tim & Sistem Marketiva'); ?>" class="input-studio" required>
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Deskripsi Tab Kanan</label>
                    <textarea name="tab_agency_desc" rows="3" class="input-studio"><?= htmlspecialchars($about_data['tab_agency_desc'] ?? ''); ?></textarea>
                  </div>
                </div>
              </div>
            </div>

            <!-- 3. 3 KATA UTAMA & RINCIAN 3 LAYANAN -->
            <div class="p-4 rounded-2xl bg-indigo-50/40 border border-indigo-200/70 space-y-4">
              <p class="text-xs font-extrabold text-indigo-700 uppercase">3. Kutipan 3 Kata Utama & Rincian 3 Layanan</p>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Kutipan 3 Kata Utama</label>
                <input type="text" name="three_words_quote" value="<?= htmlspecialchars($about_data['three_words_quote'] ?? 'KREATIF, TERUKUR, DAN TERPERCAYA — 3 KATA YANG PALING TEPAT MENGGAMBARKAN MARKETIVA'); ?>" class="input-studio bg-white" required>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-xl bg-white border border-slate-200 space-y-3">
                  <span class="inline-block px-2.5 py-0.5 rounded bg-sky-100 text-sky-700 text-[10px] font-extrabold uppercase">Social Media Management</span>
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Judul Rincian</label>
                    <input type="text" name="smm_title" value="<?= htmlspecialchars($about_data['smm_title'] ?? 'Pengelolaan Akun Instagram & TikTok Terjadwal'); ?>" class="input-studio">
                  </div>
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Deskripsi Rincian</label>
                    <textarea name="smm_desc" rows="3" class="input-studio"><?= htmlspecialchars($about_data['smm_desc'] ?? ''); ?></textarea>
                  </div>
                </div>

                <div class="p-4 rounded-xl bg-white border border-slate-200 space-y-3">
                  <span class="inline-block px-2.5 py-0.5 rounded bg-rose-100 text-[#E11D48] text-[10px] font-extrabold uppercase">Content Creation & Design</span>
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Judul Rincian</label>
                    <input type="text" name="content_title" value="<?= htmlspecialchars($about_data['content_title'] ?? 'Desain Visual Kreatif, Carousel & Video Pendek Reels'); ?>" class="input-studio">
                  </div>
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Deskripsi Rincian</label>
                    <textarea name="content_desc" rows="3" class="input-studio"><?= htmlspecialchars($about_data['content_desc'] ?? ''); ?></textarea>
                  </div>
                </div>

                <div class="p-4 rounded-xl bg-white border border-slate-200 space-y-3">
                  <span class="inline-block px-2.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-extrabold uppercase">Digital Ads</span>
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Judul Rincian</label>
                    <input type="text" name="ads_title" value="<?= htmlspecialchars($about_data['ads_title'] ?? 'Iklan Berbayar Meta Ads & TikTok Ads Tertarget'); ?>" class="input-studio">
                  </div>
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Deskripsi Rincian</label>
                    <textarea name="ads_desc" rows="3" class="input-studio"><?= htmlspecialchars($about_data['ads_desc'] ?? ''); ?></textarea>
                  </div>
                </div>
              </div>
            </div>

            <!-- 4. VISI & MISI -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Visi Perusahaan</label>
                <textarea name="about_visi" rows="3" class="input-studio"><?= htmlspecialchars($about_data['visi'] ?? ''); ?></textarea>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Misi Perusahaan</label>
                <textarea name="about_misi" rows="3" class="input-studio"><?= htmlspecialchars($about_data['misi'] ?? ''); ?></textarea>
              </div>
            </div>

            <div class="pt-2">
              <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md">Simpan Perubahan Tentang Kami</button>
            </div>
          </form>
        </div>

        <!-- TAB 5: PENGATURAN PROFIL & LOG AKTIVITAS -->
        <div id="adminTabProfile" class="card-studio space-y-8 hidden">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
              <h2 class="text-xl font-extrabold text-slate-900">Pengaturan Profil & Log Aktivitas</h2>
              <p class="text-slate-500 text-xs mt-0.5">Kelola identitas akun administrator dan pantau rekam jejak aktivitas sistem.</p>
            </div>
            <span class="self-start sm:self-auto px-3 py-1.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 text-[11px] font-extrabold flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span> Tersinkron ke Database
            </span>
          </div>

          <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
            <div class="flex items-center gap-4">
              <div id="adminProfileAvatarPreview" class="w-14 h-14 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-extrabold text-xl shadow-md flex-shrink-0">
                <?= strtoupper(substr($current_admin['nama_lengkap'] ?? $admin_name, 0, 1)); ?>
              </div>
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 id="adminProfileNamePreview" class="text-base font-extrabold"><?= htmlspecialchars($current_admin['nama_lengkap'] ?? $admin_name); ?></h3>
                  <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-[10px] font-extrabold uppercase">Super Admin</span>
                </div>
                <p id="adminProfileEmailPreview" class="text-xs text-indigo-200 mt-1"><?= htmlspecialchars($current_admin['email'] ?? $admin_email); ?></p>
              </div>
            </div>
            <div class="text-left sm:text-right">
              <p class="text-[10px] uppercase tracking-wider text-indigo-300 font-bold">ID Akun Admin</p>
              <p id="adminProfileIdPreview" class="text-sm font-extrabold font-mono text-white">#<?= intval($current_admin['id'] ?? 1); ?></p>
            </div>
          </div>

          <form method="POST" action="admin-dashboard.php" class="space-y-5">
            <input type="hidden" name="action_update_admin" value="1">
            <input type="hidden" name="selected_admin_id" id="selectedAdminIdInput" value="<?= intval($current_admin['id'] ?? 0); ?>">

            <?php if (count($admins_list) > 0): ?>
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
                <label class="block text-xs font-extrabold text-slate-700 uppercase">Pilih Akun Identitas Administrator</label>
                <select id="adminAccountSelector" onchange="fillAdminProfileForm(this)" class="input-studio bg-white font-bold text-xs">
                  <?php foreach ($admins_list as $adm_item): ?>
                    <?php
                      $is_sel = ($current_admin && intval($adm_item['id']) === intval($current_admin['id']));
                    ?>
                    <option value="<?= intval($adm_item['id']); ?>"
                            data-name="<?= htmlspecialchars($adm_item['nama_lengkap'] ?? ''); ?>"
                            data-email="<?= htmlspecialchars($adm_item['email'] ?? ''); ?>"
                            data-whatsapp="<?= htmlspecialchars($adm_item['whatsapp'] ?? ''); ?>"
                            data-alamat="<?= htmlspecialchars($adm_item['alamat'] ?? ''); ?>"
                            <?= $is_sel ? 'selected' : ''; ?>>
                      #<?= intval($adm_item['id']); ?> — <?= htmlspecialchars($adm_item['nama_lengkap']); ?> (<?= htmlspecialchars($adm_item['email']); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nama Administrator</label>
                <input type="text" name="admin_name" id="adminNameField" value="<?= htmlspecialchars($current_admin['nama_lengkap'] ?? $admin_name); ?>" class="input-studio" required>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Email Administrator</label>
                <input type="email" name="admin_email" id="adminEmailField" value="<?= htmlspecialchars($current_admin['email'] ?? $admin_email); ?>" class="input-studio" required>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Nomor Telepon / WhatsApp</label>
                <input type="tel" name="admin_whatsapp" id="adminWhatsappField" value="<?= htmlspecialchars($current_admin['whatsapp'] ?? ''); ?>" placeholder="081234567890" class="input-studio">
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Peran / Hak Akses</label>
                <input type="text" value="Administrator Utama (Super Admin)" class="input-studio bg-slate-50 text-slate-500 font-bold" readonly>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Alamat / Kantor Operasional</label>
                <input type="text" name="admin_alamat" id="adminAlamatField" value="<?= htmlspecialchars($current_admin['alamat'] ?? ''); ?>" placeholder="Contoh: Makassar, Sulawesi Selatan" class="input-studio">
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Kata Sandi Baru (Opsional)</label>
                <input type="password" name="admin_new_password" placeholder="Kosongkan jika tidak ingin mengganti sandi" class="input-studio">
              </div>
            </div>

            <div class="pt-2 flex justify-end">
              <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md flex items-center gap-2 transition-all">
                <span class="material-symbols-outlined text-[18px]">save</span>
                <span>Simpan Identitas Profil</span>
              </button>
            </div>
          </form>

          <div class="pt-6 border-t border-slate-100 space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-extrabold text-slate-900 uppercase">Riwayat Log Aktivitas Admin</h3>
              <span class="text-[11px] font-semibold text-slate-400">15 aktivitas terbaru</span>
            </div>
            <div class="space-y-2.5 max-h-64 overflow-y-auto pr-1">
              <?php while ($log = mysqli_fetch_assoc($logs_res)): ?>
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3 text-xs">
                  <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                    <span class="font-bold text-slate-800 truncate"><?= htmlspecialchars($log['aktivitas']); ?></span>
                  </div>
                  <span class="text-[11px] font-mono text-slate-400 flex-shrink-0"><?= htmlspecialchars($log['waktu']); ?></span>
                </div>
              <?php endwhile; ?>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <!-- MODAL POP-UP KONFIRMASI UPDATE STATUS PESANAN -->
  <div id="orderStatusModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4" onclick="closeStatusUpdateModal(event)">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden" onclick="event.stopPropagation()">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
            <span class="material-symbols-outlined text-[22px]">sync_alt</span>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-slate-900">Konfirmasi Ubah Status</h3>
            <p class="text-[11px] text-slate-500">Perbarui progres pesanan pelanggan</p>
          </div>
        </div>
        <button type="button" onclick="closeStatusUpdateModal()" class="p-1.5 rounded-xl hover:bg-slate-100 text-slate-500">
          <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
      </div>

      <form method="POST" action="admin-dashboard.php" class="p-6 space-y-5">
        <input type="hidden" name="action_update_order_status" value="1">
        <input type="hidden" name="order_id" id="modalStatusOrderPkId" value="">

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2 text-xs">
          <div class="flex justify-between gap-2">
            <span class="text-slate-500">ID Pesanan</span>
            <span id="modalStatusOrderCode" class="font-extrabold text-slate-900">-</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-slate-500">Klien / Usaha</span>
            <span id="modalStatusClientName" class="font-bold text-slate-800 text-right">-</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-slate-500">Layanan</span>
            <span id="modalStatusServiceName" class="font-bold text-indigo-600 text-right">-</span>
          </div>
          <div class="flex justify-between gap-2 pt-2 border-t border-slate-200">
            <span class="text-slate-500">Status Saat Ini</span>
            <span id="modalStatusCurrent" class="font-extrabold text-slate-800">-</span>
          </div>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-extrabold text-slate-700 uppercase">Pilih Status Baru</label>
          <div class="grid grid-cols-1 gap-2">
            <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:border-amber-400 cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/60 transition-all">
              <span class="flex items-center gap-2.5 text-xs font-bold text-slate-800">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Menunggu Pembayaran
              </span>
              <input type="radio" name="order_status" value="Menunggu Pembayaran" class="accent-amber-600">
            </label>
            <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:border-orange-400 cursor-pointer has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50/60 transition-all">
              <span class="flex items-center gap-2.5 text-xs font-bold text-slate-800">
                <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span> Menunggu Konfirmasi
              </span>
              <input type="radio" name="order_status" value="Menunggu Konfirmasi" class="accent-orange-600">
            </label>
            <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:border-blue-400 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/60 transition-all">
              <span class="flex items-center gap-2.5 text-xs font-bold text-slate-800">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Diproses
              </span>
              <input type="radio" name="order_status" value="Diproses" class="accent-blue-600">
            </label>
            <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:border-emerald-400 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/60 transition-all">
              <span class="flex items-center gap-2.5 text-xs font-bold text-slate-800">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Selesai
              </span>
              <input type="radio" name="order_status" value="Selesai" class="accent-emerald-600">
            </label>
            <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:border-rose-400 cursor-pointer has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/60 transition-all">
              <span class="flex items-center gap-2.5 text-xs font-bold text-slate-800">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Dibatalkan
              </span>
              <input type="radio" name="order_status" value="Dibatalkan" class="accent-rose-600">
            </label>
          </div>
        </div>

        <div class="pt-2 flex items-center justify-end gap-2.5">
          <button type="button" onclick="closeStatusUpdateModal()" class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs">
            Batal
          </button>
          <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px]">check_circle</span>
            <span>Konfirmasi & Simpan</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400">
    © 2026 Marketiva Digital Agency. Control Panel Administrator.
  </footer>

  <script>
    let activeOrderId = null;
    let currentOrderStatusFilter = 'all';

    function switchAdminTab(tabName) {
      document.getElementById('adminTabServices').classList.add('hidden');
      document.getElementById('adminTabAddService').classList.add('hidden');
      document.getElementById('adminTabOrders').classList.add('hidden');
      document.getElementById('adminTabOrderDetail').classList.add('hidden');
      document.getElementById('adminTabUsers').classList.add('hidden');
      document.getElementById('adminTabSlides').classList.add('hidden');
      document.getElementById('adminTabPaket').classList.add('hidden');
      document.getElementById('adminTabCta').classList.add('hidden');
      document.getElementById('adminTabArticles').classList.add('hidden');
      document.getElementById('adminTabAbout').classList.add('hidden');
      document.getElementById('adminTabProfile').classList.add('hidden');

      document.getElementById('btnTabServices').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      document.getElementById('btnTabAddService').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      document.getElementById('btnTabOrders').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      document.getElementById('btnTabUsers').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      document.getElementById('btnTabSlides').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      document.getElementById('btnTabPaket').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      document.getElementById('btnTabCta').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      document.getElementById('btnTabArticles').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      document.getElementById('btnTabAbout').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";
      document.getElementById('btnTabProfile').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all";

      if (tabName === 'services') {
        document.getElementById('adminTabServices').classList.remove('hidden');
        document.getElementById('btnTabServices').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'add-service') {
        document.getElementById('adminTabAddService').classList.remove('hidden');
        document.getElementById('btnTabAddService').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'orders') {
        document.getElementById('adminTabOrders').classList.remove('hidden');
        document.getElementById('btnTabOrders').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'order-detail') {
        document.getElementById('adminTabOrderDetail').classList.remove('hidden');
        document.getElementById('btnTabOrders').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'users') {
        document.getElementById('adminTabUsers').classList.remove('hidden');
        document.getElementById('btnTabUsers').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'slides') {
        document.getElementById('adminTabSlides').classList.remove('hidden');
        document.getElementById('btnTabSlides').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'paket') {
        document.getElementById('adminTabPaket').classList.remove('hidden');
        document.getElementById('btnTabPaket').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'cta') {
        document.getElementById('adminTabCta').classList.remove('hidden');
        document.getElementById('btnTabCta').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'articles') {
        document.getElementById('adminTabArticles').classList.remove('hidden');
        document.getElementById('btnTabArticles').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'about') {
        document.getElementById('adminTabAbout').classList.remove('hidden');
        document.getElementById('btnTabAbout').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      } else if (tabName === 'profile') {
        document.getElementById('adminTabProfile').classList.remove('hidden');
        document.getElementById('btnTabProfile').className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 transition-all";
      }
    }

    function setOrderStatusFilter(statusFilter) {
      currentOrderStatusFilter = statusFilter;
      const pills = document.querySelectorAll('.order-filter-pill');
      pills.forEach(btn => {
        const badge = btn.querySelector('span:last-child');
        if (btn.getAttribute('data-filter') === statusFilter) {
          btn.className = "order-filter-pill px-3.5 py-2 rounded-xl text-xs font-extrabold bg-slate-900 text-white shadow-xs transition-all flex items-center gap-1.5";
          if (badge) badge.className = "px-1.5 py-0.5 rounded-full text-[10px] bg-white/20 text-white";
        } else {
          btn.className = "order-filter-pill px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-white hover:text-slate-900 transition-all flex items-center gap-1.5";
          if (badge) badge.className = "px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200/80 text-slate-700";
        }
      });
      applyOrderFilters();
    }

    function applyOrderFilters() {
      const searchVal = (document.getElementById('orderSearchInput')?.value || '').toLowerCase().trim();
      const rows = document.querySelectorAll('.order-data-row');
      let visibleCount = 0;

      rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status') || '';
        const rowSearch = row.getAttribute('data-search') || '';
        const matchStatus = (currentOrderStatusFilter === 'all') || (rowStatus.toLowerCase() === currentOrderStatusFilter.toLowerCase());
        const matchSearch = !searchVal || rowSearch.includes(searchVal);

        if (matchStatus && matchSearch) {
          row.classList.remove('hidden');
          visibleCount++;
        } else {
          row.classList.add('hidden');
        }
      });

      const emptyRow = document.getElementById('ordersEmptyFilterRow');
      if (emptyRow) {
        if (visibleCount === 0 && rows.length > 0) {
          emptyRow.classList.remove('hidden');
        } else {
          emptyRow.classList.add('hidden');
        }
      }
    }

    function openStatusUpdateModal(data) {
      const kode = data.order_id ? data.order_id : ('#' + data.id);
      const client = (data.nama_lengkap || 'Pelanggan') + (data.nama_usaha ? ' (' + data.nama_usaha + ')' : '');
      const currentStatus = data.status || 'Menunggu Pembayaran';

      document.getElementById('modalStatusOrderPkId').value = data.id;
      document.getElementById('modalStatusOrderCode').textContent = kode;
      document.getElementById('modalStatusClientName').textContent = client;
      document.getElementById('modalStatusServiceName').textContent = data.nama_layanan || '-';
      document.getElementById('modalStatusCurrent').textContent = currentStatus;

      const radios = document.querySelectorAll('#orderStatusModal input[name="order_status"]');
      radios.forEach(r => {
        r.checked = (r.value.toLowerCase() === currentStatus.toLowerCase());
      });

      document.getElementById('orderStatusModal').classList.remove('hidden');
    }

    function closeStatusUpdateModal(event) {
      if (!event || event.target.id === 'orderStatusModal') {
        document.getElementById('orderStatusModal').classList.add('hidden');
      }
    }

    function filterCustomerCards() {
      const q = (document.getElementById('customerSearchInput')?.value || '').toLowerCase().trim();
      const cards = document.querySelectorAll('.customer-kpi-card');
      cards.forEach(card => {
        const hay = card.getAttribute('data-search') || '';
        if (!q || hay.includes(q)) {
          card.classList.remove('hidden');
        } else {
          card.classList.add('hidden');
        }
      });
    }

    function fillAdminProfileForm(selectEl) {
      const opt = selectEl.options[selectEl.selectedIndex];
      if (!opt) return;
      const idVal     = opt.value;
      const nameVal   = opt.getAttribute('data-name') || '';
      const emailVal  = opt.getAttribute('data-email') || '';
      const waVal     = opt.getAttribute('data-whatsapp') || '';
      const alamatVal = opt.getAttribute('data-alamat') || '';

      document.getElementById('selectedAdminIdInput').value = idVal;
      document.getElementById('adminNameField').value       = nameVal;
      document.getElementById('adminEmailField').value      = emailVal;
      document.getElementById('adminWhatsappField').value   = waVal;
      document.getElementById('adminAlamatField').value     = alamatVal;

      document.getElementById('adminProfileIdPreview').textContent     = '#' + idVal;
      document.getElementById('adminProfileNamePreview').textContent   = nameVal || 'Admin Marketiva';
      document.getElementById('adminProfileEmailPreview').textContent  = emailVal || '-';
      document.getElementById('adminProfileAvatarPreview').textContent = (nameVal ? nameVal.charAt(0).toUpperCase() : 'A');
    }

    function openOrderDetail(data) {
      activeOrderId = data.id;
      const kode   = data.order_id ? data.order_id : ('#' + data.id);
      const harga  = Number(data.harga || 0).toLocaleString('id-ID');
      const status = data.status || 'Menunggu Konfirmasi';

      document.getElementById('detailOrderTitle').innerText = 'Detail Pesanan ' + kode;
      document.getElementById('detailOrderStatusBadge').innerText = status;
      document.getElementById('detailNamaLayanan').innerText = data.nama_layanan || '-';
      document.getElementById('detailKategori').innerText = 'Kategori: ' + (data.kategori || '-');
      document.getElementById('detailHargaSatuan').innerText = 'Rp ' + harga;
      document.getElementById('detailSubtotal').innerText = 'Rp ' + harga;
      document.getElementById('detailNamaPemesan').innerText = data.nama_lengkap || '-';
      document.getElementById('detailNamaUsaha').innerText = data.nama_usaha || '-';
      document.getElementById('detailEmail').innerText = data.user_email || '-';
      document.getElementById('detailWhatsapp').innerText = data.whatsapp || data.user_email || '-';
      document.getElementById('detailStatusSelect').value = status;

      let waRaw = String(data.whatsapp || '').replace(/[^0-9]/g, '');
      if (waRaw.startsWith('0')) waRaw = '62' + waRaw.slice(1);
      const waBtn = document.getElementById('detailWaLink');
      if (waRaw) {
        waBtn.href = 'https://wa.me/' + waRaw;
        waBtn.classList.remove('hidden');
      } else {
        waBtn.classList.add('hidden');
      }

      switchAdminTab('order-detail');
    }

    function saveOrderDetailStatus() {
      if (!activeOrderId) return;
      const val = document.getElementById('detailStatusSelect').value;
      location.href = 'admin-dashboard.php?order_id=' + encodeURIComponent(activeOrderId) + '&update_order_status=' + encodeURIComponent(val);
    }

    function openAddForm() {
      document.getElementById('service_id').value = '';
      document.getElementById('old_gambar').value = '';
      document.getElementById('nama_layanan').value = '';
      document.getElementById('kategori_id').value = '';
      document.getElementById('harga').value = '';
      document.getElementById('deskripsi').value = '';
      document.getElementById('formTitle').innerText = 'Input Layanan Baru';
      document.getElementById('formSubtitle').innerText = 'Tambah kategori baru atau unggah paket jasa digital dari perangkat Anda.';
      document.getElementById('submitBtnText').innerText = 'Simpan Layanan';
      switchAdminTab('add-service');
    }

    function openEditForm(data) {
      document.getElementById('service_id').value = data.id;
      document.getElementById('old_gambar').value = data.gambar;
      document.getElementById('nama_layanan').value = data.nama_layanan;
      document.getElementById('kategori_id').value = data.kategori_id;
      document.getElementById('harga').value = data.harga;
      document.getElementById('deskripsi').value = data.deskripsi;
      document.getElementById('formTitle').innerText = 'Edit Layanan: ' + data.nama_layanan;
      document.getElementById('formSubtitle').innerText = 'Perbarui informasi atau ubah file gambar layanan.';
      document.getElementById('submitBtnText').innerText = 'Simpan Perubahan';
      switchAdminTab('add-service');
    }

    function editSlide(data) {
      document.getElementById('slide_id').value = data.id;
      document.getElementById('slide_badge').value = data.badge;
      document.getElementById('slide_judul').value = data.judul;
      document.getElementById('slide_deskripsi').value = data.deskripsi;
      document.getElementById('slideSubmitBtn').innerText = 'Simpan Perubahan Slide';
    }

    function resetSlideForm() {
      document.getElementById('slide_id').value = '';
      document.getElementById('slide_badge').value = '';
      document.getElementById('slide_judul').value = '';
      document.getElementById('slide_deskripsi').value = '';
      document.getElementById('slideSubmitBtn').innerText = 'Simpan Banner Slide';
    }

    function filterCatalog() {
      let input = document.getElementById('searchCatalog').value.toLowerCase();
      let rows = document.getElementsByClassName('service-row');
      for (let i = 0; i < rows.length; i++) {
        let name = rows[i].getElementsByClassName('service-name')[0].innerText.toLowerCase();
        if (name.includes(input)) {
          rows[i].style.display = "";
        } else {
          rows[i].style.display = "none";
        }
      }
    }

    window.addEventListener('DOMContentLoaded', function() {
      switchAdminTab(<?= json_encode($initial_tab); ?>);
    });
  </script>
</body>
</html>