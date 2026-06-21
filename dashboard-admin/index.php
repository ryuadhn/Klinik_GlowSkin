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

// --- QUERY DASHBOARD 1: RINGKASAN CARD ATAS (MENGGUNAKAN VIEW v_dashboard_admin_cards) ---
try {
    if ($tanggal_pilihan == date('Y-m-d')) {
        $stmt_cards = $pdo->query("SELECT * FROM v_dashboard_admin_cards");
        $admin_cards = $stmt_cards->fetch() ?: [];
        $total_pasien = $admin_cards['total_pasien'] ?? 0;
        $kunjungan_hari_ini = $admin_cards['kunjungan_hari_ini'] ?? 0;
        $pendapatan_bulan_ini = $admin_cards['pendapatan_bulan_ini'] ?? 0;
        $obat_restock = $admin_cards['obat_perlu_restock'] ?? 0;
    } else {
        // Query dinamis berdasarkan tanggal pilihan
        $stmt_total_pasien = $pdo->query("SELECT COUNT(*) FROM pasien");
        $total_pasien = $stmt_total_pasien->fetchColumn() ?: 0;

        $stmt_kunjungan = $pdo->prepare("SELECT COUNT(*) FROM kunjungan WHERE tanggal_kunjungan = ?");
        $stmt_kunjungan->execute([$tanggal_pilihan]);
        $kunjungan_hari_ini = $stmt_kunjungan->fetchColumn() ?: 0;

        $stmt_pendapatan = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM pembayaran WHERE status_bayar = 'lunas' AND MONTH(tanggal_bayar) = MONTH(?) AND YEAR(tanggal_bayar) = YEAR(?)");
        $stmt_pendapatan->execute([$tanggal_pilihan, $tanggal_pilihan]);
        $pendapatan_bulan_ini = $stmt_pendapatan->fetchColumn() ?: 0;

        $stmt_restock = $pdo->query("SELECT COUNT(*) FROM obat WHERE stok <= stok_minimum AND is_active = TRUE");
        $obat_restock = $stmt_restock->fetchColumn() ?: 0;
    }
} catch (PDOException $e) {
    $total_pasien = 0;
    $kunjungan_hari_ini = 0;
    $pendapatan_bulan_ini = 0;
    $obat_restock = 0;
}

/**
 * ============================================================
 * QUERY LAPORAN 2: TOP 3 PERAWATAN TERLARIS
 * ============================================================
 * Mengambil 3 layanan/perawatan teratas berdasarkan jumlah transaksi.
 * Menggunakan LEFT JOIN agar data layanan tetap muncul meskipun
 * belum pernah ada pendaftaran layanan sama sekali (menghindari bug data kosong).
 *
 * Alur JOIN:
 * layanan (tabel utama)
 *   └─ LEFT JOIN detail_layanan (untuk menghitung berapa kali dipesan)
 *
 * GROUP BY digunakan untuk mengelompokkan per layanan.
 * ORDER BY jumlah_transaksi DESC untuk mengurutkan dari terbanyak.
 * LIMIT 3 untuk mengambil hanya 3 teratas.
 */
