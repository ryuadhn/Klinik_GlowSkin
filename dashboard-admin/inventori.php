<?php
/**
 * ============================================================
 * FILE: dashboard-admin/inventori.php (sebelumnya inventori.html)
 * DESKRIPSI: Inventori Produk Skincare untuk Admin Klinik GlowSkin.
 * ============================================================
 */

// --- SERTAKAN FILE KONEKSI DATABASE ---
require_once __DIR__ . '/../koneksi.php';

// --- TANGGAL FILTER ---
$tanggal_pilihan = $_GET['tanggal'] ?? date('Y-m-d');

// --- VARIABEL FEEDBACK ---
$pesan_sukses = '';
$pesan_error  = '';

// --- PROSES UPDATE & TAMBAH PRODUK (POST REQUEST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_product') {
        $id_obat      = intval($_POST['id_obat'] ?? 0);
        $nama_obat    = trim(htmlspecialchars($_POST['nama_obat'] ?? ''));
        $stok         = intval($_POST['stok'] ?? 0);
        $harga_jual   = floatval($_POST['harga_jual'] ?? 0);
        $stok_minimum = intval($_POST['stok_minimum'] ?? 10);

        if ($id_obat > 0 && !empty($nama_obat)) {
            try {
                // File upload handling
                $gambar_url = null;
                $has_new_image = false;
                if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['gambar_file']['tmp_name'];
                    $file_name = $_FILES['gambar_file']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($file_ext, $allowed_exts)) {
                        $upload_dir = __DIR__ . '/../assets/images/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Fetch kode_obat for naming
                        $stmt_code = $pdo->prepare("SELECT kode_obat FROM obat WHERE id_obat = ?");
                        $stmt_code->execute([$id_obat]);
                        $kode_obat = $stmt_code->fetchColumn() ?: 'OBT';
                        
                        $new_file_name = $kode_obat . '_' . time() . '.' . $file_ext;
                        $dest_path = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $dest_path)) {
                            $gambar_url = 'assets/images/products/' . $new_file_name;
                            $has_new_image = true;
                        }
                    }
                }

                if ($has_new_image) {
                    $stmt_update = $pdo->prepare("
                        UPDATE obat 
                        SET nama_obat = ?, stok = ?, harga_jual = ?, stok_minimum = ?, gambar_url = ? 
                        WHERE id_obat = ?
                    ");
                    $stmt_update->execute([$nama_obat, $stok, $harga_jual, $stok_minimum, $gambar_url, $id_obat]);
                } else {
                    $stmt_update = $pdo->prepare("
                        UPDATE obat 
                        SET nama_obat = ?, stok = ?, harga_jual = ?, stok_minimum = ? 
                        WHERE id_obat = ?
                    ");
                    $stmt_update->execute([$nama_obat, $stok, $harga_jual, $stok_minimum, $id_obat]);
                }
                $pesan_sukses = "Produk '{$nama_obat}' berhasil diperbarui!";
            } catch (PDOException $e) {
                $pesan_error = "Gagal memperbarui produk: " . $e->getMessage();
            }
        } else {
            $pesan_error = "Input data produk tidak valid!";
        }
    } elseif ($_POST['action'] === 'add_product') {
        $kode_obat    = trim(htmlspecialchars($_POST['kode_obat'] ?? ''));
        $nama_obat    = trim(htmlspecialchars($_POST['nama_obat'] ?? ''));
        $id_kategori  = intval($_POST['id_kategori'] ?? 0);
        $satuan       = trim(htmlspecialchars($_POST['satuan'] ?? ''));
        $stok         = intval($_POST['stok'] ?? 0);
        $harga_jual   = floatval($_POST['harga_jual'] ?? 0);
        $harga_beli   = floatval($_POST['harga_beli'] ?? 0);
        $stok_minimum = intval($_POST['stok_minimum'] ?? 10);

        if (!empty($kode_obat) && !empty($nama_obat) && $id_kategori > 0 && !empty($satuan)) {
            try {
                // File upload handling
                $gambar_url = null;
                if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['gambar_file']['tmp_name'];
                    $file_name = $_FILES['gambar_file']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($file_ext, $allowed_exts)) {
                        $upload_dir = __DIR__ . '/../assets/images/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $new_file_name = $kode_obat . '_' . time() . '.' . $file_ext;
                        $dest_path = $upload_dir . $new_file_name;
                        if (move_uploaded_file($file_tmp, $dest_path)) {
                            $gambar_url = 'assets/images/products/' . $new_file_name;
                        }
                    }
                }

                $stmt_insert = $pdo->prepare("
                    INSERT INTO obat (kode_obat, nama_obat, id_kategori, satuan, harga_jual, harga_beli, stok, stok_minimum, gambar_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_insert->execute([$kode_obat, $nama_obat, $id_kategori, $satuan, $harga_jual, $harga_beli, $stok, $stok_minimum, $gambar_url]);
                $pesan_sukses = "Produk baru '{$nama_obat}' ({$kode_obat}) berhasil ditambahkan!";
            } catch (PDOException $e) {
                $pesan_error = "Gagal menambahkan produk baru: " . $e->getMessage();
            }
        } else {
            $pesan_error = "Semua kolom wajib diisi dengan benar!";
        }
    }
}

// --- FUNGSI HELPER: FORMAT ANGKA RUPIAH ---
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

