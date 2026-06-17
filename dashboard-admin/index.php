<?php
/**
 * ============================================================
 * FILE: dashboard-admin/index.php (sebelumnya index.html)
 * DESKRIPSI: Dashboard Ringkasan untuk Admin Klinik GlowSkin.
 * ============================================================
 * File ini menampilkan data live (real-time) dari database:
 * 1. Ringkasan Card Atas:
 *    - Total Pasien Terdaftar (COUNT)
 *    - Kunjungan Hari Ini (COUNT dengan filter CURDATE)
 *    - Pendapatan Bulan Ini (SUM grand_total)
 *    - Obat/Produk Perlu Restock (COUNT stok rendah)
 * 2. Tabel "Top 3 Perawatan Terlaris" (LEFT JOIN query).
 * ============================================================
 */

// --- SERTAKAN FILE KONEKSI DATABASE ---
require_once __DIR__ . '/../koneksi.php';

// --- TANGKAP TANGGAL TERPILIH DARI KALENDER (GET) ---
// Jika tidak ada parameter tanggal, default ke hari ini (date('Y-m-d'))
$tanggal_pilihan = $_GET['tanggal'] ?? date('Y-m-d');

/**
 * ============================================================
 * QUERY DASHBOARD 1: RINGKASAN CARD ATAS (4 STATISTIK UTAMA)
 * ============================================================
 * Setiap query dijalankan secara terpisah untuk kejelasan.
 * Kita menggunakan try-catch agar jika satu query gagal,
 * halaman tetap bisa ditampilkan dengan nilai default 0.
 */

// --- QUERY 1A: TOTAL PASIEN TERDAFTAR ---
// Menghitung jumlah seluruh baris di tabel 'pasien'.
try {
    $stmt_total_pasien = $pdo->query("SELECT COUNT(*) AS total FROM pasien");
    $total_pasien = $stmt_total_pasien->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $total_pasien = 0; // Nilai default jika query gagal
}

// --- QUERY 1B: KUNJUNGAN HARI INI / TANGGAL TERPILIH ---
// Menghitung jumlah kunjungan yang tanggal_kunjungannya = tanggal pilihan.
try {
    $stmt_kunjungan_hari_ini = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM kunjungan
        WHERE DATE(tanggal_kunjungan) = :tanggal
    ");
    $stmt_kunjungan_hari_ini->execute([':tanggal' => $tanggal_pilihan]);
    $kunjungan_hari_ini = $stmt_kunjungan_hari_ini->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $kunjungan_hari_ini = 0;
}

