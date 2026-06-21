<?php
/**
 * ============================================================
 * FILE: dashboard-admin/transaksi.php
 * DESKRIPSI: Modul Transaksi & Pembayaran (Kasir) untuk Admin.
 *            Digunakan untuk memproses checkout pasien dan
 *            melihat riwayat transaksi keuangan.
 * ============================================================
 */

require_once __DIR__ . '/../koneksi.php';

// --- TANGGAL FILTER ---
$tanggal_pilihan = $_GET['tanggal'] ?? date('Y-m-d');

// --- HELPER: FORMAT ANGKA RUPIAH ---
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

// --- QUERY 1: ANTREAN PEMBAYARAN (PENDING CHECKOUT) ---
try {
    $stmt_pending = $pdo->query("
        SELECT 
            k.id_kunjungan,
            k.kode_kunjungan,
            k.no_antrian,
            k.tanggal_kunjungan,
            p.id_pasien,
            p.kode_pasien,
            p.nama_lengkap AS nama_pasien,
            p.kategori AS kategori_pasien,
            d.nama_lengkap AS nama_dokter,
            -- Detail tindakan ringkas
            (SELECT GROUP_CONCAT(CONCAT(l.nama_layanan, ' (', dl.jumlah, 'x)') SEPARATOR ', ') 
             FROM detail_layanan dl 
             JOIN layanan l ON dl.id_layanan = l.id_layanan 
             WHERE dl.id_kunjungan = k.id_kunjungan) AS rincian_layanan,
            -- Detail obat ringkas
            (SELECT GROUP_CONCAT(CONCAT(o.nama_obat, ' (', ro.jumlah, 'x)') SEPARATOR ', ') 
             FROM resep_obat ro 
             JOIN obat o ON ro.id_obat = o.id_obat 
             WHERE ro.id_kunjungan = k.id_kunjungan) AS rincian_obat,
            -- Total tagihan
            (SELECT COALESCE(SUM(subtotal), 0) FROM detail_layanan WHERE id_kunjungan = k.id_kunjungan) AS total_layanan,
            (SELECT COALESCE(SUM(subtotal), 0) FROM resep_obat WHERE id_kunjungan = k.id_kunjungan) AS total_obat
        FROM kunjungan k
        JOIN pasien p ON k.id_pasien = p.id_pasien
        JOIN dokter d ON k.id_dokter = d.id_dokter
        LEFT JOIN pembayaran pb ON k.id_kunjungan = pb.id_kunjungan
        WHERE k.id_status = 3 -- Selesai diperiksa dokter
          AND (pb.status_bayar IS NULL OR pb.status_bayar = 'belum_lunas')
        ORDER BY k.tanggal_kunjungan DESC, k.id_kunjungan DESC
    ");
    $pending_tx = $stmt_pending->fetchAll();
} catch (PDOException $e) {
    $pending_tx = [];
}

// --- QUERY 2: DETAIL BILLING UNTUK JS MODAL ---
$billing_details = [];
foreach ($pending_tx as $tx) {
    $id_k = $tx['id_kunjungan'];
    try {
        // Ambil detail layanan
        $stmt_det_lay = $pdo->prepare("
            SELECT l.nama_layanan, dl.jumlah, dl.harga_satuan, dl.subtotal 
            FROM detail_layanan dl
            JOIN layanan l ON dl.id_layanan = l.id_layanan
            WHERE dl.id_kunjungan = ?
        ");
        $stmt_det_lay->execute([$id_k]);
        $layanan_items = $stmt_det_lay->fetchAll();
        
        // Ambil detail obat
        $stmt_det_obt = $pdo->prepare("
            SELECT o.nama_obat, ro.jumlah, ro.harga_satuan, ro.subtotal 
            FROM resep_obat ro
            JOIN obat o ON ro.id_obat = o.id_obat
            WHERE ro.id_kunjungan = ?
        ");
        $stmt_det_obt->execute([$id_k]);
        $obat_items = $stmt_det_obt->fetchAll();
    } catch (PDOException $e) {
        $layanan_items = [];
        $obat_items = [];
    }
    
    $total_lay = floatval($tx['total_layanan']);
    $total_ob = floatval($tx['total_obat']);
    
    // Jika tidak ada treatment/resep, anggap biaya konsultasi dokter standar LYN-001 (Rp 150.000)
    if ($total_lay + $total_ob <= 0) {
        $total_lay = 150000.00;
        $layanan_items[] = [
            'nama_layanan' => 'Biaya Konsultasi Dokter (Umum)',
            'jumlah' => 1,
            'harga_satuan' => 150000.00,
            'subtotal' => 150000.00
        ];
    }
    
    $billing_details[$id_k] = [
        'id_kunjungan' => $id_k,
        'kode_kunjungan' => $tx['kode_kunjungan'],
        'nama_pasien' => $tx['nama_pasien'],
        'kode_pasien' => $tx['kode_pasien'],
        'kategori_pasien' => $tx['kategori_pasien'],
        'nama_dokter' => $tx['nama_dokter'],
        'layanan' => $layanan_items,
        'obat' => $obat_items,
        'total_layanan' => $total_lay,
        'total_obat' => $total_ob,
        'grand_total' => $total_lay + $total_ob
    ];
}

// --- QUERY 3: RIWAYAT TRANSAKSI (LUNAS) ---
try {
    $stmt_history = $pdo->query("
        SELECT 
            pb.id_pembayaran,
            pb.kode_pembayaran,
            pb.grand_total,
            pb.total_layanan,
            pb.total_obat,
            pb.tanggal_bayar,
            k.kode_kunjungan,
            p.nama_lengkap AS nama_pasien,
            p.kode_pasien,
            mp.nama AS metode_pembayaran
        FROM pembayaran pb
        JOIN kunjungan k ON pb.id_kunjungan = k.id_kunjungan
        JOIN pasien p ON k.id_pasien = p.id_pasien
        JOIN ref_metode_pembayaran mp ON pb.id_metode = mp.id
        WHERE pb.status_bayar = 'lunas'
        ORDER BY pb.tanggal_bayar DESC
        LIMIT 50
    ");
    $history_tx = $stmt_history->fetchAll();
} catch (PDOException $e) {
    $history_tx = [];
}

// --- QUERY 4: STATISTIK RINGKASAN TRANSAKSI ---
try {
    $antrean_count = count($pending_tx);
    
    // Transaksi Hari Ini
    $stmt_tx_today = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status_bayar = 'lunas' AND DATE(tanggal_bayar) = CURDATE()");
    $tx_today_count = $stmt_tx_today->fetchColumn() ?: 0;
    
    // Omzet Hari Ini
    $stmt_omzet_today = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM pembayaran WHERE status_bayar = 'lunas' AND DATE(tanggal_bayar) = CURDATE()");
    $omzet_today = $stmt_omzet_today->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    $antrean_count = 0;
    $tx_today_count = 0;
    $omzet_today = 0;
}

// --- QUERY 5: METODE PEMBAYARAN ---
try {
    $metode_pembayaran = $pdo->query("SELECT id, nama FROM ref_metode_pembayaran ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $metode_pembayaran = [];
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Transaksi &amp; Pembayaran | GlowSkin Admin</title>
    
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
        <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="index.php">
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
        <!-- Active Tab: Transaksi & Pembayaran -->
        <a class="group relative flex items-center px-lg py-3 text-primary dark:text-primary-fixed-dim font-bold border-l-4 border-primary dark:border-primary-fixed-dim bg-primary/5 transition-all duration-200" href="transaksi.php">
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
          <!-- Spacer -->
        </div>

        <div class="flex items-center gap-6 ml-lg">
          <!-- Theme Toggle -->
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
          <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface">Transaksi &amp; Pembayaran</h2>
          <p class="text-on-surface-variant font-body-md mt-1">Kelola billing kasir pasien dan verifikasi transaksi pembayaran secara terpusat.</p>
        </div>

        <!-- Bento Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
          <!-- Stat 1: Antrean Kasir -->
          <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant/30 dark:border-slate-800 flex items-start gap-4 h-32 relative overflow-hidden shadow-sm">
            <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined text-[20px]">hourglass_empty</span>
            </div>
            <div class="flex-grow flex flex-col justify-between h-full">
              <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Antrean Kasir</p>
                <h3 class="text-2xl font-bold text-on-surface dark:text-slate-100 mt-1"><?= $antrean_count ?> Pasien</h3>
              </div>
              <p class="text-xs text-on-surface-variant dark:text-slate-400 flex items-center gap-xs">
                Menunggu pembayaran selesai
              </p>
            </div>
          </div>

          <!-- Stat 2: Transaksi Sukses Hari Ini -->
          <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant/30 dark:border-slate-800 flex items-start gap-4 h-32 relative overflow-hidden shadow-sm">
            <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg text-green-600 dark:text-green-400 flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined text-[20px]">task_alt</span>
            </div>
            <div class="flex-grow flex flex-col justify-between h-full">
              <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Transaksi Sukses (Hari Ini)</p>
                <h3 class="text-2xl font-bold text-on-surface dark:text-slate-100 mt-1"><?= $tx_today_count ?> Pembayaran</h3>
              </div>
              <p class="text-xs text-green-600 dark:text-green-400 flex items-center gap-xs">
                <span class="material-symbols-outlined text-[14px]">trending_up</span>
                Berhasil dibukukan hari ini
              </p>
            </div>
          </div>

          <!-- Stat 3: Omzet Kasir Hari Ini -->
          <div class="bg-surface-container-lowest dark:bg-[#1e293b] p-lg rounded-xl border border-outline-variant/30 dark:border-slate-800 flex items-start gap-4 h-32 relative overflow-hidden shadow-sm">
            <div class="p-2 bg-primary/10 rounded-lg text-primary dark:text-primary-fixed-dim flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined text-[20px]">monetization_on</span>
            </div>
            <div class="flex-grow flex flex-col justify-between h-full">
              <div>
                <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Omzet Kasir (Hari Ini)</p>
                <h3 class="text-2xl font-bold text-on-surface dark:text-slate-100 mt-1"><?= formatRupiah($omzet_today) ?></h3>
              </div>
              <p class="text-xs text-green-600 dark:text-green-400 flex items-center gap-xs">
                <span class="material-symbols-outlined text-[14px]">payments</span>
                Dana masuk hari ini
              </p>
            </div>
          </div>
        </div>

        <!-- Main Section Container (Dual-Tab) -->
        <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-slate-800 rounded-2xl p-xl shadow-sm space-y-lg">
          
          <!-- Tab Buttons -->
          <div class="flex flex-wrap gap-2 border-b border-outline-variant/30 dark:border-slate-800 pb-3">
            <button id="btn-tab-antrean" class="px-4 py-2 rounded-lg font-bold text-xs bg-primary text-on-primary shadow-sm hover:brightness-110 transition-all flex items-center gap-1" onclick="switchTab('antrean')">
              <span class="material-symbols-outlined text-[16px]">receipt_long</span>
              Antrean Kasir (Pending)
            </button>
            <button id="btn-tab-riwayat" class="px-4 py-2 rounded-lg font-bold text-xs bg-surface-container-low dark:bg-slate-800 hover:bg-surface-container-high dark:hover:bg-slate-700 text-on-surface-variant dark:text-slate-300 transition-all flex items-center gap-1" onclick="switchTab('riwayat')">
              <span class="material-symbols-outlined text-[16px]">history</span>
              Riwayat Transaksi (Lunas)
            </button>
          </div>

          <!-- Tab Panels -->
          <div>
            
            <!-- PANEL 1: ANTREAN KASIR -->
            <div id="panel-antrean" class="space-y-md">
              <div class="flex justify-between items-center">
                <div>
                  <h3 class="font-title-sm text-sm font-bold text-primary dark:text-primary-fixed-dim">Menunggu Proses Pembayaran</h3>
                  <p class="text-xs text-on-surface-variant dark:text-slate-400 mt-0.5">Daftar pasien yang telah selesai diperiksa dokter dan rincian tindakannya siap ditagihkan.</p>
                </div>
              </div>

              <div class="overflow-x-auto border border-outline-variant/30 dark:border-slate-800 rounded-xl">
                <table class="w-full text-left border-collapse">
                  <thead class="sticky top-0 bg-surface-container-low dark:bg-[#1e293b] text-label-caps text-on-surface-variant dark:text-slate-400 border-b border-outline-variant/30 dark:border-transparent">
                    <tr>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">No. Antrean</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Kode Kunjungan</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Nama Pasien</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Kategori</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Dokter</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Total Rincian</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Aksi</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-outline-variant/20 dark:divide-y-0 text-body-sm">
                    <?php if (empty($pending_tx)) : ?>
                      <tr>
                        <td colspan="7" class="p-xl text-center text-on-surface-variant dark:text-slate-400 italic">
                          Tidak ada antrean kasir saat ini. Semua pembayaran telah lunas!
                        </td>
                      </tr>
                    <?php else : ?>
                      <?php foreach ($pending_tx as $tx) : 
                        $is_vip = $tx['kategori_pasien'] === 'vip';
                        $grand_total = floatval($tx['total_layanan'] + $tx['total_obat']);
                        if ($grand_total <= 0) $grand_total = 150000.00; // Konsultasi default
                      ?>
                        <tr class="text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-low/40 dark:hover:bg-slate-800/40 transition-colors">
                          <td class="px-lg py-md font-bold text-on-surface dark:text-slate-100 text-center w-20"><?= htmlspecialchars($tx['no_antrian']) ?></td>
                          <td class="px-lg py-md font-mono text-xs font-bold text-on-surface dark:text-slate-100"><?= htmlspecialchars($tx['kode_kunjungan']) ?></td>
                          <td class="px-lg py-md font-medium text-on-surface dark:text-slate-100">
                            <div><?= htmlspecialchars($tx['nama_pasien']) ?></div>
                            <div class="text-[10px] text-on-surface-variant dark:text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($tx['kode_pasien']) ?></div>
                          </td>
                          <td class="px-lg py-md">
                            <span class="inline-flex items-center gap-1 px-sm py-0.5 rounded-full font-bold text-[11px] <?= $is_vip ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' ?>">
                              <?= $is_vip ? '★ VIP' : 'Reguler' ?>
                            </span>
                          </td>
                          <td class="px-lg py-md"><?= htmlspecialchars($tx['nama_dokter']) ?></td>
                          <td class="px-lg py-md">
                            <div class="font-bold text-on-surface dark:text-slate-100"><?= formatRupiah($grand_total) ?></div>
                            <div class="text-[10px] text-on-surface-variant dark:text-slate-500">
                              Layanan: <?= formatRupiah($tx['total_layanan'] ?: 150000.00) ?> | Obat: <?= formatRupiah($tx['total_obat'] ?: 0) ?>
                            </div>
                          </td>
                          <td class="px-lg py-md">
                            <button onclick="openCheckoutModal(<?= $tx['id_kunjungan'] ?>)" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-on-primary font-bold text-[12px] hover:brightness-110 shadow-sm transition-all">
                              <span class="material-symbols-outlined text-[16px]">payments</span>
                              Proses Bayar
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- PANEL 2: RIWAYAT TRANSAKSI (LUNAS) -->
            <div id="panel-riwayat" class="hidden space-y-md">
              <div class="flex justify-between items-center">
                <div>
                  <h3 class="font-title-sm text-sm font-bold text-primary dark:text-primary-fixed-dim">Riwayat Pembayaran Sukses</h3>
                  <p class="text-xs text-on-surface-variant dark:text-slate-400 mt-0.5">Menampilkan catatan transaksi pembayaran yang sudah berstatus LUNAS.</p>
                </div>
              </div>

              <div class="overflow-x-auto border border-outline-variant/30 dark:border-slate-800 rounded-xl">
                <table class="w-full text-left border-collapse">
                  <thead class="sticky top-0 bg-surface-container-low dark:bg-[#1e293b] text-label-caps text-on-surface-variant dark:text-slate-400 border-b border-outline-variant/30 dark:border-transparent">
                    <tr>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Kode Pembayaran</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Kode Kunjungan</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Nama Pasien</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Total Pembayaran</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Metode</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Waktu Bayar</th>
                      <th class="px-lg py-md uppercase tracking-wider text-[11px] font-bold">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-outline-variant/20 dark:divide-y-0 text-body-sm">
                    <?php if (empty($history_tx)) : ?>
                      <tr>
                        <td colspan="7" class="p-xl text-center text-on-surface-variant dark:text-slate-400 italic">
                          Belum ada riwayat transaksi lunas yang tercatat di database.
                        </td>
                      </tr>
                    <?php else : ?>
                      <?php foreach ($history_tx as $hx) : ?>
                        <tr class="text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-low/40 dark:hover:bg-slate-800/40 transition-colors">
                          <td class="px-lg py-md font-mono text-xs font-bold text-primary dark:text-primary-fixed-dim"><?= htmlspecialchars($hx['kode_pembayaran']) ?></td>
                          <td class="px-lg py-md font-mono text-xs text-on-surface-variant dark:text-slate-400"><?= htmlspecialchars($hx['kode_kunjungan']) ?></td>
                          <td class="px-lg py-md font-medium text-on-surface dark:text-slate-100">
                            <div><?= htmlspecialchars($hx['nama_pasien']) ?></div>
                            <div class="text-[10px] text-on-surface-variant dark:text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($hx['kode_pasien']) ?></div>
                          </td>
                          <td class="px-lg py-md font-bold text-on-surface dark:text-slate-100"><?= formatRupiah($hx['grand_total']) ?></td>
                          <td class="px-lg py-md">
                            <span class="inline-flex items-center gap-xs px-2 py-0.5 rounded-lg border border-outline/30 text-xs font-semibold">
                              <?= htmlspecialchars($hx['metode_pembayaran']) ?>
                            </span>
                          </td>
                          <td class="px-lg py-md text-xs"><?= date('d M Y H:i', strtotime($hx['tanggal_bayar'])) ?></td>
                          <td class="px-lg py-md">
                            <span class="inline-flex items-center gap-1 px-sm py-0.5 rounded-full font-bold text-[11px] bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                              ✓ Lunas
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>
      </main>
    </div>

    <!-- MODERN BILLING CHECKOUT MODAL -->
    <div id="checkout-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-md">
      <!-- Modal Box -->
      <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-slate-800 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl scale-95 opacity-0 transition-all duration-300 transform" id="modal-box">
        
        <!-- Header -->
        <div class="bg-primary/5 dark:bg-primary/10 px-xl py-lg border-b border-outline-variant/30 dark:border-slate-800 flex justify-between items-center">
          <div>
            <h3 class="font-headline-md text-title-sm font-bold text-primary dark:text-primary-fixed-dim">Billing Pasien &amp; Pembayaran</h3>
            <p class="text-xs text-on-surface-variant dark:text-slate-400 mt-0.5" id="modal-patient-info">Nama Pasien: - | NIK: -</p>
          </div>
          <button onclick="closeCheckoutModal()" class="w-8 h-8 rounded-full hover:bg-outline-variant/20 dark:hover:bg-slate-800 flex items-center justify-center text-on-surface-variant dark:text-slate-300 transition-colors">
            <span class="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <!-- Body Content -->
        <div class="p-xl space-y-lg max-h-[60vh] overflow-y-auto">
          
          <!-- Rincian Biaya Section -->
          <div class="space-y-sm">
            <h4 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/80 dark:text-slate-400">Rincian Layanan / Treatment</h4>
            <div class="border border-outline-variant/20 dark:border-slate-800 rounded-xl overflow-hidden">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-surface-container-low dark:bg-slate-800/40 text-on-surface-variant dark:text-slate-400 border-b border-outline-variant/20 dark:border-slate-800 font-bold">
                    <th class="px-md py-2.5">Deskripsi Perawatan</th>
                    <th class="px-md py-2.5 text-center w-16">Jumlah</th>
                    <th class="px-md py-2.5 text-right w-24">Harga Satuan</th>
                    <th class="px-md py-2.5 text-right w-28">Subtotal</th>
                  </tr>
                </thead>
                <tbody id="modal-layanan-body" class="divide-y divide-outline-variant/20 dark:divide-slate-800/50">
                  <!-- Dynamic Rows -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Rincian Resep Obat Section -->
          <div class="space-y-sm" id="modal-obat-section">
            <h4 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/80 dark:text-slate-400">Rincian Resep Obat / Skincare</h4>
            <div class="border border-outline-variant/20 dark:border-slate-800 rounded-xl overflow-hidden">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-surface-container-low dark:bg-slate-800/40 text-on-surface-variant dark:text-slate-400 border-b border-outline-variant/20 dark:border-slate-800 font-bold">
                    <th class="px-md py-2.5">Nama Skincare / Obat</th>
                    <th class="px-md py-2.5 text-center w-16">Jumlah</th>
                    <th class="px-md py-2.5 text-right w-24">Harga Satuan</th>
                    <th class="px-md py-2.5 text-right w-28">Subtotal</th>
                  </tr>
                </thead>
                <tbody id="modal-obat-body" class="divide-y divide-outline-variant/20 dark:divide-slate-800/50">
                  <!-- Dynamic Rows -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Grand Total Billing -->
          <div class="bg-primary/5 dark:bg-slate-800/50 p-lg rounded-2xl border border-primary/10 dark:border-slate-800 flex justify-between items-center">
            <div>
              <span class="text-xs font-bold text-on-surface-variant dark:text-slate-400 block uppercase">Total Bersih Tagihan</span>
              <span id="modal-discount-note" class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold block mt-0.5"></span>
            </div>
            <div class="text-right">
              <span id="modal-grand-total" class="text-2xl font-black text-primary dark:text-primary-fixed-dim">Rp 0</span>
            </div>
          </div>

          <!-- Form Input Pembayaran -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-lg border-t border-outline-variant/30 dark:border-slate-800 pt-lg">
            <div>
              <label for="select-metode" class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant/80 dark:text-slate-400 mb-2">Metode Pembayaran</p>
              <select id="select-metode" class="w-full rounded-lg border-outline-variant bg-transparent text-body-sm py-2 px-3 focus:ring-primary focus:border-primary dark:bg-slate-800 dark:border-slate-700">
                <?php foreach ($metode_pembayaran as $mp) : ?>
                  <option value="<?= $mp['id'] ?>"><?= htmlspecialchars($mp['nama']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant/80 dark:text-slate-400 mb-2">Diskon / Membership</p>
              <div class="w-full bg-surface-container-low dark:bg-slate-800/50 border border-outline-variant/30 dark:border-slate-700 rounded-lg py-2.5 px-3 text-body-sm font-semibold flex items-center justify-between">
                <span id="modal-membership-badge" class="text-xs font-bold">REGULER</span>
                <span class="text-[11px] text-on-surface-variant dark:text-slate-400">Potongan: Rp 0</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Footer -->
        <div class="px-xl py-lg bg-surface-container-low dark:bg-slate-800/20 border-t border-outline-variant/30 dark:border-slate-800 flex justify-end gap-md">
          <button onclick="closeCheckoutModal()" class="px-md py-2 border border-outline text-on-surface-variant dark:text-slate-300 font-bold rounded-lg text-xs hover:bg-surface-container-high dark:hover:bg-slate-800 transition-all">
            Batal
          </button>
          <button id="btn-submit-payment" onclick="submitPayment()" class="px-lg py-2 bg-primary text-on-primary font-bold rounded-lg text-xs hover:brightness-110 shadow-sm transition-all flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">check_circle</span>
            Konfirmasi Lunas
          </button>
        </div>

      </div>
    </div>

    <!-- INJECT JS BILLING DATA -->
    <script>
      const billingDetails = <?= json_encode($billing_details) ?>;
      let activeKunjunganId = null;

      // Initialize theme toggling
      setupThemeToggle('theme-toggle', 'dark-icon', 'light-icon');

      // Format currency JS
      function formatRupiahJS(number) {
        return 'Rp ' + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(number);
      }

      // Tab switching
      function switchTab(tab) {
        const pAntrean = document.getElementById('panel-antrean');
        const pRiwayat = document.getElementById('panel-riwayat');
        const bAntrean = document.getElementById('btn-tab-antrean');
        const bRiwayat = document.getElementById('btn-tab-riwayat');

        if (tab === 'antrean') {
          pAntrean.classList.remove('hidden');
          pRiwayat.classList.add('hidden');

          bAntrean.className = 'px-4 py-2 rounded-lg font-bold text-xs bg-primary text-on-primary shadow-sm hover:brightness-110 transition-all flex items-center gap-1';
          bRiwayat.className = 'px-4 py-2 rounded-lg font-bold text-xs bg-surface-container-low dark:bg-slate-800 hover:bg-surface-container-high dark:hover:bg-slate-700 text-on-surface-variant dark:text-slate-300 transition-all flex items-center gap-1';
        } else {
          pAntrean.classList.add('hidden');
          pRiwayat.classList.remove('hidden');

          bRiwayat.className = 'px-4 py-2 rounded-lg font-bold text-xs bg-primary text-on-primary shadow-sm hover:brightness-110 transition-all flex items-center gap-1';
          bAntrean.className = 'px-4 py-2 rounded-lg font-bold text-xs bg-surface-container-low dark:bg-slate-800 hover:bg-surface-container-high dark:hover:bg-slate-700 text-on-surface-variant dark:text-slate-300 transition-all flex items-center gap-1';
        }
      }

      // Modal management
      function openCheckoutModal(idKunjungan) {
        const details = billingDetails[idKunjungan];
        if (!details) return;

        activeKunjunganId = idKunjungan;

        // Set patient info
        document.getElementById('modal-patient-info').textContent = `Pasien: ${details.nama_pasien} (${details.kode_pasien}) | Dokter: ${details.nama_dokter}`;

        // Populate Layanan Table
        const layBody = document.getElementById('modal-layanan-body');
        layBody.innerHTML = '';
        details.layanan.forEach(item => {
          layBody.innerHTML += `
            <tr class="hover:bg-surface-container-low/50 dark:hover:bg-slate-800/30">
              <td class="px-md py-2">${item.nama_layanan}</td>
              <td class="px-md py-2 text-center">${item.jumlah}</td>
              <td class="px-md py-2 text-right">${formatRupiahJS(item.harga_satuan)}</td>
              <td class="px-md py-2 text-right font-bold text-on-surface dark:text-slate-100">${formatRupiahJS(item.subtotal)}</td>
            </tr>
          `;
        });

        // Populate Obat Table
        const obtBody = document.getElementById('modal-obat-body');
        const obtSection = document.getElementById('modal-obat-section');
        obtBody.innerHTML = '';

        if (details.obat.length === 0) {
          obtSection.classList.add('hidden');
        } else {
          obtSection.classList.remove('hidden');
          details.obat.forEach(item => {
            obtBody.innerHTML += `
              <tr class="hover:bg-surface-container-low/50 dark:hover:bg-slate-800/30">
                <td class="px-md py-2">${item.nama_obat}</td>
                <td class="px-md py-2 text-center">${item.jumlah}</td>
                <td class="px-md py-2 text-right">${formatRupiahJS(item.harga_satuan)}</td>
                <td class="px-md py-2 text-right font-bold text-on-surface dark:text-slate-100">${formatRupiahJS(item.subtotal)}</td>
              </tr>
            `;
          });
        }

        // Membership discount logic & pricing display
        const isVip = details.kategori_pasien === 'vip';
        const badge = document.getElementById('modal-membership-badge');
        const discountNote = document.getElementById('modal-discount-note');
        
        let grandTotal = details.grand_total;

        if (isVip) {
          badge.textContent = '★ MEMBER VIP';
          badge.className = 'text-xs font-bold text-amber-700 bg-amber-100 dark:text-amber-300 dark:bg-amber-900/30 px-2 py-0.5 rounded-full';
          
          // VIP promo: 10% discount on Grand Total
          const discount = grandTotal * 0.1;
          grandTotal = grandTotal - discount;
          discountNote.textContent = `★ Mendapat diskon Member VIP 10% (Hemat ${formatRupiahJS(discount)})`;
        } else {
          badge.textContent = 'REGULER';
          badge.className = 'text-xs font-bold text-blue-700 bg-blue-100 dark:text-blue-300 dark:bg-blue-900/30 px-2 py-0.5 rounded-full';
          discountNote.textContent = 'Upgrade ke status VIP di data pasien untuk potongan member 10%';
        }

        document.getElementById('modal-grand-total').textContent = formatRupiahJS(grandTotal);

        // Show Modal
        const modal = document.getElementById('checkout-modal');
        const box = document.getElementById('modal-box');
        modal.classList.remove('hidden');
        setTimeout(() => {
          box.classList.remove('scale-95', 'opacity-0');
          box.classList.add('scale-100', 'opacity-100');
        }, 10);
      }

      function closeCheckoutModal() {
        const modal = document.getElementById('checkout-modal');
        const box = document.getElementById('modal-box');
        
        box.classList.remove('scale-100', 'opacity-100');
        box.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
          modal.classList.add('hidden');
          activeKunjunganId = null;
        }, 300);
      }

      // AJAX submit checkout
      function submitPayment() {
        if (!activeKunjunganId) return;

        const idMetode = document.getElementById('select-metode').value;
        const btn = document.getElementById('btn-submit-payment');

        btn.disabled = true;
        btn.textContent = '⏳ Menyimpan...';

        const formData = new FormData();
        formData.append('id_kunjungan', activeKunjunganId);
        formData.append('id_metode', idMetode);

        fetch('api_proses_transaksi.php', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            alert('Sukses! Transaksi lunas berhasil dibukukan.');
            window.location.reload();
          } else {
            alert('Gagal memproses transaksi: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">check_circle</span> Konfirmasi Lunas';
          }
        })
        .catch(err => {
          alert('Terjadi kesalahan jaringan, silakan coba lagi.');
          btn.disabled = false;
          btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">check_circle</span> Konfirmasi Lunas';
        });
      }
    </script>
  </body>
</html>