// --- FUNGSI HELPER: GAMBAR PRODUK BERDASARKAN KODE ---
function getProductImage($kode_obat, $gambar_url = null) {
    if (!empty($gambar_url)) {
        if (strpos($gambar_url, 'http://') === 0 || strpos($gambar_url, 'https://') === 0) {
            return $gambar_url;
        }
        return '../' . $gambar_url;
    }
    switch ($kode_obat) {
        case 'OBT-004': // Acne Facial Wash
        case 'GS-CLS-001': // Gentle Foaming Cleanser
            return 'https://lh3.googleusercontent.com/aida-public/AB6AXuDvxO_-Mu3S2lL7zzTE_lB4-ZqmdzYh0ryhFB9iHZWtBslnspzY4ZYjCXjuRut68ID3VT1EQRCKLyHj5VfDoZJetCm7KyNGGeJ3DxBcLOUom5zSNjOckOuxu3X__hNH2oH_0CYhTfT50F7bfvwrXVdP0CfphK9kA14vKZMFmfZXYlQiTtwlB3pMSf2ijIuK93N26pq93jOo29nxd95e1640rOYf6BUeFAqrEL7Zcf7S_04aWAd5Ws09_nNL-DUWJJ5W6FZg-yn0TRs';
        case 'OBT-002': // Vitamin C Serum
        case 'GS-SRM-012': // Brightening Vitamin C Serum
            return 'https://lh3.googleusercontent.com/aida-public/AB6AXuDq97QDTyo-p_fFvceDyNYP3I0mKO7b8gLXsLOk3JquQifEhUzlrux1aL1jz6giAxmuUyzBvqwY3jsowpZWcg3bPex_H4HHFDdz6H5AmktCOj0vhDtrGbTZiNZOpPIFBG-FQ6rHGIWX640VTGpuX-vSZ4gmHnlZ3tzyUYI3AMADbmXUuP8d5AzsDrL4bGb73LrIDWxgFmmnGZ0QjUvOytkSjoH5Bp1vscqt7lRZTjb2jvE_i4rzW_OT2HaIily-gah9YwJBQMijP10';
        case 'OBT-003': // Sunscreen
        case 'GS-SUN-005': // UV Shield Daily Sunscreen
            return 'https://lh3.googleusercontent.com/aida-public/AB6AXuDTuebV9IcJrHnqW2CPVLedT7srbVq0D0Eo0IGtF11N7bmbOv3E04brMFsKjK5A546qprEBLtFM7jN812ebpqmi1Qv8g--FwDBvJoBnFTIxD8N-X8bAThuMg3p4FC-WWP5D0y4km5YqUermThvox-yelZ97HXTo9IfEmrftg1fnUNA5pZ4bqjZtZVgDtwVD4my_Rm1sNvJ_KDGJM-pzOJWWJRs8qAMfimiIKK4J-u5zD_YwjObe2zPehZsUrbRtk3koU-3CcL8Ckvk';
        case 'OBT-001': // Retinol Night Cream
        case 'OBT-005': // Collagen Glow Capsule
        case 'GS-MST-021': // Hyaluronic Acid Gel
            return 'https://lh3.googleusercontent.com/aida-public/AB6AXuBS5BwY3H8kVhBv6vMb2O-IRhU4xcMOcMf0RsVaCPZQTSh0LyPTYHn-dGYzioJhrGFIqpbFbcyW_lEgFwJKKXUKdnscb-zL-1SkzFZsD6gdw12F2zvRBcKTPSHYKwGpGdXsY9C3UZp1ikqTV-boR2rI0cw356u1f_Zj4JpzdUVPIVVWAwqeZzexivqrSSwJtUYgUY0QHed28xWacjVzmCFaUq4IPr16OMUr5xwg8aPwa1Wmnpe_46NvfOTmmSCE4u-DLkksSCJXxfs';
        default:
            return 'https://images.unsplash.com/photo-1608248597481-496100c80836?auto=format&fit=crop&q=80&w=120';
    }
}

// --- QUERY DYNAMIC STATS (BENTO GRID) ---
try {
    // 1. Total Produk
    $stmt_tot = $pdo->query("SELECT COUNT(*) FROM obat WHERE is_active = TRUE");
    $total_produk = $stmt_tot->fetchColumn() ?: 0;

    // 2. Stok Rendah (di bawah atau sama dengan stok minimum)
    $stmt_low = $pdo->query("SELECT COUNT(*) FROM obat WHERE stok <= stok_minimum AND is_active = TRUE");
    $stok_rendah = $stmt_low->fetchColumn() ?: 0;

    // 3. Kategori Aktif
    $stmt_cat = $pdo->query("SELECT COUNT(DISTINCT id_kategori) FROM obat WHERE is_active = TRUE");
    $kategori_aktif = $stmt_cat->fetchColumn() ?: 0;

} catch (PDOException $e) {
    $total_produk = 0;
    $stok_rendah = 0;
    $kategori_aktif = 0;
}

