<?php
/**
 * ============================================================
 * FILE: dashboard-admin/log-keamanan.php
 * DESKRIPSI: Modul Log Keamanan & Audit System untuk Admin.
 *            Menampilkan riwayat aktivitas database dan login.
 * ============================================================
 */

require_once __DIR__ . '/../koneksi.php';

// --- TANGGAL FILTER ---
$tanggal_pilihan = $_GET['tanggal'] ?? date('Y-m-d');

try {
    // 1. Total Aktivitas Hari Ini (atau sesuai tanggal filter)
    $stmt_tot = $pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = ?");
    $stmt_tot->execute([$tanggal_pilihan]);
    $total_aktivitas = $stmt_tot->fetchColumn() ?: 0;

    // 2. Percobaan Login Gagal Hari Ini (atau sesuai tanggal filter)
    $stmt_failed = $pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = ? AND keterangan LIKE '%login gagal%'");
    $stmt_failed->execute([$tanggal_pilihan]);
    $failed_logins = $stmt_failed->fetchColumn() ?: 0;

    // 3. Perubahan Data Sensitif Hari Ini (atau sesuai tanggal filter)
    $stmt_sensitive = $pdo->prepare("
        SELECT COUNT(*) FROM audit_log 
        WHERE DATE(created_at) = ? 
          AND (aksi = 'UPDATE' OR aksi = 'DELETE' OR (nama_tabel = 'rekam_medis' AND aksi = 'INSERT'))
    ");
    $stmt_sensitive->execute([$tanggal_pilihan]);
    $sensitive_changes = $stmt_sensitive->fetchColumn() ?: 0;

    // 4. Query data audit log terbaru (JOIN staf untuk nama pengguna)
    $stmt_logs = $pdo->query("
        SELECT al.id_log, al.nama_tabel, al.id_record, al.aksi, al.keterangan, al.created_at, al.ip_address, al.sumber, s.nama_lengkap AS nama_staf
        FROM audit_log al
        LEFT JOIN staf s ON al.id_staf = s.id_staf
        ORDER BY al.created_at DESC
        LIMIT 100
    ");
    $logs = $stmt_logs->fetchAll();

    // 5. Query 3 Percobaan Login Gagal Terkini untuk Peringatan Keamanan
    $stmt_alerts = $pdo->query("
        SELECT keterangan, created_at, ip_address
        FROM audit_log
        WHERE keterangan LIKE '%login gagal%'
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $alerts = $stmt_alerts->fetchAll();

} catch (PDOException $e) {
    $total_aktivitas = 0;
    $failed_logins = 0;
    $sensitive_changes = 0;
    $logs = [];
    $alerts = [];
}
?>
<!DOCTYPE html>
<html class="light" lang="id">

<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title>Log Keamanan Sistem | GlowSkin Admin</title>

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
            "on-tertiary-fixed": "#0b1c30",
            "surface-dim": "var(--surface-dim)",
            "surface-container-lowest": "var(--surface-container-lowest)",
            "on-tertiary-container": "#2b3b50",
            "inverse-primary": "#4fdbc8",
            "inverse-on-surface": "#eff1f3",
            "on-surface": "var(--on-surface)",
            "error-container": "#ffdad6",
            "surface-bright": "var(--surface-lowest)",
            "surface": "var(--surface)",
            "on-tertiary-fixed-variant": "#38485d",
            "on-background": "#191c1e",
            "tertiary-fixed-dim": "#b7c8e1",
            "on-secondary": "#ffffff",
            "on-secondary-fixed": "#40000d",
            "tertiary-fixed": "#d3e4fe",
            "surface-container-highest": "var(--surface-container-highest)",
            "inverse-surface": "#2d3133",
            "secondary-container": "#dc2c4f",
            "primary-fixed": "#71f8e4",
            "primary-container": "var(--primary-container)",
            "on-secondary-container": "#fffbff",
            "surface-container-low": "var(--surface-container-low)",
            "on-error": "#ffffff",
            "outline-variant": "var(--outline-variant)",
            "on-tertiary": "#ffffff",
            "on-secondary-fixed-variant": "#92002a",
            "on-primary-container": "#00423b",
            "tertiary": "#505f76",
            "surface-variant": "var(--surface-container-highest)",
            "secondary": "#b90538",
            "on-error-container": "#93000a",
            "tertiary-container": "#95a5be",
            "background": "var(--surface)",
            "on-surface-variant": "var(--on-surface-variant)",
            "surface-container-high": "var(--surface-container-high)",
            "surface-tint": "var(--primary)",
            "primary-fixed-dim": "#4fdbc8",
            "primary": "var(--primary)",
            "secondary-fixed": "#ffdadb",
            "error": "#ba1a1a",
            "secondary-fixed-dim": "#ffb2b7",
            "on-primary-fixed-variant": "#005048",
            "on-primary-fixed": "#00201c",
            "on-primary": "#ffffff",
            "surface-container": "var(--surface-container)",
            "outline": "var(--outline)"
          },
          borderRadius: {
            "DEFAULT": "0.125rem",
            "lg": "0.25rem",
            "xl": "0.5rem",
            "full": "0.75rem"
          },
          spacing: {
            "sm": "8px",
            "xs": "4px",
            "xl": "40px",
            "lg": "24px",
            "md": "16px",
            "container-max": "1440px",
            "base": "4px",
            "sidebar-width": "260px"
          },
          fontFamily: {
            "label-caps": ["Plus Jakarta Sans"],
            "display-lg": ["Plus Jakarta Sans"],
            "body-sm": ["Plus Jakarta Sans"],
            "body-md": ["Plus Jakarta Sans"],
            "headline-md": ["Plus Jakarta Sans"],
            "title-sm": ["Plus Jakarta Sans"]
          },
          fontSize: {
            "label-caps": ["12px", { "lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "700" }],
            "display-lg": ["40px", { "lineHeight": "48px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
            "body-sm": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
            "body-md": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
            "headline-md": ["24px", { "lineHeight": "32px", "fontWeight": "600" }],
            "title-sm": ["18px", { "lineHeight": "24px", "fontWeight": "600" }]
          }
        }
      }
    }
  </script>
  <style>
    .status-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }
  </style>
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
      <a class="group relative flex items-center px-lg py-3 text-on-surface-variant dark:text-slate-300 hover:bg-surface-container-high dark:hover:bg-slate-800/50 transition-colors" href="transaksi.php">
        <span class="material-symbols-outlined mr-3">payments</span>
        <span class="font-label-caps text-label-caps">Transaksi &amp; Pembayaran</span>
      </a>
      <!-- Active Tab: Log Keamanan Sistem -->
      <a class="group relative flex items-center px-lg py-3 text-primary dark:text-primary-fixed-dim font-bold border-l-4 border-primary dark:border-primary-fixed-dim bg-primary/5 transition-all duration-200" href="log-keamanan.php">
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
        <div class="relative w-full group">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant dark:text-slate-400 pointer-events-none">search</span>
          <input class="w-full pl-10 pr-4 py-2 bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg font-body-sm text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" placeholder="Search security logs..." type="text" id="search-input" />
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
          <?php if ($failed_logins > 0) : ?>
            <span class="absolute top-0 right-0 w-2 h-2 bg-secondary rounded-full border-2 border-surface-container-lowest dark:border-[#0f172a]"></span>
          <?php endif; ?>
        </button>

        <div class="flex items-center gap-2 text-on-surface-variant dark:text-slate-300 bg-surface-container-low/50 dark:bg-slate-800/50 px-3 py-1.5 rounded-lg border border-outline-variant/30 dark:border-slate-800">
          <span class="material-symbols-outlined text-[18px]">calendar_today</span>
          <input type="date" id="calendar-select" value="<?= htmlspecialchars($tanggal_pilihan) ?>" class="bg-transparent border-none p-0 text-body-sm font-medium focus:ring-0 outline-none text-on-surface cursor-pointer dark:text-white" onchange="window.location.href = '?tanggal=' + this.value;">
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
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-md mb-xl">
        <div>
          <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface">Log Keamanan Sistem</h2>
          <p class="font-body-md text-body-md text-on-surface-variant max-w-2xl">
            Pemantauan aktivitas database, akses sistem secara real-time, dan audit kepatuhan untuk integritas data klinik.
          </p>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
        <!-- Stat Card 1 -->
        <div class="bg-surface dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 p-lg rounded-xl flex items-start justify-between shadow-sm">
          <div>
            <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 mb-sm">TOTAL AKTIVITAS (TANGGAL INI)</p>
            <h3 class="text-[32px] font-bold text-on-surface dark:text-slate-100"><?= $total_aktivitas ?></h3>
            <div class="flex items-center gap-xs text-primary dark:text-primary-fixed-dim mt-xs">
              <span class="material-symbols-outlined text-[16px]">info</span>
              <span class="font-body-sm text-body-sm">Aktivitas tercatat di database</span>
            </div>
          </div>
          <div class="w-12 h-12 rounded-lg bg-primary-container/10 flex items-center justify-center text-primary dark:text-primary-fixed-dim">
            <span class="material-symbols-outlined text-[28px]">query_stats</span>
          </div>
        </div>

        <!-- Stat Card 2 (Warning) -->
        <div class="bg-surface dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 p-lg rounded-xl flex items-start justify-between shadow-sm <?= $failed_logins > 0 ? 'border-l-4 border-l-secondary' : '' ?>">
          <div>
            <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 mb-sm">PERCOBAAN LOGIN GAGAL</p>
            <h3 class="text-[32px] font-bold <?= $failed_logins > 0 ? 'text-secondary dark:text-red-400' : 'text-on-surface dark:text-slate-100' ?>"><?= $failed_logins ?></h3>
            <div class="flex items-center gap-xs <?= $failed_logins > 0 ? 'text-secondary dark:text-red-400' : 'text-on-surface-variant dark:text-slate-400' ?> mt-xs">
              <span class="material-symbols-outlined text-[16px]"><?= $failed_logins > 0 ? 'warning' : 'check_circle' ?></span>
              <span class="font-body-sm text-body-sm"><?= $failed_logins > 0 ? 'Butuh Perhatian Segera' : 'Kondisi Aman' ?></span>
            </div>
          </div>
          <div class="w-12 h-12 rounded-lg bg-secondary-container/10 flex items-center justify-center text-secondary dark:text-red-450">
            <span class="material-symbols-outlined text-[28px]">lock_reset</span>
          </div>
        </div>

        <!-- Stat Card 3 -->
        <div class="bg-surface dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 p-lg rounded-xl flex items-start justify-between shadow-sm">
          <div>
            <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 mb-sm">PERUBAHAN DATA SENSITIF</p>
            <h3 class="text-[32px] font-bold text-on-surface dark:text-slate-100"><?= $sensitive_changes ?></h3>
            <div class="flex items-center gap-xs text-tertiary dark:text-tertiary-fixed mt-xs">
              <span class="material-symbols-outlined text-[16px]">visibility</span>
              <span class="font-body-sm text-body-sm">Terverifikasi Otomatis (UPDATE/DELETE)</span>
            </div>
          </div>
          <div class="w-12 h-12 rounded-lg bg-tertiary-container/10 flex items-center justify-center text-tertiary dark:text-tertiary-fixed">
            <span class="material-symbols-outlined text-[28px]">encrypted</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-4 gap-lg">
        <!-- Main Audit Log (Left Column) -->
        <div class="lg:col-span-3 space-y-md">
          <!-- Filters -->
          <div class="bg-surface dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 p-md rounded-xl flex flex-wrap items-center gap-md shadow-sm">
            <div class="flex-1 min-w-[200px] relative">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant dark:text-slate-400 pointer-events-none">person_search</span>
              <input class="w-full pl-10 pr-4 py-2 bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg text-body-sm text-on-surface dark:text-slate-100 focus:ring-1 focus:ring-primary focus:outline-none transition-all" placeholder="Cari berdasarkan nama pengguna..." type="text" id="filter-user" />
            </div>
            <select class="px-md py-2 bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg text-body-sm text-on-surface dark:text-slate-100 focus:ring-1 focus:ring-primary focus:outline-none min-w-[160px] outline-none cursor-pointer" id="filter-activity">
              <option value="all">Semua Aktivitas</option>
              <option value="update">Update Data (UPDATE)</option>
              <option value="insert">Insert Data (INSERT)</option>
              <option value="delete">Delete Data (DELETE)</option>
            </select>
            <button class="p-2 bg-primary/10 text-primary dark:text-primary-fixed-dim rounded-lg hover:bg-primary/20 transition-colors">
              <span class="material-symbols-outlined">filter_list</span>
            </button>
          </div>

          <!-- Audit Table -->
          <div class="bg-surface dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-surface-container-low dark:bg-slate-900 border-b border-outline-variant dark:border-slate-800">
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400">WAKTU & TANGGAL</th>
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400">PENGGUNA</th>
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400">AKTIVITAS (KETERANGAN LOG)</th>
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400">ALAMAT IP</th>
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400">STATUS</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/30 dark:divide-slate-800/50" id="log-table-body">
                <?php if (empty($logs)) : ?>
                  <tr>
                    <td colspan="5" class="px-lg py-8 text-center text-on-surface-variant dark:text-slate-400 italic">Belum ada aktivitas terekam di database.</td>
                  </tr>
                <?php else : 
                  foreach ($logs as $log) : 
                    // Tentukan inisial avatar dan badge
                    $badge = '';
                    if ($log['nama_staf']) {
                        $nama_user = htmlspecialchars($log['nama_staf']);
                        $nama_parts = explode(' ', $nama_user);
                        $inisial = strtoupper(substr($nama_parts[0], 0, 1));
                        if (count($nama_parts) > 1) {
                            $inisial .= strtoupper(substr(end($nama_parts), 0, 1));
                        }
                        $badge = !empty($log['sumber']) ? htmlspecialchars($log['sumber']) : 'Admin Dashboard';
                    } else {
                        // Jika nama staf kosong, cek apakah dari sumber/dokter
                        $sumber_text = !empty($log['sumber']) ? $log['sumber'] : 'Sistem';
                        if (strpos($sumber_text, 'dr. ') === 0) {
                            $nama_user = htmlspecialchars($sumber_text);
                            $inisial = 'DR';
                            $badge = 'Dokter Dashboard';
                        } elseif ($sumber_text === 'Admin Dashboard') {
                            $nama_user = 'Admin Klinik';
                            $inisial = 'AD';
                            $badge = 'Admin Dashboard';
                        } else {
                            $nama_user = htmlspecialchars($sumber_text);
                            $inisial = ($sumber_text === 'Landing Page') ? 'LP' : 'SYS';
                            $badge = ($sumber_text === 'Landing Page') ? 'Landing Page' : 'Sistem';
                        }
                    }

                    // Ambil IP Address
                    $ip = !empty($log['ip_address']) ? $log['ip_address'] : '127.0.0.1';

                    // Klasifikasikan status & style (Success vs Failed)
                    $is_failed = (stripos($log['keterangan'], 'gagal') !== false || stripos($log['keterangan'], 'fail') !== false);
                    $status_dot_color = $is_failed ? 'bg-secondary' : 'bg-primary';
                    $status_text = $is_failed ? 'Failed' : 'Success';
                    $status_badge_class = $is_failed 
                        ? 'bg-secondary/10 text-secondary dark:text-red-450' 
                        : 'bg-primary/10 text-primary dark:text-primary-fixed-dim';

                    // Atribut data-activity untuk dynamic JS filtering
                    $activity_slug = strtolower($log['aksi']);
                ?>
                  <tr class="hover:bg-primary/5 dark:hover:bg-slate-800/50 transition-colors group log-row"
                      data-user="<?= strtolower($nama_user) ?>" 
                      data-activity="<?= $activity_slug ?>" 
                      data-status="<?= $is_failed ? 'failed' : 'success' ?>">
                    <!-- Waktu -->
                    <td class="px-lg py-md font-body-sm text-body-sm text-on-surface dark:text-slate-300 font-medium">
                      <?= date('d M Y H:i:s', strtotime($log['created_at'])) ?>
                    </td>
                    <!-- Pengguna -->
                    <td class="px-lg py-md">
                      <div class="flex items-center gap-sm">
                        <div class="w-8 h-8 rounded-full bg-surface-container-high dark:bg-slate-900 flex items-center justify-center font-bold text-primary dark:text-primary-fixed-dim text-[12px] flex-shrink-0">
                          <?= $inisial ?>
                        </div>
                        <div>
                          <span class="font-body-md text-body-md text-on-surface dark:text-slate-100 block font-semibold leading-tight"><?= $nama_user ?></span>
                          <?php if (!empty($badge)) : ?>
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-surface-container-high text-on-surface-variant border border-outline-variant/30 uppercase tracking-wider block w-fit mt-0.5"><?= $badge ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <!-- Keterangan Log -->
                    <td class="px-lg py-md font-body-sm text-body-sm text-on-surface dark:text-slate-200">
                      <?= htmlspecialchars($log['keterangan']) ?>
                    </td>
                    <!-- Alamat IP -->
                    <td class="px-lg py-md font-body-sm text-body-sm <?= $is_failed ? 'text-secondary dark:text-red-400 font-bold' : 'text-on-surface-variant dark:text-slate-400' ?>">
                      <?= htmlspecialchars($ip) ?>
                    </td>
                    <!-- Status Badge -->
                    <td class="px-lg py-md">
                      <span class="inline-flex items-center gap-xs px-sm py-1 <?= $status_badge_class ?> rounded-full text-[12px] font-bold">
                        <span class="status-dot <?= $status_dot_color ?>"></span> <?= $status_text ?>
                      </span>
                    </td>
                  </tr>
                <?php 
                  endforeach;
                endif; 
                ?>
              </tbody>
            </table>

            <div class="p-md flex items-center justify-between border-t border-outline-variant dark:border-slate-800 bg-surface-container-lowest dark:bg-slate-900">
              <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-slate-400" id="log-count">
                Menampilkan 1-<?= count($logs) ?> dari <?= count($logs) ?> log
              </p>
              <div class="flex gap-xs">
                <button class="p-2 border border-outline-variant dark:border-slate-800 rounded hover:bg-surface-container-low dark:hover:bg-slate-800 text-on-surface dark:text-slate-300 disabled:opacity-50" disabled="">
                  <span class="material-symbols-outlined text-[20px] flex items-center">chevron_left</span>
                </button>
                <button class="p-2 border border-outline-variant dark:border-slate-800 rounded hover:bg-surface-container-low dark:hover:bg-slate-800 text-on-surface dark:text-slate-300" disabled="">
                  <span class="material-symbols-outlined text-[20px] flex items-center">chevron_right</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column (Alerts) -->
        <div class="lg:col-span-1 space-y-md">
          <div class="bg-surface dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 rounded-xl p-md flex flex-col shadow-sm">
            <div class="flex items-center gap-sm mb-md">
              <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">error</span>
              <h4 class="font-title-sm text-title-sm text-on-surface dark:text-slate-100">Peringatan Keamanan Terkini</h4>
            </div>

            <div class="space-y-md overflow-y-auto">
              <?php if (empty($alerts)) : ?>
                <div class="p-md border-l-4 border-primary bg-primary/5 rounded-r-lg">
                  <p class="text-xs font-bold text-primary">Kondisi Aman</p>
                  <p class="text-[11px] text-on-surface-variant dark:text-slate-300 mt-1">Tidak ada percobaan login gagal yang terdeteksi di database.</p>
                </div>
              <?php else : 
                foreach ($alerts as $alert) :
                  // Ambil IP
                  $alert_ip = !empty($alert['ip_address']) ? $alert['ip_address'] : '127.0.0.1';
              ?>
                <!-- Alert Item -->
                <div class="p-md border-l-4 border-secondary bg-secondary/5 rounded-r-lg space-y-xs">
                  <div class="flex justify-between items-start">
                    <span class="font-label-caps text-[10px] text-secondary dark:text-red-400 font-bold">PERINGATAN</span>
                    <span class="text-[10px] text-on-surface-variant dark:text-slate-400"><?= date('d M H:i', strtotime($alert['created_at'])) ?></span>
                  </div>
                  <p class="font-title-sm text-[14px] leading-tight font-bold text-on-surface dark:text-slate-100">Percobaan Login Gagal</p>
                  <p class="font-body-sm text-[12px] text-on-surface-variant dark:text-slate-300">
                    <?= htmlspecialchars($alert['keterangan']) ?>
                  </p>
                  <button class="mt-xs text-[12px] font-bold text-secondary dark:text-red-400 underline">TINDAK LANJUTI</button>
                </div>
              <?php 
                endforeach;
              endif; 
              ?>
            </div>

            <button class="mt-auto w-full py-sm text-center border-t border-outline-variant dark:border-slate-800 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim hover:bg-primary/5 dark:hover:bg-primary-fixed-dim/10 transition-colors mt-lg pt-lg">
              LIHAT SEMUA PERINGATAN
            </button>
          </div>

          <!-- Visual Anchor -->
          <div class="relative h-48 rounded-xl overflow-hidden border border-outline-variant dark:border-slate-800 shadow-sm">
            <img alt="futuristic medical laboratory" class="w-full h-full object-cover grayscale opacity-50 dark:opacity-20" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAK9NvmApffO7gCUdWzfsP_QsA_4jTsyziERUans2ncAb1Vbl8DAIZwbQTW9IAwpbsyPD6rAcsQx6W1uyae5g85TtLjuJV_yj3V2QjaT6mZYr0vWwI8xMOf1l2Roc3QPHCjQXUAkoB3IxMdOQZTzanOQ_x4OfP7J6b8rG7uf4hwUorjvMMTI8l1bTt4J_wAvQ8kBHSe5KTbjPDkBWPPqJRDbKD5hWb67jiMnZkVSzL0ou8bV1zTJ27q4KfWbney_2KOOr8BR8e9FRw" />
            <div class="absolute inset-0 bg-gradient-to-t from-background/90 dark:from-slate-900/90 to-transparent flex flex-col justify-end p-md">
              <p class="font-title-sm text-sm text-on-surface-variant dark:text-slate-200">Data Anda Aman Terlindungi</p>
              <p class="font-label-caps text-[10px] uppercase text-primary dark:text-primary-fixed-dim">Certified Security Standard</p>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Micro-interaction & Functionality Scripts -->
  <script src="../assets/js/dashboard-ui.js"></script>
  <script>
    // Initialize theme toggling
    setupThemeToggle('theme-toggle', 'dark-icon', 'light-icon');

    // Search & Activity Filter functionality
    document.addEventListener('DOMContentLoaded', () => {
      const searchInput = document.getElementById('search-input');
      const filterUser = document.getElementById('filter-user');
      const filterActivity = document.getElementById('filter-activity');
      const logRows = document.querySelectorAll('.log-row');
      const countSpan = document.getElementById('log-count');

      function filterLogs() {
        const query = searchInput.value.toLowerCase().trim();
        const userQuery = filterUser.value.toLowerCase().trim();
        const selectedActivity = filterActivity.value.toLowerCase();

        let visibleCount = 0;

        logRows.forEach(row => {
          const rowUser = row.getAttribute('data-user').toLowerCase();
          const rowActivity = row.getAttribute('data-activity').toLowerCase();
          const rowContent = row.innerText.toLowerCase();

          const matchesQuery = query === '' || rowContent.includes(query);
          const matchesUser = userQuery === '' || rowUser.includes(userQuery);
          const matchesActivity = selectedActivity === 'all' || rowActivity === selectedActivity;

          if (matchesQuery && matchesUser && matchesActivity) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });

        if (countSpan) {
          countSpan.textContent = `Menampilkan 1-${visibleCount} dari ${visibleCount} log`;
        }
      }

      if (searchInput && filterUser && filterActivity) {
        searchInput.addEventListener('input', filterLogs);
        filterUser.addEventListener('input', filterLogs);
        filterActivity.addEventListener('change', filterLogs);
      }

      // Hover animation for rows
      logRows.forEach(row => {
        row.style.transition = 'transform 0.2s ease-out';
        row.addEventListener('mouseenter', () => {
          row.style.transform = 'translateX(4px)';
        });
        row.addEventListener('mouseleave', () => {
          row.style.transform = 'translateX(0)';
        });
      });

      // Run initial filtering
      filterLogs();
    });
  </script>
</body>

</html>