try {
    $stmt_top_perawatan = $pdo->query("
        SELECT
            l.nama_layanan,
            COUNT(dl.id_detail) AS jumlah_transaksi
        FROM layanan l
        LEFT JOIN detail_layanan dl ON l.id_layanan = dl.id_layanan
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
    if ($tanggal_pilihan == date('Y-m-d')) {
        $stmt_antrian = $pdo->query("SELECT * FROM v_dashboard_admin_antrian");
        $antrian_pasien = $stmt_antrian->fetchAll();
    } else {
        // Query dinamis berdasarkan tanggal pilihan
        $stmt_antrian = $pdo->prepare("
            SELECT 
                k.no_antrian,
                p.kode_pasien,
                p.nama_lengkap,
                d.nama_lengkap AS dokter,
                k.keluhan_utama,
                sk.nama AS status,
                k.waktu_daftar
            FROM kunjungan k
            JOIN pasien p ON k.id_pasien = p.id_pasien
            JOIN dokter d ON k.id_dokter = d.id_dokter
            JOIN ref_status_kunjungan sk ON k.id_status = sk.id
            WHERE k.tanggal_kunjungan = ?
            ORDER BY k.no_antrian
        ");
        $stmt_antrian->execute([$tanggal_pilihan]);
        $antrian_pasien = $stmt_antrian->fetchAll();
    }
} catch (PDOException $e) {
    $antrian_pasien = [];
}

// --- FUNGSI HELPER: FORMAT ANGKA RUPIAH ---
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// --- QUERY 5 VIEW UNTUK LAPORAN MANAJEMEN (BAB VII) ---

// 1. Mengambil data statistik kunjungan pasien per bulan
try {
    $v_kunjungan_bulanan = $pdo->query("SELECT * FROM v_laporan_kunjungan_bulanan")->fetchAll();
} catch (PDOException $e) {
    $v_kunjungan_bulanan = [];
}

// 2. Mengambil data jenis perawatan yang paling banyak dipesan
try {
    $v_layanan_terlaris = $pdo->query("SELECT * FROM v_laporan_layanan_terlaris")->fetchAll();
} catch (PDOException $e) {
    $v_layanan_terlaris = [];
}

// 3. Mengambil data pasien dengan frekuensi kunjungan paling aktif
try {
    $v_pasien_teraktif = $pdo->query("SELECT * FROM v_laporan_pasien_teraktif")->fetchAll();
} catch (PDOException $e) {
    $v_pasien_teraktif = [];
}

// 4. Mengambil data obat/skincare dengan stok di bawah batas minimum (perlu restok)
try {
    $v_stok_minimum = $pdo->query("SELECT * FROM v_laporan_stok_minimum")->fetchAll();
} catch (PDOException $e) {
    $v_stok_minimum = [];
}

// 5. Mengambil data kategori & status pasien (VIP/Reguler - dikontrol oleh admin)
try {
    $v_status_pasien = $pdo->query("
        SELECT 
            p.id_pasien,
            p.kode_pasien AS kode_pasien,
            p.nama_lengkap AS nama_pasien,
            p.kategori AS kategori_raw,
            CASE
                WHEN p.kategori = 'vip' THEN 'VIP'
                WHEN p.kategori = 'member' THEN 'Member'
                ELSE 'Reguler'
            END AS kategori_pasien,
            CONCAT(COUNT(k.id_kunjungan), ' Kali') AS total_kunjungan,
            CASE
                WHEN p.no_telepon IS NOT NULL AND p.no_telepon != '' THEN 'Aktif'
                ELSE 'Tidak Aktif'
            END AS status_akun
        FROM pasien p
        JOIN kunjungan k ON p.id_pasien = k.id_pasien
        GROUP BY p.id_pasien, p.kode_pasien, p.nama_lengkap, p.kategori, p.no_telepon
        ORDER BY p.kategori DESC, COUNT(k.id_kunjungan) DESC, p.kode_pasien ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $v_status_pasien = [];
}

// --- FUNGSI HELPER UNTUK RENDER TABEL DATABASE VIEW SECARA DINAMIS ---
function renderReportTable($view_data) {
    if (!empty($view_data)) {
        echo '<table class="w-full text-left border-collapse">';
        echo '  <thead class="sticky top-0 bg-surface-container-low dark:bg-[#1e293b] text-label-caps text-on-surface-variant dark:text-slate-400 border-b border-outline-variant/30 dark:border-transparent">';
        echo '    <tr>';
        // Ambil header kolom secara dinamis dari keys baris pertama
        foreach (array_keys($view_data[0]) as $col_name) {
            echo '    <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">' . htmlspecialchars(str_replace('_', ' ', $col_name)) . '</th>';
        }
        echo '    </tr>';
        echo '  </thead>';
        echo '  <tbody class="divide-y divide-outline-variant/20 dark:divide-y-0 text-body-sm">';
        foreach ($view_data as $row) {
            echo '  <tr class="text-on-surface-variant dark:text-slate-300 odd:bg-transparent even:bg-surface-container-low/10 dark:even:bg-slate-800/20 hover:bg-surface-container-low/40 dark:hover:bg-slate-800/40 transition-colors">';
            foreach ($row as $col_name => $val) {
                $formatted_val = htmlspecialchars($val ?? '-');
                if (is_numeric($val)) {
                    // 1. Cek jika kolom persen
                    if (strpos($col_name, 'persen') !== false) {
                        $formatted_val = number_format($val, 1, ',', '.') . '%';
                    }
                    // 2. Cek jika kolom terkait keuangan
                    elseif (
                        strpos($col_name, 'harga') !== false || 
                        strpos($col_name, 'pendapatan') !== false || 
                        strpos($col_name, 'belanja') !== false || 
                        strpos($col_name, 'biaya') !== false || 
                        strpos($col_name, 'rata_rata') !== false || 
                        strpos($col_name, 'tertinggi') !== false || 
                        strpos($col_name, 'total_layanan') !== false ||
                        strpos($col_name, 'total_obat') !== false ||
                        $col_name === 'grand_total' || 
                        $col_name === 'subtotal'
                    ) {
                        $formatted_val = 'Rp ' . number_format($val, 0, ',', '.');
                    }
                    // 3. Jika numerik biasa (seperti total kunjungan, umur, jumlah transaksi)
                    else {
                        $formatted_val = number_format($val, 0, ',', '.');
                    }
                }
                echo '    <td class="px-lg py-md">' . $formatted_val . '</td>';
            }
            echo '  </tr>';
        }
        echo '  </tbody>';
        echo '</table>';
    } else {
        echo '<div class="p-xl text-center text-on-surface-variant dark:text-slate-400 italic text-sm">';
        echo '  Tidak ada data laporan yang tersedia atau Database View belum diisi di MySQL Workbench.';
        echo '</div>';
    }
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
        <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="rekam-medis.php">
          <span class="material-symbols-outlined mr-3">medical_services</span>
          <span class="font-label-caps text-label-caps">Data Pasien &amp; Rekam Medis</span>
        </a>
        <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="inventori.php">
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
                <h3 class="text-2xl font-bold text-on-surface dark:text-slate-100 mt-1 whitespace-nowrap"><?= formatRupiah($pendapatan_bulan_ini) ?></h3>
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
                <tr class="border-b border-outline-variant/30 dark:border-transparent text-label-caps text-on-surface-variant dark:text-slate-400">
                  <th class="py-md">No</th>
                  <th class="py-md">Nama Layanan / Perawatan</th>
                  <th class="py-md">Jumlah Transaksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/20 dark:divide-y-0 text-body-sm">
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
                  <tr class="text-on-surface-variant dark:text-slate-300 odd:bg-transparent even:bg-surface-container-low/10 dark:even:bg-slate-800/10 transition-colors">
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

        <!-- Antrean Pasien Hari Ini (Menggunakan View v_dashboard_admin_antrian) -->
        <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-none rounded-2xl p-xl shadow-sm space-y-md">
          <div class="flex justify-between items-center">
            <div>
              <h2 class="font-title-sm text-title-sm font-bold text-on-surface dark:text-slate-100">Antrean Pasien Hari Ini</h2>
              <p class="text-body-sm text-on-surface-variant dark:text-slate-400">Menampilkan antrean pasien aktif untuk hari ini dari database view.</p>
            </div>
            <button class="border border-outline dark:border-slate-700 px-md py-sm rounded-lg font-title-sm text-body-sm dark:text-slate-300 hover:bg-surface-container-low dark:hover:bg-slate-800 transition-all">
              Lihat Semua
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-outline-variant/30 dark:border-transparent text-label-caps text-on-surface-variant dark:text-slate-400">
                  <th class="py-md">No. Antrean</th>
                  <th class="py-md">Kode Pasien</th>
                  <th class="py-md">Nama Pasien</th>
                  <th class="py-md">Dokter</th>
                  <th class="py-md">Keluhan Utama</th>
                  <th class="py-md">Waktu Daftar</th>
                  <th class="py-md">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/20 dark:divide-y-0 text-body-sm">
                <?php
                if (empty($antrian_pasien)) :
                ?>
                  <tr>
                    <td colspan="7" class="py-md text-center text-on-surface-variant dark:text-slate-400">
                      Belum ada antrean pasien untuk hari ini.
                    </td>
                  </tr>
                <?php else :
                    foreach ($antrian_pasien as $antrian) :
                        // Menentukan warna badge status secara dinamis
                        $badge_class = 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300'; // Default: yellow
                        if ($antrian['status'] === 'Selesai') {
                            $badge_class = 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
                        } elseif ($antrian['status'] === 'Sedang Diperiksa') {
                            $badge_class = 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
                        } elseif ($antrian['status'] === 'Batal') {
                            $badge_class = 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
                        }
                ?>
                  <tr class="text-on-surface-variant dark:text-slate-300 odd:bg-transparent even:bg-surface-container-low/10 dark:even:bg-slate-800/10 hover:bg-surface-container-low/20 dark:hover:bg-slate-800/20 transition-colors">
                    <!-- No. Antrean -->
                    <td class="py-md font-bold text-on-surface dark:text-slate-100"><?= htmlspecialchars($antrian['no_antrian']) ?></td>
                    <!-- Kode Pasien -->
                    <td class="py-md font-mono text-xs"><?= htmlspecialchars($antrian['kode_pasien']) ?></td>
                    <!-- Nama Pasien -->
                    <td class="py-md font-bold text-on-surface dark:text-slate-100"><?= htmlspecialchars($antrian['nama_lengkap']) ?></td>
                    <!-- Dokter -->
                    <td class="py-md"><?= htmlspecialchars($antrian['dokter']) ?></td>
                    <!-- Keluhan Utama -->
                    <td class="py-md max-w-xs truncate" title="<?= htmlspecialchars($antrian['keluhan_utama']) ?>"><?= htmlspecialchars($antrian['keluhan_utama']) ?></td>
                    <!-- Waktu Daftar -->
                    <td class="py-md"><?= date('H:i', strtotime($antrian['waktu_daftar'])) ?></td>
                    <!-- Badge Status -->
                    <td class="py-md">
                      <span class="inline-flex items-center gap-xs px-sm py-1 rounded-full <?= $badge_class ?> font-bold text-[12px]">
                        <?= htmlspecialchars($antrian['status']) ?>
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

        <!-- Laporan Manajemen & Eksekutif (Bab VII) Section -->
        <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-slate-800 rounded-2xl p-xl shadow-sm space-y-lg mt-xl">
          <div>
            <h2 class="font-title-sm text-title-sm font-bold text-on-surface dark:text-slate-100 flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-[24px]">analytics</span>
              Laporan Manajemen &amp; Eksekutif 
            </h2>
            <p class="text-body-sm text-on-surface-variant dark:text-slate-400">
              Kompilasi laporan berbasis Database View untuk kebutuhan pelaporan eksekutif manajemen dan analisis performa klinik.
            </p>
          </div>

          <!-- Tab Selector -->
          <div class="flex flex-wrap gap-2 border-b border-outline-variant/30 dark:border-slate-800 pb-3">
            <button class="tab-btn active px-4 py-2 rounded-lg font-bold text-xs bg-primary text-on-primary shadow-sm hover:brightness-110 transition-all" onclick="switchReportTab(event, 'tab-kunjungan')">
              Kunjungan Bulanan
            </button>
            <button class="tab-btn px-4 py-2 rounded-lg font-bold text-xs bg-surface-container-low dark:bg-slate-800 hover:bg-surface-container-high dark:hover:bg-slate-700 text-on-surface-variant dark:text-slate-300 transition-all" onclick="switchReportTab(event, 'tab-layanan')">
              Layanan Terlaris
            </button>
            <button class="tab-btn px-4 py-2 rounded-lg font-bold text-xs bg-surface-container-low dark:bg-slate-800 hover:bg-surface-container-high dark:hover:bg-slate-700 text-on-surface-variant dark:text-slate-300 transition-all" onclick="switchReportTab(event, 'tab-pasien')">
              Pasien Teraktif
            </button>
            <button class="tab-btn px-4 py-2 rounded-lg font-bold text-xs bg-surface-container-low dark:bg-slate-800 hover:bg-surface-container-high dark:hover:bg-slate-700 text-on-surface-variant dark:text-slate-300 transition-all" onclick="switchReportTab(event, 'tab-stok')">
              Stok Minimum
            </button>
            <button class="tab-btn px-4 py-2 rounded-lg font-bold text-xs bg-surface-container-low dark:bg-slate-800 hover:bg-surface-container-high dark:hover:bg-slate-700 text-on-surface-variant dark:text-slate-300 transition-all" onclick="switchReportTab(event, 'tab-status-pasien')">
              Kategori Pasien
            </button>
          </div>

          <!-- Tab Contents -->
          <div class="report-content-wrapper mt-lg">
            
            <!-- Tab 1: Kunjungan Bulanan -->
            <div id="tab-kunjungan" class="report-tab-panel space-y-md">
              <h3 class="font-title-sm text-sm font-bold text-primary dark:text-primary-fixed-dim">Laporan Kunjungan Bulanan (v_laporan_kunjungan_bulanan)</h3>
              <div class="overflow-x-auto border border-outline-variant/30 dark:border-slate-800 rounded-xl">
                <?php renderReportTable($v_kunjungan_bulanan); ?>
              </div>
            </div>

            <!-- Tab 2: Layanan Terlaris -->
            <div id="tab-layanan" class="report-tab-panel hidden space-y-md">
              <h3 class="font-title-sm text-sm font-bold text-primary dark:text-primary-fixed-dim">Laporan Layanan &amp; Perawatan Terlaris (v_laporan_layanan_terlaris)</h3>
              <div class="overflow-x-auto border border-outline-variant/30 dark:border-slate-800 rounded-xl">
                <?php renderReportTable($v_layanan_terlaris); ?>
              </div>
            </div>

            <!-- Tab 3: Pasien Teraktif -->
            <div id="tab-pasien" class="report-tab-panel hidden space-y-md">
              <h3 class="font-title-sm text-sm font-bold text-primary dark:text-primary-fixed-dim">Laporan Pelanggan/Pasien Teraktif (v_laporan_pasien_teraktif)</h3>
              <div class="overflow-x-auto border border-outline-variant/30 dark:border-slate-800 rounded-xl">
                <?php renderReportTable($v_pasien_teraktif); ?>
              </div>
            </div>

            <!-- Tab 4: Stok Minimum -->
            <div id="tab-stok" class="report-tab-panel hidden space-y-md">
              <h3 class="font-title-sm text-sm font-bold text-primary dark:text-primary-fixed-dim">Laporan Peringatan Stok Minimum Obat (v_laporan_stok_minimum)</h3>
              <div class="overflow-x-auto border border-outline-variant/30 dark:border-slate-800 rounded-xl">
                <?php renderReportTable($v_stok_minimum); ?>
              </div>
            </div>

            <!-- Tab 5: Kategori & Status Pasien -->
            <div id="tab-status-pasien" class="report-tab-panel hidden space-y-md">
              <div class="flex items-start justify-between flex-wrap gap-2">
                <div>
                  <h3 class="font-title-sm text-sm font-bold text-primary dark:text-primary-fixed-dim">Laporan Kategori &amp; Status Pasien (v_laporan_status_pasien)</h3>
                  <p class="text-xs text-on-surface-variant dark:text-slate-400 mt-0.5">Admin dapat mengubah kategori pasien menjadi <span class="text-amber-600 dark:text-amber-400 font-bold">★ VIP</span>, <span class="text-indigo-600 dark:text-indigo-400 font-bold">👤 Member</span>, atau <span class="text-blue-600 dark:text-blue-400 font-bold">Reguler</span> secara langsung.</p>
                </div>
                <div class="text-xs text-on-surface-variant dark:text-slate-500 italic bg-surface-container-low dark:bg-slate-800 px-3 py-1.5 rounded-lg">
                  Perubahan langsung tersimpan ke database
                </div>
              </div>
              <div class="overflow-x-auto border border-outline-variant/30 dark:border-slate-800 rounded-xl">
                <?php if (!empty($v_status_pasien)) : ?>
                <table class="w-full text-left border-collapse">
                  <thead class="sticky top-0 bg-surface-container-low dark:bg-[#1e293b] text-label-caps text-on-surface-variant dark:text-slate-400 border-b border-outline-variant/30 dark:border-transparent">
                    <tr>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">ID Pasien</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Nama Pasien</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Kategori</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Total Kunjungan</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Status Akun</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Ubah Kategori</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-outline-variant/20 dark:divide-y-0 text-body-sm">
                    <?php foreach ($v_status_pasien as $row) :
                        $kat = $row['kategori_raw'];
                        $is_aktif = $row['status_akun'] === 'Aktif';

                        // Tentukan style badge berdasarkan 3 kategori
                        if ($kat === 'vip') {
                            $badge_style = 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300';
                            $badge_icon = '★ ';
                        } elseif ($kat === 'member') {
                            $badge_style = 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300';
                            $badge_icon = '👤 ';
                        } else {
                            $badge_style = 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300';
                            $badge_icon = '';
                        }
                    ?>
                    <tr id="row-pasien-<?= $row['id_pasien'] ?>" class="text-on-surface-variant dark:text-slate-300 odd:bg-transparent even:bg-surface-container-low/10 dark:even:bg-slate-800/20 hover:bg-surface-container-low/40 dark:hover:bg-slate-800/40 transition-colors">
                      <td class="px-lg py-md font-mono text-xs font-bold text-on-surface dark:text-slate-100"><?= htmlspecialchars($row['kode_pasien']) ?></td>
                      <td class="px-lg py-md font-medium text-on-surface dark:text-slate-100"><?= htmlspecialchars($row['nama_pasien']) ?></td>
                      <td class="px-lg py-md" id="badge-<?= $row['id_pasien'] ?>">
                        <span class="inline-flex items-center gap-1 px-sm py-0.5 rounded-full font-bold text-[11px] <?= $badge_style ?>">
                          <?= $badge_icon ?><?= htmlspecialchars($row['kategori_pasien']) ?>
                        </span>
                      </td>
                      <td class="px-lg py-md">
                        <span class="inline-flex items-center gap-xs px-sm py-1 rounded-full bg-primary/10 dark:bg-primary/20 text-primary dark:text-primary-fixed-dim font-bold text-[12px]">
                          <?= htmlspecialchars($row['total_kunjungan']) ?>
                        </span>
                      </td>
                      <td class="px-lg py-md">
                        <span class="inline-flex items-center gap-1 px-sm py-0.5 rounded-full font-bold text-[11px] <?= $is_aktif ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300' ?>">
                          <span class="w-1.5 h-1.5 rounded-full <?= $is_aktif ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                          <?= htmlspecialchars($row['status_akun']) ?>
                        </span>
                      </td>
                      <td class="px-lg py-md">
                        <select
                          id="select-kategori-<?= $row['id_pasien'] ?>"
                          onchange="updateKategori(<?= $row['id_pasien'] ?>, this.value, this)"
                          class="bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg px-3 py-1.5 text-xs font-bold transition-all outline-none text-on-surface dark:text-slate-100 cursor-pointer focus:ring-1 focus:ring-primary">
                          <option value="reguler" <?= $kat === 'reguler' ? 'selected' : '' ?>>Reguler</option>
                          <option value="member" <?= $kat === 'member' ? 'selected' : '' ?>>Member</option>
                          <option value="vip" <?= $kat === 'vip' ? 'selected' : '' ?>>VIP</option>
                        </select>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php else : ?>
                <div class="p-xl text-center text-on-surface-variant dark:text-slate-400 italic text-sm">
                  Belum ada data pasien terdaftar.
                </div>
                <?php endif; ?>
              </div>
            </div>

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

      // Tab switching logic for executive reports
      function switchReportTab(event, tabId) {
        // Hide all report panels
        const panels = document.querySelectorAll('.report-tab-panel');
        panels.forEach(panel => panel.classList.add('hidden'));

        // Show selected panel
        const activePanel = document.getElementById(tabId);
        if (activePanel) activePanel.classList.remove('hidden');

        // Reset all buttons style classes
        const buttons = document.querySelectorAll('.tab-btn');
        buttons.forEach(btn => {
          btn.classList.remove('bg-primary', 'text-on-primary', 'shadow-sm', 'hover:brightness-110');
          btn.classList.add('bg-surface-container-low', 'dark:bg-slate-800', 'text-on-surface-variant', 'dark:text-slate-300', 'hover:bg-surface-container-high', 'dark:hover:bg-slate-700');
        });

        // Add active style classes to clicked button
        event.currentTarget.classList.remove('bg-surface-container-low', 'dark:bg-slate-800', 'text-on-surface-variant', 'dark:text-slate-300', 'hover:bg-surface-container-high', 'dark:hover:bg-slate-700');
        event.currentTarget.classList.add('bg-primary', 'text-on-primary', 'shadow-sm', 'hover:brightness-110');
      }

      // ===== UPDATE KATEGORI PASIEN (AJAX) =====
      function updateKategori(idPasien, kategoriTarget, selectEl) {
        const labels = { 'vip': 'VIP', 'member': 'Member', 'reguler': 'Reguler' };
        const confirmMsg = `Ubah kategori pasien ini menjadi ${labels[kategoriTarget]}?`;

        if (!confirm(confirmMsg)) {
          window.location.reload();
          return;
        }

        selectEl.disabled = true;

        const formData = new FormData();
        formData.append('id_pasien', idPasien);
        formData.append('kategori', kategoriTarget);

        fetch('api_toggle_vip.php', { method: 'POST', body: formData })
          .then(r => r.json())
          .then(data => {
            selectEl.disabled = false;
            if (data.success) {
              // Update badge secara dinamis
              const badgeCell = document.getElementById(`badge-${idPasien}`);
              let badgeHTML = '';
              if (kategoriTarget === 'vip') {
                badgeHTML = `<span class="inline-flex items-center gap-1 px-sm py-0.5 rounded-full font-bold text-[11px] bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">★ VIP</span>`;
              } else if (kategoriTarget === 'member') {
                badgeHTML = `<span class="inline-flex items-center gap-1 px-sm py-0.5 rounded-full font-bold text-[11px] bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300">👤 Member</span>`;
              } else {
                badgeHTML = `<span class="inline-flex items-center gap-1 px-sm py-0.5 rounded-full font-bold text-[11px] bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">Reguler</span>`;
              }
              badgeCell.innerHTML = badgeHTML;
            } else {
              alert('Gagal mengubah status: ' + data.message);
              window.location.reload();
            }
          })
          .catch(() => {
            alert('Terjadi error jaringan, silahkan coba lagi.');
            selectEl.disabled = false;
            window.location.reload();
          });
      }
    </script>
  </body>
</html>