// --- QUERY DAFTAR KATEGORI UNTUK FILTER ---
try {
    $categories = $pdo->query("SELECT id, nama_kategori FROM kategori_obat ORDER BY nama_kategori ASC")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// --- QUERY DAFTAR PRODUK (TABLE) ---
try {
    $products = $pdo->query("
        SELECT o.id_obat, o.kode_obat, o.nama_obat, o.stok, o.stok_minimum, o.harga_jual, o.gambar_url, k.nama_kategori
        FROM obat o
        JOIN kategori_obat k ON o.id_kategori = k.id
        WHERE o.is_active = TRUE
        ORDER BY o.kode_obat ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $products = [];
}
?>
<!DOCTYPE html>
<html class="light" lang="id">

<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title>Inventori Produk Skincare | GlowSkin Admin</title>

  <!-- Scripts & Styles -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script src="../assets/js/theme-toggle.js"></script>
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "surface": "var(--surface)",
            "secondary": "#b90538",
            "on-secondary": "#ffffff",
            "on-secondary-fixed": "#40000d",
            "surface-container-highest": "var(--surface-container-highest)",
            "error-container": "#ffdad6",
            "on-error": "#ffffff",
            "tertiary-fixed-dim": "#b7c8e1",
            "error": "#ba1a1a",
            "on-tertiary-fixed": "#0b1c30",
            "primary-fixed-dim": "#4fdbc8",
            "outline-variant": "var(--outline-variant)",
            "on-background": "#191c1e",
            "on-surface": "var(--on-surface)",
            "surface-tint": "var(--primary)",
            "on-secondary-fixed-variant": "#92002a",
            "on-primary-container": "#00423b",
            "on-tertiary": "#ffffff",
            "surface-variant": "var(--surface-container-highest)",
            "inverse-surface": "#2d3133",
            "surface-container-high": "var(--surface-container-high)",
            "secondary-fixed-dim": "#ffb2b7",
            "surface-bright": "var(--surface-lowest)",
            "on-primary-fixed-variant": "#005048",
            "surface-container-low": "var(--surface-container-low)",
            "surface-container": "var(--surface-container)",
            "primary": "var(--primary)",
            "on-tertiary-container": "#2b3b50",
            "tertiary-fixed": "#d3e4fe",
            "primary-fixed": "#71f8e4",
            "inverse-primary": "#4fdbc8",
            "surface-dim": "var(--surface-dim)",
            "surface-container-lowest": "var(--surface-container-lowest)",
            "inverse-on-surface": "#eff1f3",
            "on-secondary-container": "#fffbff",
            "primary-container": "var(--primary-container)",
            "secondary-container": "#dc2c4f",
            "on-tertiary-fixed-variant": "#38485d",
            "on-primary": "#ffffff",
            "on-error-container": "#93000a",
            "on-primary-fixed": "#00201c",
            "tertiary": "#505f76",
            "outline": "var(--outline)",
            "on-surface-variant": "var(--on-surface-variant)",
            "background": "var(--surface)",
            "secondary-fixed": "#ffdadb",
            "tertiary-container": "#95a5be"
          },
          borderRadius: {
            "DEFAULT": "0.125rem",
            "lg": "0.25rem",
            "xl": "0.5rem",
            "full": "0.75rem"
          },
          spacing: {
            "sm": "8px",
            "xl": "40px",
            "md": "16px",
            "container-max": "1440px",
            "xs": "4px",
            "lg": "24px",
            "sidebar-width": "260px",
            "base": "4px"
          },
          fontFamily: {
            "display-lg": ["Plus Jakarta Sans"],
            "headline-md": ["Plus Jakarta Sans"],
            "label-caps": ["Plus Jakarta Sans"],
            "body-md": ["Plus Jakarta Sans"],
            "body-sm": ["Plus Jakarta Sans"],
            "title-sm": ["Plus Jakarta Sans"],
            "display-lg-mobile": ["Plus Jakarta Sans"]
          },
          fontSize: {
            "display-lg": ["40px", { "lineHeight": "48px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
            "headline-md": ["24px", { "lineHeight": "32px", "fontWeight": "600" }],
            "label-caps": ["12px", { "lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "700" }],
            "body-md": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
            "body-sm": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
            "title-sm": ["18px", { "lineHeight": "24px", "fontWeight": "600" }],
            "display-lg-mobile": ["32px", { "lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700" }]
          }
        }
      }
    };
  </script>
</head>

<body class="bg-surface dark:bg-[#0f172a] text-on-surface dark:text-[#f1f5f9] font-body-md transition-colors duration-300">
  
  <?php if (!empty($pesan_sukses)) : ?>
    <script>alert("✅ <?= $pesan_sukses ?>");</script>
  <?php elseif (!empty($pesan_error)) : ?>
    <script>alert("❌ <?= $pesan_error ?>");</script>
  <?php endif; ?>

  <!-- SideNavBar Shell -->
  <aside class="fixed h-full w-[260px] left-0 top-0 flex flex-col py-lg border-r border-outline-variant dark:border-slate-800 bg-surface-container-lowest dark:bg-[#0f172a] z-50">
    <div class="px-lg mb-xl">
      <h1 class="font-display-lg text-display-lg text-primary dark:text-primary-fixed-dim tracking-tight">GlowSkin</h1>
      <p class="font-label-caps text-label-caps text-on-surface-variant/60 dark:text-slate-400 tracking-widest uppercase">Admin Console</p>
    </div>

    <nav class="flex-1 space-y-1">
      <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="index.php">
        <span class="material-symbols-outlined mr-3">dashboard</span>
        <span class="font-label-caps text-label-caps">Ringkasan Dashboard</span>
      </a>
      <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="rekam-medis.php">
        <span class="material-symbols-outlined mr-3">medical_services</span>
        <span class="font-label-caps text-label-caps">Data Pasien &amp; Rekam Medis</span>
      </a>
      <!-- Active Tab -->
      <a class="group relative flex items-center px-lg py-3 text-primary dark:text-primary-fixed-dim font-bold border-l-4 border-primary dark:border-primary-fixed-dim bg-primary/5 transition-all duration-200" href="inventori.php">
        <span class="material-symbols-outlined mr-3">inventory_2</span>
        <span class="font-label-caps text-label-caps">Inventori Produk Skincare</span>
      </a>
      <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="transaksi.php">
        <span class="material-symbols-outlined mr-3">payments</span>
        <span class="font-label-caps text-label-caps">Transaksi &amp; Pembayaran</span>
      </a>
      <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="log-keamanan.php">
        <span class="material-symbols-outlined mr-3">security</span>
        <span class="font-label-caps text-label-caps">Log Keamanan Sistem</span>
      </a>
    </nav>

    <div class="px-lg pt-lg mt-auto border-t border-outline-variant dark:border-slate-800">
      <button id="btn-new-appointment" class="w-full py-3 px-4 bg-primary text-on-primary font-bold rounded-lg mb-lg hover:brightness-110 transition-all flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-[20px]">add</span>
        <span class="font-label-caps text-label-caps">New Appointment</span>
      </button>
      <div class="space-y-1">
        <a id="link-settings" class="flex items-center px-2 py-2 text-on-surface-variant dark:text-slate-400 hover:text-primary transition-colors" href="#">
          <span class="material-symbols-outlined mr-3 text-[20px]">settings</span>
          <span class="font-body-sm text-body-sm">Settings</span>
        </a>
        <a class="flex items-center px-2 py-2 text-on-surface-variant dark:text-slate-400 hover:text-primary transition-colors" href="../landing-page/index.php">
          <span class="material-symbols-outlined mr-3 text-[20px]">logout</span>
          <span class="font-body-sm text-body-sm">Keluar</span>
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content Area -->
  <div class="ml-[260px] min-h-screen flex flex-col">
    <!-- TopNavBar Shell -->
    <header class="fixed top-0 right-0 w-[calc(100%-260px)] h-16 bg-surface-container-lowest dark:bg-[#0f172a] border-b border-outline-variant dark:border-slate-800 flex justify-between items-center px-xl z-40">
      <div class="flex items-center flex-1 max-w-xl">
        <div class="relative w-full">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant dark:text-slate-400">search</span>
          <input class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-full py-2 pl-10 pr-4 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" placeholder="Cari produk, kategori, atau kode..." type="text" id="search-input" />
        </div>
      </div>

      <div class="flex items-center gap-6 ml-lg">
        <!-- Theme Toggle Button -->
        <button class="relative text-on-surface-variant dark:text-slate-300 hover:text-primary transition-colors flex items-center" id="theme-toggle">
          <span class="material-symbols-outlined hidden" id="dark-icon">light_mode</span>
          <span class="material-symbols-outlined" id="light-icon">dark_mode</span>
        </button>

        <button class="relative text-on-surface-variant dark:text-slate-300 hover:text-primary transition-colors">
          <span class="material-symbols-outlined">notifications</span>
          <?php if ($stok_rendah > 0) : ?>
            <span class="absolute top-0 right-0 w-2 h-2 bg-secondary rounded-full border-2 border-surface-container-lowest dark:border-[#0f172a]"></span>
          <?php endif; ?>
        </button>

        <div class="flex items-center gap-2 text-on-surface-variant dark:text-slate-300 bg-surface-container-low/50 dark:bg-slate-800/50 px-3 py-1.5 rounded-lg border border-outline-variant/30 dark:border-slate-800">
          <span class="material-symbols-outlined text-[18px]">calendar_today</span>
          <input type="date" id="calendar-select" value="<?= $tanggal_pilihan ?>" class="bg-transparent border-none p-0 text-body-sm font-medium focus:ring-0 outline-none text-on-surface cursor-pointer dark:text-white" onchange="window.location.href = '?tanggal=' + this.value;">
        </div>

        <div class="h-8 w-px bg-outline-variant dark:bg-slate-800"></div>

        <div class="flex items-center gap-3">
          <div class="text-right hidden sm:block">
            <p class="font-title-sm text-title-sm leading-none text-primary">Admin Nanda</p>
            <p class="font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400">SUPER ADMIN</p>
          </div>
          <div class="w-10 h-10 rounded-full border-2 border-primary-container bg-primary/10 flex items-center justify-center text-primary">
            <span class="material-symbols-outlined text-[24px]">person</span>
          </div>
        </div>
      </div>
    </header>

    <!-- Page Canvas -->
    <main class="mt-16 p-xl flex-1 max-w-[1440px] mx-auto w-full bg-background dark:bg-transparent">
      <!-- Header Section -->
      <div class="flex flex-col md:flex-row md:items-end justify-between mb-xl gap-lg">
        <div>
          <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface">Inventori Produk Skincare</h2>
          <p class="text-on-surface-variant font-body-md mt-1">Kelola stok produk, pembaruan harga, dan pantau ketersediaan barang secara real-time.</p>
        </div>
        <div class="flex items-center gap-3">
          <button id="btn-tambah-produk" class="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-lg font-label-caps text-label-caps hover:brightness-110 transition-all whitespace-nowrap h-fit self-start md:self-end shadow-sm">
            <span class="material-symbols-outlined text-[18px]">add_circle</span>
            Tambah Produk Baru
          </button>
        </div>
      </div>

      <!-- Bento Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
        <!-- Total Products -->
        <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant dark:border-slate-800 flex items-start gap-4 h-36 relative overflow-hidden shadow-sm">
          <div class="p-2 bg-primary/10 rounded-lg text-primary dark:text-primary-fixed-dim flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[20px]">inventory</span>
          </div>
          <div class="flex-grow flex flex-col justify-center h-full">
            <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Total Produk</p>
            <p class="font-display-lg text-display-lg text-on-surface dark:text-slate-100 mt-1"><?= $total_produk ?></p>
          </div>
          <div class="absolute -right-4 -bottom-4 opacity-5">
            <span class="material-symbols-outlined text-[120px]">inventory</span>
          </div>
        </div>

        <!-- Low Stock - Alert Styling -->
        <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-secondary/30 bg-secondary/5 dark:bg-red-950/20 dark:border-red-900/30 flex items-start gap-4 h-36 relative overflow-hidden shadow-sm">
          <div class="p-2 bg-secondary/10 rounded-lg text-secondary dark:text-red-400 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[20px]">warning</span>
          </div>
          <div class="flex-grow flex flex-col justify-center h-full">
            <p class="font-label-caps text-label-caps text-secondary dark:text-red-400 uppercase">Stok Rendah</p>
            <div class="flex items-baseline gap-2 mt-1">
              <p class="font-display-lg text-display-lg text-secondary dark:text-red-400"><?= $stok_rendah ?></p>
              <span class="font-body-sm text-body-sm text-on-surface-variant dark:text-slate-300">Perlu Restock</span>
            </div>
          </div>
        </div>

        <!-- Active Categories -->
        <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant dark:border-slate-800 flex items-start gap-4 h-36 relative overflow-hidden shadow-sm">
          <div class="p-2 bg-primary-container/20 rounded-lg text-primary dark:text-primary-fixed-dim flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[20px]">category</span>
          </div>
          <div class="flex-grow flex flex-col justify-center h-full">
            <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Total Kategori Produk</p>
            <p class="font-display-lg text-display-lg text-on-surface dark:text-slate-100 mt-1"><?= $kategori_aktif ?></p>
            <div class="flex gap-1 mt-2">
              <div class="h-1 flex-1 bg-primary rounded-full"></div>
              <div class="h-1 flex-1 bg-primary rounded-full"></div>
              <div class="h-1 flex-1 bg-primary/20 dark:bg-primary-fixed-dim/20 rounded-full"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters & Actions -->
      <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant dark:border-slate-800 mb-lg flex flex-col md:flex-row gap-lg items-center justify-between shadow-sm">
        <div class="flex flex-wrap gap-md w-full md:w-auto">
          <div class="flex flex-col gap-1">
            <label class="font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 px-1 uppercase">Kategori</label>
            <select class="bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg px-md py-2 text-body-sm text-on-surface dark:text-slate-100 outline-none focus:border-primary transition-all min-w-[160px]" id="filter-category">
              <option value="all">Semua Kategori</option>
              <?php foreach ($categories as $cat) : ?>
                <option value="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $cat['nama_kategori']))) ?>"><?= htmlspecialchars($cat['nama_kategori']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 px-1 uppercase">Status Stok</label>
            <select class="bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg px-md py-2 text-body-sm text-on-surface dark:text-slate-100 outline-none focus:border-primary transition-all min-w-[160px]" id="filter-status">
              <option value="all">Semua Status</option>
              <option value="instock">Tersedia (Aman)</option>
              <option value="lowstock">Hampir Habis</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-body-sm text-on-surface-variant dark:text-slate-400" id="product-count">Menampilkan - dari - produk</span>
        </div>
      </div>

      <!-- Data Table Section -->
      <div class="bg-surface-container-lowest dark:bg-[#1e293b] rounded-xl border border-outline-variant dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead class="bg-surface-container-low dark:bg-slate-900 border-b border-outline-variant dark:border-slate-800">
              <tr>
                <th class="px-lg py-4 font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Kode Produk</th>
                <th class="px-lg py-4 font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Nama Produk</th>
                <th class="px-lg py-4 font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Kategori</th>
                <th class="px-lg py-4 font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Stok</th>
                <th class="px-lg py-4 font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase tracking-wider text-right">Harga Jual</th>
                <th class="px-lg py-4 font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase tracking-wider text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/30 dark:divide-slate-800/50" id="product-table-body">
              <?php
              if (empty($products)) :
              ?>
                <tr>
                  <td colspan="6" class="px-lg py-8 text-center text-on-surface-variant dark:text-slate-400 italic">Belum ada data produk di database.</td>
                </tr>
              <?php
              else :
                foreach ($products as $product) :
                  // Logic warna bar stok & status filter
                  $status_slug = 'instock';
                  $bar_color = 'bg-primary';
                  $text_color_stok = 'text-on-surface-variant dark:text-slate-400';
                  
                  if ($product['stok'] <= $product['stok_minimum']) {
                      $status_slug = 'lowstock';
                      $bar_color = 'bg-secondary dark:bg-red-500';
                      $text_color_stok = 'text-secondary dark:text-red-400 font-bold';
                  } elseif ($product['stok'] <= 50) {
                      $bar_color = 'bg-tertiary';
                  }
                  
                  // Hitung persentase progress bar (max 100)
                  $progress_pct = max(0, min(100, $product['stok']));
                  
                  // Category Slug untuk JavaScript filter
                  $category_slug = htmlspecialchars(strtolower(str_replace(' ', '-', $product['nama_kategori'])));
              ?>
                <tr class="hover:bg-primary/5 dark:hover:bg-slate-800/50 transition-colors group product-row" data-category="<?= $category_slug ?>" data-status="<?= $status_slug ?>">
                  <!-- Kode Obat -->
                  <td class="px-lg py-4 font-body-sm text-on-surface dark:text-slate-200 font-mono"><?= htmlspecialchars($product['kode_obat']) ?></td>
                  <!-- Nama & Gambar -->
                  <td class="px-lg py-4">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-lg bg-surface dark:bg-slate-900 border border-outline-variant dark:border-slate-800 overflow-hidden flex-shrink-0">
                        <img class="w-full h-full object-cover" alt="<?= htmlspecialchars($product['nama_obat']) ?>" src="<?= getProductImage($product['kode_obat'], $product['gambar_url']) ?>" />
                      </div>
                      <span class="font-body-md font-medium text-on-surface dark:text-slate-100"><?= htmlspecialchars($product['nama_obat']) ?></span>
                    </div>
                  </td>
                  <!-- Kategori -->
                  <td class="px-lg py-4">
                    <span class="px-3 py-1 bg-primary/10 dark:bg-primary-fixed-dim/20 text-primary dark:text-primary-fixed-dim rounded-full font-label-caps text-[10px] uppercase"><?= htmlspecialchars($product['nama_kategori']) ?></span>
                  </td>
                  <!-- Stok -->
                  <td class="px-lg py-4">
                    <div class="flex items-center gap-3">
                      <div class="w-24 h-2 bg-surface-container-high dark:bg-slate-900 rounded-full overflow-hidden">
                        <div class="h-full <?= $bar_color ?>" style="width: <?= $progress_pct ?>%"></div>
                      </div>
                      <span class="text-body-sm <?= $text_color_stok ?>"><?= $product['stok'] ?> Unit</span>
                    </div>
                  </td>
                  <!-- Harga -->
                  <td class="px-lg py-4 text-right font-body-md text-on-surface dark:text-slate-200"><?= formatRupiah($product['harga_jual']) ?></td>
                  <!-- Aksi (Edit, Delete, More) -->
                  <td class="px-lg py-4">
                    <div class="flex justify-center gap-2">
                      <!-- Tombol Edit yang menyimpan data produk di atribut data-* -->
                      <button class="btn-edit-product p-2 text-on-surface-variant dark:text-slate-400 hover:text-primary dark:hover:text-primary-fixed-dim transition-colors" 
                              data-id="<?= $product['id_obat'] ?>" 
                              data-nama="<?= htmlspecialchars($product['nama_obat']) ?>" 
                              data-stok="<?= $product['stok'] ?>" 
                              data-harga="<?= (int)$product['harga_jual'] ?>"
                              data-minimum="<?= $product['stok_minimum'] ?>"
                              data-gambar="<?= htmlspecialchars($product['gambar_url'] ?? '') ?>">
                        <span class="material-symbols-outlined text-[20px]">edit</span>
                      </button>
                      <button class="p-2 text-on-surface-variant dark:text-slate-400 hover:text-secondary dark:hover:text-red-400 transition-colors">
                        <span class="material-symbols-outlined text-[20px]">delete</span>
                      </button>
                      <button class="p-2 text-on-surface-variant dark:text-slate-400 hover:text-primary dark:hover:text-primary-fixed-dim transition-colors">
                        <span class="material-symbols-outlined text-[20px]">more_vert</span>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php
                endforeach;
              endif;
              ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination Shell -->
        <div class="px-lg py-4 bg-surface-container-low dark:bg-slate-900 border-t border-outline-variant dark:border-slate-800 flex flex-col md:flex-row items-center justify-between gap-md">
          <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-slate-400">Menampilkan halaman 1 dari 1</p>
          <div class="flex items-center gap-1">
            <button class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant dark:border-slate-800 hover:bg-surface dark:hover:bg-slate-800 text-on-surface dark:text-slate-300 transition-colors disabled:opacity-50" disabled="">
              <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <button class="w-10 h-10 flex items-center justify-center rounded-lg bg-primary text-on-primary font-bold">1</button>
            <button class="w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant dark:border-slate-800 hover:bg-surface dark:hover:bg-slate-800 text-on-surface dark:text-slate-300 transition-colors" disabled="">
              <span class="material-symbols-outlined">chevron_right</span>
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Edit Produk (Popup Form) -->
  <div id="edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-slate-800 rounded-3xl w-full max-w-3xl p-xl shadow-2xl transform scale-95 transition-transform duration-300 relative">
      <button id="close-edit-modal" class="absolute top-md right-md text-on-surface-variant dark:text-slate-400 hover:text-primary transition-colors flex items-center p-xs rounded-full hover:bg-surface-container-low dark:hover:bg-slate-800">
        <span class="material-symbols-outlined text-[24px]">close</span>
      </button>
      
      <h3 class="font-headline-md text-xl font-bold text-on-surface dark:text-slate-100 mb-lg flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">edit_note</span>
        Edit Data Skincare
      </h3>
      
      <form method="POST" action="" enctype="multipart/form-data" class="space-y-md">
        <input type="hidden" name="action" value="edit_product">
        <input type="hidden" name="id_obat" id="edit-id">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
          <!-- Left Column: Details -->
          <div class="space-y-md">
            <div class="space-y-xs">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Nama Produk</label>
              <input type="text" name="nama_obat" id="edit-nama" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
            </div>
            
            <div class="space-y-xs">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Stok Unit</label>
              <input type="number" name="stok" id="edit-stok" min="0" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
            </div>
            
            <div class="space-y-xs">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Harga Jual (Rp)</label>
              <input type="number" name="harga_jual" id="edit-harga" min="0" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
            </div>

            <div class="space-y-xs">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Stok Minimum (Batas Restok)</label>
              <input type="number" name="stok_minimum" id="edit-minimum" min="0" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
            </div>
          </div>

          <!-- Right Column: Image Uploader -->
          <div class="space-y-md flex flex-col justify-between">
            <!-- Drag-and-drop File Upload Container for Edit -->
            <div class="space-y-xs flex-1 flex flex-col justify-start">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Foto Produk Skincare (Drag &amp; Drop atau Pilih Baru)</label>
              
              <div id="edit-dropzone" class="flex-grow min-h-[180px] border-2 border-dashed border-outline-variant dark:border-slate-700 hover:border-primary dark:hover:border-primary rounded-2xl flex flex-col items-center justify-center p-md cursor-pointer transition-colors bg-surface-container-low/50 dark:bg-slate-900/50 relative overflow-hidden group">
                <input type="file" name="gambar_file" id="edit-file-input" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-20">
                
                <!-- Inner placeholder container -->
                <div id="edit-dropzone-placeholder" class="text-center space-y-2 z-10 pointer-events-none group-hover:scale-105 transition-transform duration-300">
                  <span class="material-symbols-outlined text-4xl text-primary/70 dark:text-primary-fixed-dim/70">cloud_upload</span>
                  <p class="text-xs font-semibold text-on-surface dark:text-slate-200">Geser file ke sini atau klik untuk mengganti foto</p>
                  <p class="text-[10px] text-on-surface-variant dark:text-slate-400">Format PNG, JPG, JPEG, atau WebP</p>
                </div>

                <!-- Preview container (displays current image first, then changes on upload) -->
                <img id="edit-image-preview" src="#" alt="Preview" class="absolute inset-0 w-full h-full object-contain hidden z-10 bg-surface dark:bg-slate-800 p-2">
              </div>
            </div>
          </div>
        </div>
        
        <div class="flex gap-md pt-lg border-t border-outline-variant/30 dark:border-slate-800 mt-lg justify-end">
          <button type="button" id="close-edit-modal-btn" class="px-6 py-3 border border-outline-variant dark:border-slate-700 text-on-surface-variant dark:text-slate-300 rounded-xl font-title-sm text-body-md hover:bg-surface-container-low dark:hover:bg-slate-800 transition-all text-center min-w-[120px]">
            Batal
          </button>
          <button type="submit" class="px-6 py-3 bg-primary text-on-primary rounded-xl font-title-sm text-body-md hover:brightness-110 transition-all text-center min-w-[150px]">
            Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Tambah Produk Baru (Popup Form) -->
  <div id="add-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-slate-800 rounded-3xl w-full max-w-3xl p-xl shadow-2xl transform scale-95 transition-transform duration-300 relative">
      <button id="close-add-modal" class="absolute top-md right-md text-on-surface-variant dark:text-slate-400 hover:text-primary transition-colors flex items-center p-xs rounded-full hover:bg-surface-container-low dark:hover:bg-slate-800">
        <span class="material-symbols-outlined text-[24px]">close</span>
      </button>
      
      <h3 class="font-headline-md text-xl font-bold text-on-surface dark:text-slate-100 mb-lg flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">add_circle</span>
        Tambah Produk Baru
      </h3>
      
      <form method="POST" action="" enctype="multipart/form-data" class="space-y-md">
        <input type="hidden" name="action" value="add_product">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
          <!-- Left Column: Product Details -->
          <div class="space-y-md">
            <div class="space-y-xs">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Kode Produk</label>
              <input type="text" name="kode_obat" placeholder="Contoh: OBT-006" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
            </div>

            <div class="space-y-xs">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Nama Produk</label>
              <input type="text" name="nama_obat" placeholder="Nama produk skincare" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
            </div>

            <div class="space-y-xs">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Kategori</label>
              <select name="id_kategori" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all cursor-pointer" required>
                <?php foreach ($categories as $cat) : ?>
                  <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nama_kategori']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="space-y-xs">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Satuan</label>
              <input type="text" name="satuan" placeholder="Contoh: tube, bottle, pcs" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
            </div>
            
            <div class="grid grid-cols-2 gap-sm">
              <div class="space-y-xs">
                <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Stok Awal</label>
                <input type="number" name="stok" min="0" value="0" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
              </div>
              <div class="space-y-xs">
                <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Stok Minimum</label>
                <input type="number" name="stok_minimum" min="0" value="10" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
              </div>
            </div>
          </div>

          <!-- Right Column: Price & Drag-and-drop Image Uploader -->
          <div class="space-y-md flex flex-col justify-between">
            <div class="grid grid-cols-2 gap-sm">
              <div class="space-y-xs">
                <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Harga Beli (Rp)</label>
                <input type="number" name="harga_beli" min="0" placeholder="0" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
              </div>
              <div class="space-y-xs">
                <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Harga Jual (Rp)</label>
                <input type="number" name="harga_jual" min="0" placeholder="0" class="w-full bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg p-3 text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" required>
              </div>
            </div>

            <!-- Drag-and-drop File Upload Container -->
            <div class="space-y-xs flex-1 flex flex-col justify-start">
              <label class="block font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400 uppercase tracking-wider">Foto Produk Skincare (Drag &amp; Drop atau Pilih)</label>
              
              <div id="add-dropzone" class="flex-grow min-h-[140px] border-2 border-dashed border-outline-variant dark:border-slate-700 hover:border-primary dark:hover:border-primary rounded-2xl flex flex-col items-center justify-center p-md cursor-pointer transition-colors bg-surface-container-low/50 dark:bg-slate-900/50 relative overflow-hidden group">
                <input type="file" name="gambar_file" id="add-file-input" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-20">
                
                <!-- Inner placeholder container -->
                <div id="add-dropzone-placeholder" class="text-center space-y-2 z-10 pointer-events-none group-hover:scale-105 transition-transform duration-300">
                  <span class="material-symbols-outlined text-4xl text-primary/70 dark:text-primary-fixed-dim/70">cloud_upload</span>
                  <p class="text-xs font-semibold text-on-surface dark:text-slate-200">Geser file ke sini atau klik untuk mencari</p>
                  <p class="text-[10px] text-on-surface-variant dark:text-slate-400">Hanya format PNG, JPG, JPEG, atau WebP</p>
                </div>

                <!-- Preview container -->
                <img id="add-image-preview" src="#" alt="Preview" class="absolute inset-0 w-full h-full object-contain hidden z-10 bg-surface dark:bg-slate-800 p-2">
              </div>
            </div>
          </div>
        </div>
        
        <div class="flex gap-md pt-lg border-t border-outline-variant/30 dark:border-slate-800 mt-lg justify-end">
          <button type="button" id="close-add-modal-btn" class="px-6 py-3 border border-outline-variant dark:border-slate-700 text-on-surface-variant dark:text-slate-300 rounded-xl font-title-sm text-body-md hover:bg-surface-container-low dark:hover:bg-slate-800 transition-all text-center min-w-[120px]">
            Batal
          </button>
          <button type="submit" class="px-6 py-3 bg-primary text-on-primary rounded-xl font-title-sm text-body-md hover:brightness-110 transition-all text-center min-w-[150px]">
            Tambah Produk
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Micro-interaction & Functionality Scripts -->
  <script src="../assets/js/dashboard-ui.js"></script>
  <script>
    // IIFE to handle dynamic updates and modals without DOMContentLoaded clashes
    (function() {
      // 1. Initialize theme toggling using shared utility
      setupThemeToggle('theme-toggle', 'dark-icon', 'light-icon');

      // Date display initialized in PHP server-side

      // 3. Search, Category Filter, and Status Filter functionality
      const searchInput = document.getElementById('search-input');
      const filterCategory = document.getElementById('filter-category');
      const filterStatus = document.getElementById('filter-status');
      const rows = document.querySelectorAll('.product-row');
      const countSpan = document.getElementById('product-count');

      function filterProducts() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedCategory = filterCategory.value.toLowerCase();
        const selectedStatus = filterStatus.value.toLowerCase();

        let visibleCount = 0;

        rows.forEach(row => {
          const productName = row.querySelector('td:nth-child(2) span').textContent.toLowerCase();
          const productCode = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
          const rowCategory = row.getAttribute('data-category').toLowerCase();
          const rowStatus = row.getAttribute('data-status').toLowerCase();

          const matchesQuery = query === '' || productName.includes(query) || productCode.includes(query);
          const matchesCategory = selectedCategory === 'all' || rowCategory === selectedCategory;
          const matchesStatus = selectedStatus === 'all' || rowStatus === selectedStatus;

          if (matchesQuery && matchesCategory && matchesStatus) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });

        // Update count text
        if (countSpan) {
          countSpan.textContent = `Menampilkan 1-${visibleCount} dari ${visibleCount} produk`;
        }
      }

      if (searchInput && filterCategory && filterStatus) {
        searchInput.addEventListener('input', filterProducts);
        filterCategory.addEventListener('change', filterProducts);
        filterStatus.addEventListener('change', filterProducts);
      }

      // Run initial filter to update counts
      filterProducts();

      // 4. Modal Edit Skincare functionality
      const editModal = document.getElementById('edit-modal');
      const closeEditModalBtn = document.getElementById('close-edit-modal');
      const closeEditModalBtn2 = document.getElementById('close-edit-modal-btn');
      const editImagePreview = document.getElementById('edit-image-preview');
      const editPlaceholder = document.getElementById('edit-dropzone-placeholder');

      function closeEditModal() {
        editModal.classList.add('opacity-0');
        editModal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
          editModal.classList.add('hidden');
        }, 300);
      }

      closeEditModalBtn?.addEventListener('click', closeEditModal);
      closeEditModalBtn2?.addEventListener('click', closeEditModal);

      document.querySelectorAll('.btn-edit-product').forEach(btn => {
        btn.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const nama = this.getAttribute('data-nama');
          const stok = this.getAttribute('data-stok');
          const harga = this.getAttribute('data-harga');
          const minimum = this.getAttribute('data-minimum');
          const gambar = this.getAttribute('data-gambar');

          document.getElementById('edit-id').value = id;
          document.getElementById('edit-nama').value = nama;
          document.getElementById('edit-stok').value = stok;
          document.getElementById('edit-harga').value = harga;
          document.getElementById('edit-minimum').value = minimum;

          // Update image preview in Edit Modal
          if (gambar && gambar !== '') {
            if (gambar.startsWith('http://') || gambar.startsWith('https://')) {
              editImagePreview.src = gambar;
            } else {
              editImagePreview.src = '../' + gambar;
            }
            editImagePreview.classList.remove('hidden');
            editPlaceholder.classList.add('hidden');
          } else {
            editImagePreview.src = '#';
            editImagePreview.classList.add('hidden');
            editPlaceholder.classList.remove('hidden');
          }

          editModal.classList.remove('hidden');
          setTimeout(() => {
            editModal.classList.remove('opacity-0');
            editModal.querySelector('div').classList.remove('scale-95');
          }, 10);
        });
      });

      // 5. Modal Tambah Produk Baru functionality
      const addModal = document.getElementById('add-modal');
      const openAddModalBtn = document.getElementById('btn-tambah-produk');
      const closeAddModalBtn = document.getElementById('close-add-modal');
      const closeAddModalBtn2 = document.getElementById('close-add-modal-btn');
      const addImagePreview = document.getElementById('add-image-preview');
      const addPlaceholder = document.getElementById('add-dropzone-placeholder');

      function openAddModal() {
        // Reset uploader in Add Modal on open
        document.getElementById('add-file-input').value = '';
        addImagePreview.src = '#';
        addImagePreview.classList.add('hidden');
        addPlaceholder.classList.remove('hidden');

        addModal.classList.remove('hidden');
        setTimeout(() => {
          addModal.classList.remove('opacity-0');
          addModal.querySelector('div').classList.remove('scale-95');
        }, 10);
      }

      function closeAddModal() {
        addModal.classList.add('opacity-0');
        addModal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
          addModal.classList.add('hidden');
        }, 300);
      }

      openAddModalBtn?.addEventListener('click', openAddModal);
      closeAddModalBtn?.addEventListener('click', closeAddModal);
      closeAddModalBtn2?.addEventListener('click', closeAddModal);

      // File input change preview handlers
      const addDropzone = document.getElementById('add-dropzone');
      const addFileInput = document.getElementById('add-file-input');

      if (addDropzone && addFileInput) {
        addFileInput.addEventListener('dragenter', () => {
          addDropzone.classList.add('border-primary', 'bg-primary/5', 'dark:bg-primary-fixed-dim/5');
        });
        addFileInput.addEventListener('dragover', () => {
          addDropzone.classList.add('border-primary', 'bg-primary/5', 'dark:bg-primary-fixed-dim/5');
        });
        addFileInput.addEventListener('dragleave', () => {
          addDropzone.classList.remove('border-primary', 'bg-primary/5', 'dark:bg-primary-fixed-dim/5');
        });
        addFileInput.addEventListener('drop', () => {
          addDropzone.classList.remove('border-primary', 'bg-primary/5', 'dark:bg-primary-fixed-dim/5');
        });
      }

      const editDropzone = document.getElementById('edit-dropzone');
      const editFileInput = document.getElementById('edit-file-input');

      if (editDropzone && editFileInput) {
        editFileInput.addEventListener('dragenter', () => {
          editDropzone.classList.add('border-primary', 'bg-primary/5', 'dark:bg-primary-fixed-dim/5');
        });
        editFileInput.addEventListener('dragover', () => {
          editDropzone.classList.add('border-primary', 'bg-primary/5', 'dark:bg-primary-fixed-dim/5');
        });
        editFileInput.addEventListener('dragleave', () => {
          editDropzone.classList.remove('border-primary', 'bg-primary/5', 'dark:bg-primary-fixed-dim/5');
        });
        editFileInput.addEventListener('drop', () => {
          editDropzone.classList.remove('border-primary', 'bg-primary/5', 'dark:bg-primary-fixed-dim/5');
        });
      }

      addFileInput?.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            addImagePreview.src = e.target.result;
            addImagePreview.classList.remove('hidden');
            addPlaceholder.classList.add('hidden');
          };
          reader.readAsDataURL(file);
        } else {
          addImagePreview.src = '#';
          addImagePreview.classList.add('hidden');
          addPlaceholder.classList.remove('hidden');
        }
      });

      editFileInput?.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(e) {
            editImagePreview.src = e.target.result;
            editImagePreview.classList.remove('hidden');
            editPlaceholder.classList.add('hidden');
          };
          reader.readAsDataURL(file);
        }
      });
    })();
  </script>
</body>

</html>