// --- QUERY 1C: PENDAPATAN BULAN INI ---
// Menjumlahkan (SUM) kolom 'grand_total' di tabel 'transaksi'
// yang dibuat pada bulan dan tahun yang sama dengan saat ini.
try {
    $stmt_pendapatan = $pdo->query("
        SELECT COALESCE(SUM(grand_total), 0) AS total_pendapatan
        FROM transaksi
        WHERE MONTH(tanggal_transaksi) = MONTH(CURDATE())
          AND YEAR(tanggal_transaksi)  = YEAR(CURDATE())
    ");
    $pendapatan_bulan_ini = $stmt_pendapatan->fetch()['total_pendapatan'] ?? 0;
} catch (PDOException $e) {
    $pendapatan_bulan_ini = 0;
}

// --- QUERY 1D: OBAT/PRODUK PERLU RESTOCK ---
// Menghitung jumlah produk di tabel 'produk' yang stoknya
// kurang dari atau sama dengan batas minimum (misal: stok_minimum).
// Jika kolom stok_minimum tidak ada, kita pakai angka tetap (misal: 10).
try {
    $stmt_restock = $pdo->query("
        SELECT COUNT(*) AS total
        FROM produk
        WHERE stok <= stok_minimum
    ");
    $obat_restock = $stmt_restock->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $obat_restock = 0;
}

/**
 * ============================================================
 * QUERY LAPORAN 2: TOP 3 PERAWATAN TERLARIS
 * ============================================================
 * Mengambil 3 layanan/perawatan teratas berdasarkan jumlah transaksi.
 * Menggunakan LEFT JOIN agar data layanan tetap muncul meskipun
 * belum pernah ada transaksi sama sekali (menghindari bug data kosong).
 *
 * Alur JOIN:
 * layanan (tabel utama)
 *   └─ LEFT JOIN detail_transaksi (untuk menghitung berapa kali dipesan)
 *
 * GROUP BY digunakan untuk mengelompokkan per layanan.
 * ORDER BY jumlah_transaksi DESC untuk mengurutkan dari terbanyak.
 * LIMIT 3 untuk mengambil hanya 3 teratas.
 */
try {
    $stmt_top_perawatan = $pdo->query("
        SELECT
            l.nama_layanan,
            COUNT(dt.id_detail_transaksi) AS jumlah_transaksi
        FROM layanan l
        LEFT JOIN detail_transaksi dt ON l.id_layanan = dt.id_layanan
        GROUP BY l.id_layanan, l.nama_layanan
        ORDER BY jumlah_transaksi DESC
        LIMIT 3
    ");
    $top_perawatan = $stmt_top_perawatan->fetchAll();
} catch (PDOException $e) {
    $top_perawatan = []; // Array kosong jika query gagal
}

/**
 * ============================================================
 * QUERY DASHBOARD 3: 5 RESERVASI TERBARU (DATA LIVE)
 * ============================================================
 * Mengambil 5 kunjungan/booking terbaru dari tabel 'kunjungan'.
 * Kita lakukan JOIN dengan tabel pasien, dokter, status kunjungan,
 * dan detail_layanan untuk memunculkan profil lengkap pendaftar.
 */
try {
    $stmt_recent = $pdo->query("
        SELECT
            p.nama_lengkap AS nama_pasien,
            p.no_telepon,
            COALESCE(GROUP_CONCAT(l.nama_layanan SEPARATOR ', '), 'Konsultasi Umum') AS jenis_perawatan,
            d.nama_lengkap AS nama_dokter,
            k.tanggal_kunjungan,
            k.waktu_daftar,
            rsk.nama AS status_nama,
            rsk.warna AS status_warna
        FROM kunjungan k
        JOIN pasien p ON k.id_pasien = p.id_pasien
        JOIN dokter d ON k.id_dokter = d.id_dokter
        JOIN ref_status_kunjungan rsk ON k.id_status = rsk.id
        LEFT JOIN detail_layanan dl ON k.id_kunjungan = dl.id_kunjungan
        LEFT JOIN layanan l ON dl.id_layanan = l.id_layanan
        GROUP BY k.id_kunjungan
        ORDER BY k.tanggal_kunjungan DESC, k.waktu_daftar DESC, k.id_kunjungan DESC
        LIMIT 5
    ");
    $recent_reservations = $stmt_recent->fetchAll();
} catch (PDOException $e) {
    $recent_reservations = [];
}

/**
 * ============================================================
 * FUNGSI HELPER: FORMAT ANGKA RUPIAH
 * ============================================================
 * Mengubah angka mentah (misal: 184200000) menjadi format
 * Rupiah Indonesia yang mudah dibaca (misal: Rp 184.200.000).
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>GlowSkin Admin | Dashboard Ringkasan</title>
    
    <!-- Scripts & Styles -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="../assets/js/theme-toggle.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css" />

    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "inverse-on-surface": "#eff1f3",
              "inverse-primary": "#4fdbc8",
              "primary-container": "var(--primary-container)",
              "inverse-surface": "#2d3133",
              "on-secondary-fixed": "#40000d",
              "surface-container-low": "var(--surface-container-low)",
              "surface": "var(--surface)",
              "on-background": "#191c1e",
              "surface-bright": "var(--surface-lowest)",
              "on-error": "#ffffff",
              "on-tertiary-fixed": "#0b1c30",
              "on-error-container": "#93000a",
              "on-primary-fixed": "#00201c",
              "on-surface-variant": "var(--on-surface-variant)",
              "outline-variant": "var(--outline-variant)",
              "on-secondary-fixed-variant": "#92002a",
              "surface-container-high": "var(--surface-container-high)",
              "background": "var(--surface)",
              "secondary-fixed": "#ffdadb",
              "secondary": "#b90538",
              "tertiary": "#505f76",
              "outline": "var(--outline)",
              "on-tertiary-fixed-variant": "#38485d",
              "on-tertiary": "#ffffff",
              "error": "#ba1a1a",
              "surface-container-lowest": "var(--surface-container-lowest)",
              "on-primary": "#ffffff",
              "on-surface": "var(--on-surface)",
              "on-secondary-container": "#fffbff",
              "secondary-container": "#dc2c4f",
              "surface-container": "var(--surface-container)",
              "primary": "var(--primary)",
              "secondary-fixed-dim": "#ffb2b7",
              "on-tertiary-container": "#2b3b50",
              "primary-fixed": "#71f8e4",
              "error-container": "#ffdad6",
              "surface-container-highest": "var(--surface-container-highest)",
              "on-secondary": "#ffffff",
              "primary-fixed-dim": "#4fdbc8",
              "tertiary-container": "#95a5be",
              "tertiary-fixed": "#d3e4fe",
              "on-primary-container": "#00423b",
              "tertiary-fixed-dim": "#b7c8e1",
              "surface-variant": "var(--surface-container-highest)",
              "surface-tint": "var(--primary)",
              "surface-dim": "var(--surface-dim)",
              "on-primary-fixed-variant": "#005048"
            },
            borderRadius: {
              "DEFAULT": "0.125rem",
              "lg": "0.25rem",
              "xl": "0.5rem",
              "full": "0.75rem"
            },
            spacing: {
              "xs": "4px",
              "sm": "8px",
              "md": "16px",
              "container-max": "1440px",
              "sidebar-width": "260px",
              "lg": "24px",
              "xl": "40px",
              "base": "4px"
            },
            fontFamily: {
              "body-sm": ["Plus Jakarta Sans"],
              "label-caps": ["Plus Jakarta Sans"],
              "display-lg-mobile": ["Plus Jakarta Sans"],
              "body-md": ["Plus Jakarta Sans"],
              "title-sm": ["Plus Jakarta Sans"],
              "headline-md": ["Plus Jakarta Sans"],
              "display-lg": ["Plus Jakarta Sans"]
            },
            fontSize: {
              "body-sm": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
              "label-caps": ["12px", { "lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "700" }],
              "display-lg-mobile": ["32px", { "lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
              "body-md": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
              "title-sm": ["18px", { "lineHeight": "24px", "fontWeight": "600" }],
              "headline-md": ["24px", { "lineHeight": "32px", "fontWeight": "600" }],
              "display-lg": ["40px", { "lineHeight": "48px", "letterSpacing": "-0.02em", "fontWeight": "700" }]
            }
          }
        }
      };
    </script>
  </head>
  <body class="bg-surface dark:bg-[#0f172a] text-on-surface dark:text-[#f1f5f9] font-body-md transition-colors duration-300">
    <!-- SideNavBar Shell -->
    <aside class="fixed h-full w-[260px] left-0 top-0 flex flex-col py-lg border-r border-outline-variant dark:border-slate-800 bg-surface-container-lowest dark:bg-[#0f172a] z-50">
      <div class="px-lg mb-xl">
        <h1 class="font-display-lg text-display-lg text-primary dark:text-primary-fixed-dim tracking-tight">GlowSkin</h1>
        <p class="font-label-caps text-label-caps text-on-surface-variant/60 dark:text-slate-400 tracking-widest uppercase">Admin Console</p>
      </div>

      <nav class="flex-1 space-y-1">
        <!-- Active Tab -->
        <a class="group relative flex items-center px-lg py-3 text-primary dark:text-primary-fixed-dim font-bold border-l-4 border-primary dark:border-primary-fixed-dim bg-primary/5 transition-all duration-200" href="index.php">
          <span class="material-symbols-outlined mr-3">dashboard</span>
          <span class="font-label-caps text-label-caps">Ringkasan Dashboard</span>
        </a>
        <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="rekam-medis.html">
          <span class="material-symbols-outlined mr-3">medical_services</span>
          <span class="font-label-caps text-label-caps">Data Pasien &amp; Rekam Medis</span>
        </a>
        <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="inventori.html">
          <span class="material-symbols-outlined mr-3">inventory_2</span>
          <span class="font-label-caps text-label-caps">Inventori Produk Skincare</span>
        </a>
        <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="log-keamanan.html">
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
          <!-- Left top header spacer -->
        </div>

        <div class="flex items-center gap-6 ml-lg">
          <!-- Theme Toggle Button -->
          <button class="relative text-on-surface-variant dark:text-slate-300 hover:text-primary transition-colors flex items-center" id="theme-toggle">
            <span class="material-symbols-outlined hidden" id="dark-icon">light_mode</span>
            <span class="material-symbols-outlined" id="light-icon">dark_mode</span>
          </button>

          <button class="relative text-on-surface-variant dark:text-slate-300 hover:text-primary transition-colors">
            <span class="material-symbols-outlined">notifications</span>
            <span class="absolute top-0 right-0 w-2 h-2 bg-secondary rounded-full border-2 border-surface-container-lowest dark:border-[#0f172a]"></span>
          </button>

          <div class="flex items-center gap-2 text-on-surface-variant dark:text-slate-300 bg-surface-container-low/50 dark:bg-slate-800/50 px-3 py-1.5 rounded-lg border border-outline-variant/30 dark:border-slate-800">
            <span class="material-symbols-outlined text-[18px]">calendar_today</span>
            <input type="date" id="calendar-select" value="<?= $tanggal_pilihan ?>" class="bg-transparent border-none p-0 text-body-sm font-medium focus:ring-0 outline-none text-on-surface cursor-pointer dark:text-white" onchange="window.location.href = '?tanggal=' + this.value;">
          </div>

          <div class="h-8 w-px bg-outline-variant dark:bg-slate-800"></div>

          <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
              <p class="font-title-sm text-title-sm leading-none text-primary">Dr. Sarah Wijaya</p>
              <p class="font-label-caps text-[10px] text-on-surface-variant dark:text-slate-400">SUPER ADMIN</p>
            </div>
            <div class="w-10 h-10 rounded-full border-2 border-primary-container overflow-hidden">
              <img alt="Administrator Profile" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCvj1BVNZb6Yrkc_b_HOHa5pfWWvoL5tW5HxnVdZERiif6OwI3aNZ8FDP2oycGimvuC4M8rNibiYEfnvjAw66DBFbPAo5ScqoW7vmXcgCaO7wZxYK2Kg8ammdNGCcPZkCqR0mrun9dK9rYA9EP1Q9AJEn4khxh3RMxXKeVYh3OZ3nzisYhQlWS3pz578DuOX02UIO-1fSOmcXMnCXClJEwjTI1Bz9y9qrr8v5KPm22Xt0OHseZDh77F8QnNhjNLWiZzsSmrGgAax5M"/>
            </div>
          </div>
        </div>
      </header>

      <!-- Page Canvas -->
      <main class="mt-16 p-xl flex-1 max-w-[1440px] mx-auto w-full bg-background dark:bg-transparent">
        <!-- Header Section -->
        <div class="mb-xl">
          <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface">Ringkasan Utama</h2>
          <p class="text-on-surface-variant font-body-md mt-1">Pantau statistik klinik, reservasi masuk, serta ketersediaan barang secara terpusat.</p>
        </div>

        <!-- Bento Stats Grid -->
        <?php
        /**
         * ============================================================
         * BLOK PHP: MENAMPILKAN DATA LIVE PADA CARD RINGKASAN
         * ============================================================
         * Data yang sudah di-query di atas kini ditampilkan secara
         * dinamis di dalam card-card HTML menggunakan <?= (shorthand echo).
         * Angka-angka statis HTML sebelumnya diganti dengan variabel PHP.
         */
        ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-lg mb-xl">
          <!-- Stat 1: Total Pasien Terdaftar -->
          <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant/30 dark:border-slate-800 flex items-start gap-4 h-36 relative overflow-hidden shadow-sm">
            <div class="p-2 bg-primary/10 rounded-lg text-primary dark:text-primary-fixed-dim flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined text-[20px]">book_online</span>
            </div>
            <div class="flex-grow flex flex-col justify-between h-full">
              <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Total Pasien Terdaftar</p>
                <!-- DATA LIVE: Menampilkan total pasien dari database -->
                <h3 class="text-2xl font-bold text-on-surface dark:text-slate-100 mt-1"><?= number_format($total_pasien, 0, ',', '.') ?></h3>
              </div>
              <p class="text-xs text-green-600 dark:text-green-400 flex items-center gap-xs">
                <span class="material-symbols-outlined text-[14px]">groups</span>
                Seluruh pasien terdaftar
              </p>
            </div>
          </div>

          <!-- Stat 2: Kunjungan Hari Ini -->
          <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant/30 dark:border-slate-800 flex items-start gap-4 h-36 relative overflow-hidden shadow-sm">
            <div class="p-2 bg-primary/10 rounded-lg text-primary dark:text-primary-fixed-dim flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined text-[20px]">person_add</span>
            </div>
            <div class="flex-grow flex flex-col justify-between h-full">
              <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase"><?= $tanggal_pilihan == date('Y-m-d') ? 'Kunjungan Hari Ini' : 'Kunjungan Terpilih' ?></p>
                <!-- DATA LIVE: Menampilkan jumlah kunjungan hari ini -->
                <h3 class="text-2xl font-bold text-on-surface dark:text-slate-100 mt-1"><?= number_format($kunjungan_hari_ini, 0, ',', '.') ?></h3>
              </div>
              <p class="text-xs text-green-600 dark:text-green-400 flex items-center gap-xs">
                <span class="material-symbols-outlined text-[14px]">today</span>
                <?= $tanggal_pilihan == date('Y-m-d') ? 'Data hari ini (real-time)' : 'Data tanggal ' . date('d/m/Y', strtotime($tanggal_pilihan)) ?>
              </p>
            </div>
          </div>

          <!-- Stat 3: Pendapatan Bulan Ini -->
          <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant/30 dark:border-slate-800 flex items-start gap-4 h-36 relative overflow-hidden shadow-sm">
            <div class="p-2 bg-primary/10 rounded-lg text-primary dark:text-primary-fixed-dim flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined text-[20px]">payments</span>
            </div>
            <div class="flex-grow flex flex-col justify-between h-full">
              <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Pendapatan Bulan Ini</p>
                <!-- DATA LIVE: Menampilkan SUM grand_total dari tabel transaksi -->
                <h3 class="text-2xl font-bold text-on-surface dark:text-slate-100 mt-1"><?= formatRupiah($pendapatan_bulan_ini) ?></h3>
              </div>
              <p class="text-xs text-green-600 dark:text-green-400 flex items-center gap-xs">
                <span class="material-symbols-outlined text-[14px]">trending_up</span>
                Akumulasi bulan berjalan
              </p>
            </div>
          </div>

          <!-- Stat 4: Produk Perlu Restock -->
          <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant/30 dark:border-slate-800 flex items-start gap-4 h-36 relative overflow-hidden shadow-sm">
            <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg text-red-600 dark:text-red-400 flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined text-[20px]">warning</span>
            </div>
            <div class="flex-grow flex flex-col justify-between h-full">
              <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Peringatan Inventori</p>
                <!-- DATA LIVE: Menampilkan jumlah produk yang perlu restock -->
                <h3 class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1"><?= $obat_restock ?> Item</h3>
              </div>
              <p class="text-xs text-red-600 dark:text-red-400 flex items-center gap-xs">
                <span class="material-symbols-outlined text-[14px]">warning</span>
                Butuh restock segera
              </p>
            </div>
          </div>
        </div>

        <!-- Top 3 Perawatan Terlaris Table -->
        <?php
        /**
         * ============================================================
         * BLOK PHP: TABEL TOP 3 PERAWATAN TERLARIS
         * ============================================================
         * Data dari query LEFT JOIN di atas di-loop menggunakan foreach.
         * LEFT JOIN digunakan agar layanan yang belum pernah ada
         * transaksinya tetap muncul dengan jumlah_transaksi = 0.
         * Ini mencegah bug "tabel kosong" jika data masih sedikit.
         */
        ?>
        <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-slate-800 rounded-2xl p-xl shadow-sm space-y-md mb-xl">
          <div class="flex justify-between items-center">
            <div>
              <h2 class="font-title-sm text-title-sm font-bold text-on-surface dark:text-slate-100">Top 3 Perawatan Terlaris</h2>
              <p class="text-body-sm text-on-surface-variant dark:text-slate-400">Perawatan paling sering dipesan berdasarkan data transaksi.</p>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-outline-variant/30 dark:border-slate-800 text-label-caps text-on-surface-variant dark:text-slate-400">
                  <th class="py-md">No</th>
                  <th class="py-md">Nama Layanan / Perawatan</th>
                  <th class="py-md">Jumlah Transaksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/20 dark:divide-slate-800/50 text-body-sm">
                <?php
                /**
                 * --- PERULANGAN FOREACH: MENAMPILKAN DATA TOP 3 PERAWATAN ---
                 * Jika array $top_perawatan kosong (tidak ada data),
                 * tampilkan pesan "Belum ada data" di dalam tabel.
                 * Jika ada data, loop setiap baris dan tampilkan di <tr>.
                 */
                if (empty($top_perawatan)) :
                ?>
                  <!-- Pesan jika belum ada data perawatan di database -->
                  <tr>
                    <td colspan="3" class="py-md text-center text-on-surface-variant dark:text-slate-400">
                      Belum ada data perawatan yang tersedia.
                    </td>
                  </tr>
                <?php else :
                    // --- VARIABEL COUNTER untuk nomor urut ---
                    $nomor = 1;
                    foreach ($top_perawatan as $perawatan) :
                ?>
                  <tr class="text-on-surface-variant dark:text-slate-300">
                    <!-- Nomor Urut -->
                    <td class="py-md font-bold text-on-surface dark:text-slate-100"><?= $nomor++ ?></td>
                    <!-- Nama Layanan dari database -->
                    <td class="py-md font-bold text-on-surface dark:text-slate-100"><?= htmlspecialchars($perawatan['nama_layanan']) ?></td>
                    <!-- Jumlah Transaksi dari hasil COUNT -->
                    <td class="py-md">
                      <span class="inline-flex items-center gap-xs px-sm py-1 rounded-full bg-primary/10 dark:bg-primary/20 text-primary dark:text-primary-fixed-dim font-bold text-[12px]">
                        <?= number_format($perawatan['jumlah_transaksi'], 0, ',', '.') ?> transaksi
                      </span>
                    </td>
                  </tr>
                <?php
                    endforeach;
                endif;
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Recent Reservations Table (tetap statis sebagai placeholder) -->
        <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-slate-800 rounded-2xl p-xl shadow-sm space-y-md">
          <div class="flex justify-between items-center">
            <div>
              <h2 class="font-title-sm text-title-sm font-bold text-on-surface dark:text-slate-100">Reservasi Terbaru</h2>
              <p class="text-body-sm text-on-surface-variant dark:text-slate-400">Menampilkan booking aktif terbaru dari pasien.</p>
            </div>
            <button class="border border-outline dark:border-slate-700 px-md py-sm rounded-lg font-title-sm text-body-sm dark:text-slate-300 hover:bg-surface-container-low dark:hover:bg-slate-800 transition-all">
              Lihat Semua
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-outline-variant/30 dark:border-slate-800 text-label-caps text-on-surface-variant dark:text-slate-400">
                  <th class="py-md">Nama Pasien</th>
                  <th class="py-md">WhatsApp / HP</th>
                  <th class="py-md">Jenis Perawatan</th>
                  <th class="py-md">Dokter</th>
                  <th class="py-md">Tanggal &amp; Waktu</th>
                  <th class="py-md">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/20 dark:divide-slate-800/50 text-body-sm">
                <?php
                /**
                 * ============================================================
                 * BLOK PHP: DAFTAR RESERVASI TERBARU (DATA LIVE)
                 * ============================================================
                 * Menampilkan 5 reservasi teratas yang baru masuk.
                 * Jika list kosong, tampilkan pesan informatif.
                 */
                if (empty($recent_reservations)) :
                ?>
                  <tr>
                    <td colspan="6" class="py-md text-center text-on-surface-variant dark:text-slate-400">
                      Belum ada data reservasi baru saat ini.
                    </td>
                  </tr>
                <?php else :
                    foreach ($recent_reservations as $reservation) :
                        // Format tanggal & waktu ke bentuk yang lebih mudah dibaca
                        $tanggal_raw = strtotime($reservation['tanggal_kunjungan']);
                        $waktu_raw = strtotime($reservation['waktu_daftar']);
                        $tanggal_formatted = date('j M Y', $tanggal_raw);
                        $waktu_formatted = date('H:i', $waktu_raw);
                        
                        // Menentukan warna badge status secara dinamis dari database
                        $badge_class = 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300'; // Default: yellow
                        if ($reservation['status_warna'] === 'green') {
                            $badge_class = 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
                        } elseif ($reservation['status_warna'] === 'blue') {
                            $badge_class = 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
                        } elseif ($reservation['status_warna'] === 'red') {
                            $badge_class = 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
                        }
                ?>
                  <tr class="text-on-surface-variant dark:text-slate-300">
                    <!-- Nama Pasien -->
                    <td class="py-md font-bold text-on-surface dark:text-slate-100"><?= htmlspecialchars($reservation['nama_pasien']) ?></td>
                    <!-- WhatsApp -->
                    <td class="py-md"><?= htmlspecialchars($reservation['no_telepon']) ?></td>
                    <!-- Jenis Perawatan (dari detail_layanan) -->
                    <td class="py-md"><?= htmlspecialchars($reservation['jenis_perawatan']) ?></td>
                    <!-- Dokter yang dipilih -->
                    <td class="py-md"><?= htmlspecialchars($reservation['nama_dokter']) ?></td>
                    <!-- Tanggal & Waktu pendaftaran -->
                    <td class="py-md"><?= "{$tanggal_formatted}, {$waktu_formatted}" ?></td>
                    <!-- Badge Status Kunjungan -->
                    <td class="py-md">
                      <span class="inline-flex items-center gap-xs px-sm py-1 rounded-full <?= $badge_class ?> font-bold text-[12px]">
                        <?= htmlspecialchars($reservation['status_nama']) ?>
                      </span>
                    </td>
                  </tr>
                <?php
                    endforeach;
                endif;
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>

    <script src="../assets/js/dashboard-ui.js"></script>
    <script>
      // Initialize theme toggling
      setupThemeToggle('theme-toggle', 'dark-icon', 'light-icon');

      // Initialize current date display (Indonesian Locale)
      const dateDisplay = document.getElementById('current-date-display');
      if (dateDisplay) {
        const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        dateDisplay.textContent = new Date().toLocaleDateString('id-ID', options);
      }
    </script>
  </body>
</html>
