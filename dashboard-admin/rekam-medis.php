<?php
require_once __DIR__ . '/../koneksi.php';

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatTanggalIndonesia($date) {
    if (!$date) {
        return '-';
    }

    $bulan = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    $timestamp = strtotime($date);
    return date('d', $timestamp) . ' ' . $bulan[(int)date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function getInitials($name) {
    $words = preg_split('/\s+/', trim((string)$name));
    $initials = '';
    foreach ($words as $word) {
        if ($word !== '') {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials ?: 'GS';
}

$today_visits = 0;
$service_categories = [];
$medical_records = [];

try {
    $today_visits = (int)$pdo->query("SELECT COUNT(*) FROM kunjungan WHERE tanggal_kunjungan = CURDATE()")->fetchColumn();

    $service_categories = $pdo->query("
        SELECT id, nama
        FROM ref_jenis_layanan
        ORDER BY id
    ")->fetchAll();

    $stmt_records = $pdo->query("
        SELECT
            k.id_kunjungan,
            k.kode_kunjungan,
            k.tanggal_kunjungan,
            k.waktu_daftar,
            k.keluhan_utama,
            p.kode_pasien,
            p.nama_lengkap AS nama_pasien,
            p.tanggal_lahir,
            p.alergi,
            p.kategori AS kategori_pasien,
            jk.nama AS jenis_kelamin,
            d.nama_lengkap AS nama_dokter,
            sk.nama AS status_kunjungan,
            rm.anamnesis,
            rm.diagnosa,
            rm.tindakan,
            rm.tekanan_darah,
            GROUP_CONCAT(DISTINCT l.nama_layanan ORDER BY l.nama_layanan SEPARATOR ', ') AS layanan,
            GROUP_CONCAT(DISTINCT jl.id ORDER BY jl.id SEPARATOR ',') AS kategori_layanan_ids
        FROM kunjungan k
        JOIN pasien p ON k.id_pasien = p.id_pasien
        JOIN ref_jenis_kelamin jk ON p.id_jenis_kelamin = jk.id
        JOIN dokter d ON k.id_dokter = d.id_dokter
        JOIN ref_status_kunjungan sk ON k.id_status = sk.id
        LEFT JOIN rekam_medis rm ON k.id_kunjungan = rm.id_kunjungan
        LEFT JOIN detail_layanan dl ON k.id_kunjungan = dl.id_kunjungan
        LEFT JOIN layanan l ON dl.id_layanan = l.id_layanan
        LEFT JOIN ref_jenis_layanan jl ON l.id_jenis_layanan = jl.id
        GROUP BY
            k.id_kunjungan, k.kode_kunjungan, k.tanggal_kunjungan, k.waktu_daftar,
            k.keluhan_utama, p.kode_pasien, p.nama_lengkap, p.tanggal_lahir,
            p.alergi, p.kategori, jk.nama, d.nama_lengkap, sk.nama,
            rm.anamnesis, rm.diagnosa, rm.tindakan, rm.tekanan_darah
        ORDER BY k.tanggal_kunjungan DESC, k.waktu_daftar DESC, k.id_kunjungan DESC
    ");
    $medical_records = $stmt_records->fetchAll();
} catch (PDOException $e) {
    $today_visits = 0;
    $service_categories = [];
    $medical_records = [];
}

$selected_record = $medical_records[0] ?? null;
$selected_summary = $selected_record
    ? ($selected_record['tindakan'] ?: ($selected_record['diagnosa'] ?: ($selected_record['anamnesis'] ?: $selected_record['keluhan_utama'])))
    : 'Pilih salah satu kunjungan untuk melihat ringkasan medis.';
$selected_age = ($selected_record && $selected_record['tanggal_lahir'])
    ? (int)date_diff(date_create($selected_record['tanggal_lahir']), date_create('today'))->y
    : '-';
$selected_allergy = ($selected_record && trim((string)($selected_record['alergi'] ?? '')) !== '')
    ? $selected_record['alergi']
    : 'Tidak Ada';
$selected_avatar = $selected_record
    ? 'https://ui-avatars.com/api/?name=' . urlencode($selected_record['nama_pasien']) . '&background=E0F2F1&color=00796B&bold=true&size=128'
    : 'https://ui-avatars.com/api/?name=GlowSkin&background=E0F2F1&color=00796B&bold=true&size=128';
?>
<!DOCTYPE html>
<html class="light" lang="id">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Rekam Medis | GlowSkin Clinical Management</title>

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
              "tertiary-fixed-dim": "#b7c8e1",
              "on-primary-container": "#00423b",
              "on-error-container": "#93000a",
              "outline": "var(--outline)",
              "background": "var(--surface)",
              "primary-fixed": "#71f8e4",
              "surface-container-low": "var(--surface-container-low)",
              "on-tertiary-fixed": "#0b1c30",
              "surface-variant": "var(--surface-container-highest)",
              "on-tertiary-fixed-variant": "#38485d",
              "on-secondary-container": "#fffbff",
              "tertiary": "#505f76",
              "inverse-primary": "#4fdbc8",
              "on-primary-fixed-variant": "#005048",
              "primary": "var(--primary)",
              "on-tertiary": "#ffffff",
              "surface-container-high": "var(--surface-container-high)",
              "on-error": "#ffffff",
              "on-surface": "var(--on-surface)",
              "inverse-surface": "#2d3133",
              "primary-container": "var(--primary-container)",
              "surface-dim": "var(--surface-dim)",
              "on-tertiary-container": "#2b3b50",
              "on-secondary-fixed": "#40000d",
              "outline-variant": "var(--outline-variant)",
              "inverse-on-surface": "#eff1f3",
              "secondary-fixed-dim": "#ffb2b7",
              "primary-fixed-dim": "#4fdbc8",
              "surface-container-lowest": "var(--surface-container-lowest)",
              "on-surface-variant": "var(--on-surface-variant)",
              "error-container": "#ffdad6",
              "on-secondary": "#ffffff",
              "error": "#ba1a1a",
              "surface-bright": "var(--surface-lowest)",
              "secondary": "#b90538",
              "on-primary-fixed": "#00201c",
              "secondary-fixed": "#ffdadb",
              "secondary-container": "#dc2c4f",
              "surface-container-highest": "var(--surface-container-highest)",
              "surface-tint": "var(--primary)",
              "surface-container": "var(--surface-container)",
              "on-secondary-fixed-variant": "#92002a",
              "surface": "var(--surface)",
              "on-primary": "#ffffff",
              "on-background": "#191c1e",
              "tertiary-container": "#95a5be",
              "tertiary-fixed": "#d3e4fe"
            },
            borderRadius: {
              "DEFAULT": "0.125rem",
              "lg": "0.25rem",
              "xl": "0.5rem",
              "full": "0.75rem"
            },
            spacing: {
              "lg": "24px",
              "container-max": "1440px",
              "xs": "4px",
              "sidebar-width": "260px",
              "md": "16px",
              "base": "4px",
              "xl": "40px",
              "sm": "8px"
            },
            fontFamily: {
              "title-sm": ["Plus Jakarta Sans"],
              "headline-md": ["Plus Jakarta Sans"],
              "display-lg": ["Plus Jakarta Sans"],
              "body-md": ["Plus Jakarta Sans"],
              "label-caps": ["Plus Jakarta Sans"],
              "display-lg-mobile": ["Plus Jakarta Sans"],
              "body-sm": ["Plus Jakarta Sans"]
            },
            fontSize: {
              "title-sm": ["18px", { "lineHeight": "24px", "fontWeight": "600" }],
              "headline-md": ["24px", { "lineHeight": "32px", "fontWeight": "600" }],
              "display-lg": ["40px", { "lineHeight": "48px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
              "body-md": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
              "label-caps": ["12px", { "lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "700" }],
              "display-lg-mobile": ["32px", { "lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
              "body-sm": ["14px", { "lineHeight": "20px", "fontWeight": "400" }]
            }
          },
        },
      }
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
        <!-- Active Tab -->
        <a class="group relative flex items-center px-lg py-3 text-primary dark:text-primary-fixed-dim font-bold border-l-4 border-primary dark:border-primary-fixed-dim bg-primary/5 transition-all duration-200" href="rekam-medis.php">
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
        <div class="flex items-center flex-1 max-w-md">
          <div class="relative w-full rounded-lg">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant dark:text-slate-400">search</span>
            <input class="w-full pl-10 pr-4 py-2 bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg font-body-sm text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" placeholder="Cari rekam medis atau pasien..." type="text" id="search-input" />
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
            <span class="absolute top-0 right-0 w-2 h-2 bg-secondary rounded-full border-2 border-surface-container-lowest dark:border-[#0f172a]"></span>
          </button>

          <div class="flex items-center gap-2 text-on-surface-variant dark:text-slate-300 bg-surface-container-low/50 dark:bg-slate-800/50 px-3 py-1.5 rounded-lg border border-outline-variant/30 dark:border-slate-800">
            <span class="material-symbols-outlined text-[18px]">calendar_today</span>
            <input type="date" id="calendar-select" class="bg-transparent border-none p-0 text-body-sm font-medium focus:ring-0 outline-none text-on-surface cursor-pointer dark:text-white" onchange="window.location.href = '?tanggal=' + this.value;">
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
        <div class="max-w-7xl mx-auto space-y-lg">
          <!-- Page Header & Actions -->
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
            <div>
              <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface">Data Pasien &amp; Rekam Medis</h2>
              <p class="font-body-md text-body-md text-on-surface-variant">Manajemen data riwayat klinis pasien secara real-time.</p>
            </div>
          </div>
 
          <!-- Filters & Stats Bento -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-lg mb-xl">
            <div class="col-span-1 md:col-span-3 bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 p-lg rounded-xl flex flex-wrap gap-md items-center shadow-sm">
              <div class="flex flex-col gap-xs min-w-[200px]">
                <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400">Periode Kunjungan</label>
                <select class="bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg px-md py-sm text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary outline-none transition-all" id="filter-period">
                  <option value="all">Semua Waktu</option>
                  <option value="today">Hari Ini</option>
                  <option value="week">7 Hari Terakhir</option>
                  <option value="month">Bulan Ini</option>
                </select>
              </div>
              <div class="flex flex-col gap-xs min-w-[200px]">
                <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400">Kategori Layanan</label>
                <select class="bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg px-md py-sm text-body-sm text-on-surface dark:text-slate-100 focus:ring-2 focus:ring-primary outline-none transition-all" id="filter-service">
                  <option value="all">Semua Layanan</option>
                  <?php foreach ($service_categories as $category) : ?>
                    <option value="<?= h($category['id']) ?>"><?= h($category['nama']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="flex flex-col gap-xs min-w-[200px]">
                <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400">Status</label>
                <div class="flex gap-sm">
                  <button class="status-btn px-md py-sm rounded-full font-label-caps text-label-caps bg-primary/10 text-primary dark:text-primary-fixed-dim border border-primary/20 transition-all" data-status="all">Semua</button>
                  <button class="status-btn px-md py-sm rounded-full font-label-caps text-label-caps bg-surface-container-high dark:bg-slate-800 text-on-surface-variant dark:text-slate-300 hover:bg-primary/10 dark:hover:bg-primary-fixed-dim/20 hover:text-primary dark:hover:text-primary-fixed-dim transition-all border border-transparent" data-status="menunggu">Menunggu</button>
                  <button class="status-btn px-md py-sm rounded-full font-label-caps text-label-caps bg-surface-container-high dark:bg-slate-800 text-on-surface-variant dark:text-slate-300 hover:bg-primary/10 dark:hover:bg-primary-fixed-dim/20 hover:text-primary dark:hover:text-primary-fixed-dim transition-all border border-transparent" data-status="selesai">Selesai</button>
                </div>
              </div>
            </div>
            <div class="bg-primary text-on-primary p-lg rounded-xl flex flex-col justify-between shadow-sm">
              <span class="font-label-caps text-label-caps opacity-80 uppercase">Total Kunjungan Hari Ini</span>
              <div class="flex items-end justify-between">
                <span class="text-[32px] font-bold leading-none"><?= number_format($today_visits, 0, ',', '.') ?></span>
                <span class="material-symbols-outlined text-[32px]">trending_up</span>
              </div>
            </div>
          </div>

          <!-- Main Data Table & Preview Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">
            <!-- Data Table -->
            <div class="lg:col-span-2 bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 rounded-xl overflow-hidden flex flex-col shadow-sm">
              <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                  <thead>
                    <tr class="bg-surface-container-low dark:bg-slate-900 border-b border-outline-variant dark:border-slate-800">
                      <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Tanggal</th>
                      <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">ID &amp; Pasien</th>
                      <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Layanan</th>
                      <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Dokter</th>
                      <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-outline-variant/30 dark:divide-slate-800/50" id="record-table-body">
                    <?php if (!empty($medical_records)) : ?>
                      <?php foreach ($medical_records as $index => $record) :
                        $is_active_row = $index === 0;
                        $status_filter = in_array($record['status_kunjungan'], ['Selesai', 'Batal'], true) ? 'selesai' : 'menunggu';
                        $category_ids = $record['kategori_layanan_ids'] ? ',' . $record['kategori_layanan_ids'] . ',' : '';
                        $summary = $record['tindakan'] ?: ($record['diagnosa'] ?: ($record['anamnesis'] ?: $record['keluhan_utama']));
                        $age = $record['tanggal_lahir'] ? (int)date_diff(date_create($record['tanggal_lahir']), date_create('today'))->y : '-';
                        $blood_pressure = $record['tekanan_darah'] ?: '-';
                        $allergy = trim((string)($record['alergi'] ?? '')) !== '' ? $record['alergi'] : 'Tidak Ada';
                        $visit_date = formatTanggalIndonesia($record['tanggal_kunjungan']);
                        $visit_time = $record['waktu_daftar'] ? date('H:i', strtotime($record['waktu_daftar'])) . ' WIB' : '-';
                        $initials = getInitials($record['nama_pasien']);
                        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($record['nama_pasien']) . '&background=E0F2F1&color=00796B&bold=true&size=128';
                      ?>
                    <tr class="hover:bg-primary/5 dark:hover:bg-slate-800/50 transition-colors cursor-pointer group record-row <?= $is_active_row ? 'bg-primary/5 dark:bg-primary-fixed-dim/5 border-l-4 border-primary dark:border-primary-fixed-dim' : '' ?>"
                        data-service="<?= h($category_ids) ?>"
                        data-status="<?= h($status_filter) ?>"
                        data-date-iso="<?= h($record['tanggal_kunjungan']) ?>"
                        data-name="<?= h($record['nama_pasien']) ?>"
                        data-id="<?= h($record['kode_pasien']) ?>"
                        data-age="<?= h($age) ?>"
                        data-gender="<?= h($record['jenis_kelamin']) ?>"
                        data-blood="<?= h($blood_pressure) ?>"
                        data-allergy="<?= h($allergy) ?>"
                        data-summary="<?= h($summary) ?>"
                        data-service-label="<?= h($record['layanan'] ?: 'Belum ada layanan') ?>"
                        data-date-label="<?= h($visit_date) ?>"
                        data-avatar="<?= h($avatar) ?>">
                      <td class="px-lg py-lg">
                        <div class="flex flex-col">
                          <span class="font-title-sm text-title-sm text-on-surface dark:text-slate-100"><?= h($visit_date) ?></span>
                          <span class="text-[12px] text-on-surface-variant dark:text-slate-400"><?= h($visit_time) ?></span>
                        </div>
                      </td>
                      <td class="px-lg py-lg">
                        <div class="flex items-center gap-md">
                          <div class="w-8 h-8 rounded-full bg-primary/10 text-primary dark:text-primary-fixed-dim flex items-center justify-center font-bold text-[12px] flex-shrink-0"><?= h($initials) ?></div>
                          <div class="flex flex-col">
                            <span class="font-body-md text-body-md font-semibold text-on-surface dark:text-slate-100"><?= h($record['nama_pasien']) ?></span>
                            <span class="text-[12px] text-on-surface-variant dark:text-slate-400">ID: <?= h($record['kode_pasien']) ?></span>
                          </div>
                        </div>
                      </td>
                      <td class="px-lg py-lg">
                        <span class="inline-block px-sm py-xs bg-tertiary-fixed-dim/20 text-tertiary dark:text-tertiary-fixed font-label-caps text-label-caps rounded"><?= h($record['layanan'] ?: 'Belum ada layanan') ?></span>
                      </td>
                      <td class="px-lg py-lg font-body-sm text-body-sm text-on-surface dark:text-slate-300"><?= h($record['nama_dokter']) ?></td>
                      <td class="px-lg py-lg text-right">
                        <button class="btn-detail-row text-primary dark:text-primary-fixed-dim hover:underline font-title-sm text-title-sm">Detail</button>
                      </td>
                    </tr>
                      <?php endforeach; ?>
                    <?php else : ?>
                    <tr>
                      <td colspan="5" class="px-lg py-xl text-center text-on-surface-variant dark:text-slate-400 italic">Belum ada data kunjungan atau rekam medis di database.</td>
                    </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <!-- Table Footer & Pagination -->
              <div class="mt-auto border-t border-outline-variant dark:border-slate-800 p-lg flex justify-between items-center bg-surface-container-lowest dark:bg-slate-900">
                <span class="font-body-sm text-body-sm text-on-surface-variant dark:text-slate-400" id="record-count">Menampilkan <?= count($medical_records) ?> dari <?= count($medical_records) ?> data</span>
                <div class="flex gap-sm">
                  <button class="px-md py-sm border border-outline-variant dark:border-slate-800 rounded hover:bg-surface-container-low dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined text-[20px] dark:text-slate-300 flex items-center">chevron_left</span>
                  </button>
                  <button class="px-md py-sm border border-outline-variant dark:border-slate-800 rounded bg-primary text-on-primary">1</button>
                  <button class="px-md py-sm border border-outline-variant dark:border-slate-800 rounded hover:bg-surface-container-low dark:hover:bg-slate-800 transition-colors text-on-surface dark:text-slate-300">2</button>
                  <button class="px-md py-sm border border-outline-variant dark:border-slate-800 rounded hover:bg-surface-container-low dark:hover:bg-slate-800 transition-colors text-on-surface dark:text-slate-300">3</button>
                  <button class="px-md py-sm border border-outline-variant dark:border-slate-800 rounded hover:bg-surface-container-low dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined text-[20px] dark:text-slate-300 flex items-center">chevron_right</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Details Preview Column -->
            <div class="flex flex-col gap-lg">
              <!-- Patient Summary Card -->
              <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                <div class="p-lg border-b border-outline-variant dark:border-slate-800 bg-surface-container-low dark:bg-slate-900">
                  <h3 class="font-title-sm text-title-sm text-on-surface dark:text-slate-100">Detail Kunjungan Terpilih</h3>
                </div>
                <div class="p-lg space-y-lg">
                  <div class="flex items-start gap-md">
                    <img id="detail-avatar" alt="Selected Patient Avatar" class="w-16 h-16 rounded-xl object-cover border border-outline-variant dark:border-slate-800 flex-shrink-0" src="<?= h($selected_avatar) ?>"/>
                    <div>
                      <h4 class="font-title-sm text-title-sm text-on-surface dark:text-slate-100" id="detail-name"><?= h($selected_record['nama_pasien'] ?? 'Belum ada data') ?></h4>
                      <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-slate-400" id="detail-meta"><?= h($selected_age) ?> Tahun - <?= h($selected_record['jenis_kelamin'] ?? '-') ?></p>
                      <span class="inline-flex items-center gap-xs text-[12px] font-semibold text-primary dark:text-primary-fixed-dim">
                        <span class="w-2 h-2 rounded-full bg-primary"></span> <?= h(($selected_record['kategori_pasien'] ?? 'reguler') === 'vip' ? 'VIP / Member' : 'Pasien Reguler') ?>
                      </span>
                    </div>
                  </div>

                  <div class="space-y-md">
                    <div class="p-md bg-surface-container-low dark:bg-slate-900 rounded-lg space-y-sm">
                      <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Ringkasan Medis</p>
                      <p class="font-body-sm text-body-sm text-on-surface dark:text-slate-200" id="detail-summary"><?= h($selected_summary) ?></p>
                    </div>
                  </div>
 
                  <div class="space-y-sm">
                    <p class="font-label-caps text-label-caps text-on-surface-variant dark:text-slate-400 uppercase">Riwayat Tindakan</p>
                    <ul class="space-y-xs">
                      <li class="flex items-center justify-between font-body-sm text-body-sm py-xs border-b border-outline-variant dark:border-slate-800">
                        <span class="text-on-surface dark:text-slate-200" id="detail-service-history"><?= h($selected_record['layanan'] ?? 'Belum ada layanan') ?></span>
                        <span class="text-on-surface-variant dark:text-slate-400 italic" id="detail-date-history"><?= h($selected_record ? formatTanggalIndonesia($selected_record['tanggal_kunjungan']) : '-') ?></span>
                      </li>
                    </ul>
                  </div>
                  <button id="btn-view-all-records" class="w-full flex items-center justify-center gap-sm border border-primary dark:border-primary-fixed-dim/30 text-primary dark:text-primary-fixed-dim px-lg py-sm rounded-lg font-title-sm text-title-sm hover:bg-primary/5 dark:hover:bg-primary-fixed-dim/10 transition-all">
                    <span class="material-symbols-outlined">description</span>
                    Lihat Semua Rekam Medis
                  </button>
                </div>
              </div>

              <!-- Quick Note Card -->
              <div class="bg-primary/5 dark:bg-primary-container/10 border border-primary/20 dark:border-primary/30 rounded-xl p-lg relative overflow-hidden group shadow-sm">
                <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-[120px] opacity-10 text-primary transform group-hover:scale-110 transition-transform">medical_information</span>
                <h4 class="font-title-sm text-title-sm text-primary mb-sm">Catatan Internal</h4>
                <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-slate-300 relative z-10">Gunakan fitur pencarian untuk menemukan riwayat spesifik berdasarkan ID Rekam Medis (RM-XXXX). Pastikan data telah divalidasi oleh dokter sebelum dicetak.</p>
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

      // Initialize calendar select input value based on URL parameter or today
      const calendarSelect = document.getElementById('calendar-select');
      if (calendarSelect) {
        const urlParams = new URLSearchParams(window.location.search);
        const tanggalParam = urlParams.get('tanggal');
        if (tanggalParam) {
          calendarSelect.value = tanggalParam;
        } else {
          const today = new Date().toISOString().split('T')[0];
          calendarSelect.value = today;
        }
      }

      document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search-input');
        const filterPeriod = document.getElementById('filter-period');
        const filterService = document.getElementById('filter-service');
        const recordRows = document.querySelectorAll('.record-row');
        const statusButtons = document.querySelectorAll('.status-btn');
        const countSpan = document.getElementById('record-count');

        // Selected visitor preview elements
        const detailAvatar = document.getElementById('detail-avatar');
        const detailName = document.getElementById('detail-name');
        const detailMeta = document.getElementById('detail-meta');
        const detailSummary = document.getElementById('detail-summary');
        const detailServiceHistory = document.getElementById('detail-service-history');
        const detailDateHistory = document.getElementById('detail-date-history');

        let activeStatusFilter = 'all';

        // Row Selection Details Update
        recordRows.forEach(row => {
          row.addEventListener('click', function() {
            // Remove active classes from all rows
            recordRows.forEach(r => r.classList.remove('bg-primary/5', 'border-l-4', 'border-primary', 'dark:border-primary-fixed-dim'));

            // Add active styles to clicked row
            this.classList.add('bg-primary/5', 'border-l-4', 'border-primary', 'dark:border-primary-fixed-dim');

            // Update details column contents
            const name = this.getAttribute('data-name');
            const age = this.getAttribute('data-age');
            const gender = this.getAttribute('data-gender');
            const summary = this.getAttribute('data-summary');
            const avatar = this.getAttribute('data-avatar');
            const serviceLabel = this.getAttribute('data-service-label');
            const dateLabel = this.getAttribute('data-date-label');

            if (detailName) detailName.textContent = name;
            if (detailMeta) detailMeta.textContent = `${age} Tahun - ${gender}`;
            if (detailSummary) detailSummary.textContent = summary;
            if (detailAvatar) detailAvatar.src = avatar;
            if (detailServiceHistory) detailServiceHistory.textContent = serviceLabel;
            if (detailDateHistory) detailDateHistory.textContent = dateLabel;
          });
        });

        // Make Detail action button click trigger the row click dynamically
        document.querySelectorAll('.btn-detail-row').forEach(btn => {
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const row = this.closest('.record-row');
            if (row) {
              row.click();
              // Scroll to details on mobile screens if needed
              const target = document.getElementById('detail-name');
              if (target && window.innerWidth < 1024) {
                target.scrollIntoView({ behavior: 'smooth' });
              }
            }
          });
        });

        // "Lihat Semua Rekam Medis" click event handler
        const btnViewAll = document.getElementById('btn-view-all-records');
        btnViewAll?.addEventListener('click', function() {
          const activeRow = document.querySelector('.record-row.bg-primary\\/5');
          if (!activeRow) {
            alert('Silakan pilih salah satu rekam medis terlebih dahulu.');
            return;
          }
          const name = activeRow.getAttribute('data-name');
          const id = activeRow.getAttribute('data-id');
          const age = activeRow.getAttribute('data-age');
          const gender = activeRow.getAttribute('data-gender');
          const date = activeRow.getAttribute('data-date-label');
          const summary = activeRow.getAttribute('data-summary');
          const service = activeRow.getAttribute('data-service-label');

          alert(`📋 REKAM MEDIS OPERASIONAL PASIEN\n---------------------------------------\nID Pasien: ${id}\nNama Pasien: ${name}\nUmur/Gender: ${age} Tahun - ${gender}\nTanggal Kunjungan: ${date}\nLayanan: ${service}\n\n[RINGKASAN DIAGNOSA & ANAMNESIS]:\n${summary}`);
        });

        // Filter Functionality
        function filterRecords() {
          const query = searchInput.value.toLowerCase().trim();
          const selectedPeriod = filterPeriod.value;
          const selectedService = filterService.value;

          let visibleCount = 0;

          recordRows.forEach(row => {
            const name = row.getAttribute('data-name').toLowerCase();
            const id = row.getAttribute('data-id').toLowerCase();
            const service = row.getAttribute('data-service') || '';
            const status = row.getAttribute('data-status');
            const visitDate = row.getAttribute('data-date-iso');

            const matchesQuery = query === '' || name.includes(query) || id.includes(query);
            const matchesService = selectedService === 'all' || service.includes(`,${selectedService},`);
            const matchesStatus = activeStatusFilter === 'all' || status === activeStatusFilter;

            const rowDate = visitDate ? new Date(`${visitDate}T00:00:00`) : null;
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const matchesPeriod =
              selectedPeriod === 'all' ||
              (rowDate && selectedPeriod === 'today' && rowDate.getTime() === today.getTime()) ||
              (rowDate && selectedPeriod === 'week' && rowDate >= new Date(today.getTime() - (6 * 24 * 60 * 60 * 1000))) ||
              (rowDate && selectedPeriod === 'month' && rowDate.getMonth() === today.getMonth() && rowDate.getFullYear() === today.getFullYear());

            if (matchesQuery && matchesService && matchesStatus && matchesPeriod) {
              row.style.display = '';
              visibleCount++;
            } else {
              row.style.display = 'none';
            }
          });

          if (countSpan) {
            countSpan.textContent = `Menampilkan ${visibleCount} dari ${recordRows.length} data`;
          }
        }

        // Status filter buttons active toggles
        statusButtons.forEach(btn => {
          btn.addEventListener('click', function() {
            statusButtons.forEach(b => {
              b.classList.remove('bg-primary/10', 'text-primary', 'border-primary/20');
              b.classList.add('bg-surface-container-high', 'dark:bg-surface-container-highest', 'text-on-surface-variant', 'border-transparent');
            });

            this.classList.remove('bg-surface-container-high', 'dark:bg-surface-container-highest', 'text-on-surface-variant', 'border-transparent');
            this.classList.add('bg-primary/10', 'text-primary', 'border-primary/20');

            activeStatusFilter = this.getAttribute('data-status');
            filterRecords();
          });
        });

        searchInput.addEventListener('input', filterRecords);
        filterPeriod.addEventListener('change', filterRecords);
        filterService.addEventListener('change', filterRecords);

        // Run initial filtering
        filterRecords();
      });
    </script>
  </body>
</html>



