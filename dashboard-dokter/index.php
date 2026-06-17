<?php
/**
 * ============================================================
 * FILE: dashboard-dokter/index.php (sebelumnya index.html)
 * DESKRIPSI: Dashboard Jadwal Praktik untuk Dokter GlowSkin.
 * ============================================================
 * File ini menampilkan:
 * 1. Tabel "Daftar Antrean Pasien Dokter" (data live dari DB)
 *    - Query Dashboard 2: JOIN kunjungan + pasien
 *    - Filter: id_dokter = 1, tanggal = hari ini, status = Menunggu
 * 2. Link ke halaman input rekam medis (input-rekam-medis.php)
 *    yang menerima parameter id_kunjungan (bukan id_pasien).
 * ============================================================
 */

// --- SERTAKAN FILE KONEKSI DATABASE ---
require_once __DIR__ . '/../koneksi.php';

// --- TANGKAP TANGGAL TERPILIH DARI KALENDER (GET) ---
// Jika tidak ada parameter tanggal, default ke hari ini (date('Y-m-d'))
$tanggal_pilihan = $_GET['tanggal'] ?? date('Y-m-d');

/**
 * ============================================================
 * QUERY DASHBOARD 2: DAFTAR ANTREAN PASIEN DOKTER
 * ============================================================
 * Mengambil data kunjungan pasien yang:
 * - id_dokter = 1 (dokter yang sedang login, hardcoded untuk demo)
 * - tanggal_kunjungan = tanggal pilihan
 * - id_status = 1 (status "Menunggu")
 */
try {
    $stmt_antrean = $pdo->prepare("
        SELECT
            k.id_kunjungan,
            k.no_antrian,
            p.nama_lengkap,
            k.keluhan_utama
        FROM kunjungan k
        JOIN pasien p ON k.id_pasien = p.id_pasien
        WHERE k.id_dokter = :id_dokter
          AND DATE(k.tanggal_kunjungan) = :tanggal
          AND k.id_status = 1
        ORDER BY k.no_antrian ASC
    ");
    $stmt_antrean->execute([
        ':id_dokter' => 1,
        ':tanggal'   => $tanggal_pilihan
    ]);
    $daftar_antrean = $stmt_antrean->fetchAll();
} catch (PDOException $e) {
    $daftar_antrean = []; // Array kosong jika query gagal
}

/**
 * --- HITUNG STATISTIK CEPAT UNTUK CARD RINGKASAN ---
 */
// Total janji temu hari ini/tanggal terpilih (semua status)
try {
    $stmt_total = $pdo->prepare("
        SELECT COUNT(*) AS total FROM kunjungan
        WHERE id_dokter = :id_dokter AND DATE(tanggal_kunjungan) = :tanggal
    ");
    $stmt_total->execute([
        ':id_dokter' => 1,
        ':tanggal'   => $tanggal_pilihan
    ]);
    $total_janji = $stmt_total->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $total_janji = 0;
}

// Selesai konsultasi hari ini/tanggal terpilih (id_status = 3, Selesai)
try {
    $stmt_selesai = $pdo->prepare("
        SELECT COUNT(*) AS total FROM kunjungan
        WHERE id_dokter = :id_dokter AND DATE(tanggal_kunjungan) = :tanggal AND id_status = 3
    ");
    $stmt_selesai->execute([
        ':id_dokter' => 1,
        ':tanggal'   => $tanggal_pilihan
    ]);
    $total_selesai = $stmt_selesai->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $total_selesai = 0;
}

// Menunggu antrean (id_status = 1)
$total_menunggu = count($daftar_antrean);
?>
<!DOCTYPE html>
<html class="light" lang="id">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>GlowSkin Dokter | Jadwal Praktik</title>
    
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
  <body class="bg-background text-on-surface font-body-md transition-colors duration-300">
    <!-- SideNavBar Shell -->
    <aside class="fixed h-full w-[260px] left-0 top-0 flex flex-col py-lg border-r border-outline-variant bg-surface-container-lowest z-50">
      <div class="px-lg mb-xl">
        <h1 class="font-display-lg text-display-lg text-primary tracking-tight">GlowSkin</h1>
        <p class="font-label-caps text-label-caps text-on-surface-variant/60 tracking-widest uppercase">Dokter Console</p>
      </div>
      
      <!-- Nav Menu -->
      <nav class="flex-grow px-md space-y-1">
        <a class="flex items-center gap-md px-md py-sm rounded-lg bg-primary/10 text-primary font-bold transition-all" href="index.php">
          <span class="material-symbols-outlined">calendar_today</span>
          <span>Jadwal Praktik</span>
        </a>
        <a class="flex items-center gap-md px-md py-sm rounded-lg hover:bg-surface-container-high dark:hover:bg-surface-variant transition-all text-on-surface-variant" href="input-rekam-medis.php">
          <span class="material-symbols-outlined">edit_note</span>
          <span>Input Rekam Medis</span>
        </a>
        <a class="flex items-center gap-md px-md py-sm rounded-lg hover:bg-surface-container-high dark:hover:bg-surface-variant transition-all text-on-surface-variant" href="riwayat-pasien.php">
          <span class="material-symbols-outlined">patient_list</span>
          <span>Riwayat Pasien</span>
        </a>
        <div class="pt-lg border-t border-outline-variant mt-lg">
          <a class="flex items-center gap-md px-md py-sm rounded-lg hover:bg-surface-container-high dark:hover:bg-surface-variant transition-all text-on-surface-variant" href="../landing-page/index.php">
            <span class="material-symbols-outlined">logout</span>
            <span>Kembali ke Site</span>
          </a>
        </div>
      </nav>
    </aside>

    <!-- Main Content Area -->
    <div class="ml-[260px] min-h-screen flex flex-col">
      <!-- TopNavBar Shell -->
      <header class="fixed top-0 right-0 w-[calc(100%-260px)] h-16 bg-surface-container-lowest border-b border-outline-variant flex justify-between items-center px-xl z-40">
        <div class="flex items-center flex-1 max-w-md">
          <div class="relative w-full rounded-lg">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
            <input class="w-full pl-10 pr-4 py-2 bg-surface border border-outline-variant rounded-lg font-body-sm text-body-sm text-on-surface focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all" placeholder="Cari Pasien atau ID..." type="text" id="search-input" />
          </div>
        </div>

        <div class="flex items-center gap-6 ml-lg">
          <!-- Theme Toggle Button -->
          <button class="relative text-on-surface-variant hover:text-primary transition-colors flex items-center" id="theme-toggle">
            <span class="material-symbols-outlined hidden" id="dark-icon">light_mode</span>
            <span class="material-symbols-outlined" id="light-icon">dark_mode</span>
          </button>

          <button class="relative text-on-surface-variant hover:text-primary transition-colors">
            <span class="material-symbols-outlined">notifications</span>
          </button>

          <div class="flex items-center gap-2 text-on-surface-variant bg-surface-container-low/50 px-3 py-1.5 rounded-lg border border-outline-variant">
            <span class="material-symbols-outlined text-[18px]">calendar_today</span>
            <input type="date" id="calendar-select" value="<?= $tanggal_pilihan ?>" class="bg-transparent border-none p-0 text-body-sm font-medium focus:ring-0 outline-none text-on-surface cursor-pointer dark:text-white" onchange="window.location.href = '?tanggal=' + this.value;">
          </div>

          <div class="h-8 w-px bg-outline-variant"></div>

          <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
              <p class="font-title-sm text-title-sm leading-none text-primary">dr. Sarah Wijaya, Sp.KK</p>
              <p class="font-label-caps text-[10px] text-on-surface-variant">DOKTER SPESIALIS</p>
            </div>
            <div class="w-10 h-10 rounded-full border-2 border-primary-container overflow-hidden">
              <img alt="Doctor Profile" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCizprgqdDImNl_3N10D2QU4V56cW0d9IEcu6PwfSVJ8d1EfXJIpqdOGo4heFc2roP5DOUh1SaGno_rfp1fhhOZCJgTO4GB060lETXOc1LhZ6SQglNuVrqIcVBUv8vtYPbhimTcFeXJlHuvbgaVVX6s6fvVtxN94ui8dRMkRfc8a1GNoMqQc56aczHWi0Sj3QcmQiUQqXnCAyehUUmStxNe_LST_wLpqSfcZ5lmglVgNYqEEVnmkkqFtW0sZ-Wv_2BvVXAo5ZNOFaE"/>
            </div>
          </div>
        </div>
      </header>

      <!-- Page Canvas -->
      <main class="mt-16 p-xl flex-1 max-w-[1440px] mx-auto w-full bg-background dark:bg-transparent">
        <!-- Header & Breadcrumb Area -->
        <div class="p-xl pb-0 bg-transparent">
          <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-md">
            <div>
              <nav aria-label="Breadcrumb" class="flex text-body-sm text-on-surface-variant mb-2">
                <span class="hover:text-primary cursor-pointer">Dashboard</span>
                <span class="mx-2">/</span>
                <span class="text-on-surface font-medium">Jadwal Praktik</span>
              </nav>
              <div class="flex items-center gap-md">
                <h3 class="font-display-lg text-display-lg text-on-surface">Jadwal Hari Ini</h3>
                <span class="px-md py-1 rounded-full bg-primary/10 text-primary font-label-caps text-[12px] font-bold">
                  <?php
                  /**
                   * Menampilkan tanggal terpilih dalam format Indonesia.
                   * Contoh output: "17 JUNI 2026"
                   */
                  $time_pilihan = strtotime($tanggal_pilihan);
                  echo strtoupper(date('d', $time_pilihan) . ' ' . [
                      1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET',
                      4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI',
                      7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER',
                      10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
                  ][intval(date('m', $time_pilihan))] . ' ' . date('Y', $time_pilihan));
                  ?>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Content Container -->
        <div class="flex-grow p-xl space-y-xl overflow-y-auto bg-background">
          <div class="grid grid-cols-12 gap-xl">
            <!-- Left Column: Quick Stats & Main Patient Table -->
            <div class="col-span-12 lg:col-span-8 space-y-xl">
              <!-- Quick Stats for Doctor -->
              <?php
              /**
               * ============================================================
               * BLOK PHP: CARD STATISTIK CEPAT DOKTER
               * ============================================================
               * Menampilkan data live:
               * - Total Janji Temu hari ini (semua status)
               * - Selesai Konsultasi hari ini (status = 3)
               */
              ?>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-2xl flex items-center gap-md shadow-sm">
                  <div class="w-12 h-12 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">groups</span>
                  </div>
                  <div>
                    <p class="text-xs font-label-caps text-on-surface-variant mb-1 uppercase tracking-wider">Total Janji Temu</p>
                    <!-- DATA LIVE: Total kunjungan hari ini untuk dokter ini -->
                    <h3 class="text-xl font-bold"><?= $total_janji ?> Pasien</h3>
                  </div>
                </div>
                <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-2xl flex items-center gap-md shadow-sm">
                  <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">check_circle</span>
                  </div>
                  <div>
                    <p class="text-xs font-label-caps text-on-surface-variant mb-1 uppercase tracking-wider">Selesai Konsultasi</p>
                    <!-- DATA LIVE: Total kunjungan yang sudah selesai -->
                    <h3 class="text-xl font-bold"><?= $total_selesai ?> Pasien</h3>
                  </div>
                </div>
              </div>

              <!-- Main Section: Tabel Daftar Antrean Pasien (DATA LIVE) -->
              <?php
              /**
               * ============================================================
               * BLOK PHP: TABEL DAFTAR ANTREAN PASIEN (DATA LIVE)
               * ============================================================
               * Tabel ini menampilkan pasien yang sedang MENUNGGU (id_status=1)
               * untuk dokter yang login (id_dokter=1) pada hari ini.
               * 
               * Data di-loop menggunakan foreach dari hasil query $daftar_antrean.
               * Setiap baris tabel berisi:
               * - No Antrian (dari kolom no_antrian)
               * - Nama Pasien (dari JOIN tabel pasien)
               * - Keluhan Utama (dari kolom keluhan_utama)
               * - Tombol "Input Rekam Medis" → mengirim id_kunjungan via GET
               *
               * PENTING: Link aksi menggunakan id_kunjungan, BUKAN id_pasien,
               * sesuai spesifikasi yang diminta.
               */
              ?>
              <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl overflow-hidden shadow-sm">
                <div class="px-lg py-md border-b border-outline-variant bg-surface-container-low/50 flex justify-between items-center">
                  <h5 class="font-title-sm text-sm font-bold text-on-surface uppercase tracking-wide">Daftar Antrean Pasien</h5>
                  <a href="riwayat-pasien.php" class="text-primary font-bold text-xs hover:underline flex items-center gap-xs">Lihat Semua</a>
                </div>
                <div class="overflow-x-auto">
                  <table class="w-full text-left border-collapse">
                    <thead class="bg-surface-container-low/50">
                      <tr class="bg-surface-container-lowest border-b border-outline-variant">
                        <th class="px-lg py-md font-label-caps text-on-surface-variant uppercase text-[11px]">No Antrian</th>
                        <th class="px-lg py-md font-label-caps text-on-surface-variant uppercase text-[11px]">Nama Pasien</th>
                        <th class="px-lg py-md font-label-caps text-on-surface-variant uppercase text-[11px]">Keluhan Utama</th>
                        <th class="px-lg py-md font-label-caps text-on-surface-variant uppercase text-[11px]">Status</th>
                        <th class="px-lg py-md font-label-caps text-on-surface-variant uppercase text-[11px] text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/30 dark:divide-outline/30">
                      <?php
                      /**
                       * --- PERULANGAN FOREACH: MENAMPILKAN BARIS ANTREAN ---
                       * Jika $daftar_antrean kosong, tampilkan pesan info.
                       * Jika ada data, loop dan tampilkan setiap pasien.
                       */
                      if (empty($daftar_antrean)) :
                      ?>
                        <!-- Pesan jika tidak ada pasien yang sedang menunggu -->
                        <tr>
                          <td colspan="5" class="px-lg py-xl text-center text-on-surface-variant">
                            <div class="flex flex-col items-center gap-sm">
                              <span class="material-symbols-outlined text-4xl text-on-surface-variant/30">event_available</span>
                              <p class="font-title-sm">Tidak ada pasien yang menunggu saat ini.</p>
                              <p class="text-body-sm">Semua antrean hari ini sudah selesai diproses.</p>
                            </div>
                          </td>
                        </tr>
                      <?php else :
                          foreach ($daftar_antrean as $antrian) :
                              /**
                               * Membuat inisial nama pasien untuk avatar.
                               * Contoh: "Aulia Rahma" → "AR"
                               */
                              $nama_parts = explode(' ', $antrian['nama_lengkap']);
                              $inisial = strtoupper(substr($nama_parts[0], 0, 1));
                              if (count($nama_parts) > 1) {
                                  $inisial .= strtoupper(substr(end($nama_parts), 0, 1));
                              }
                      ?>
                        <!-- Baris data pasien yang menunggu -->
                        <tr class="hover:bg-primary/5 dark:hover:bg-primary-container/10 transition-colors">
                          <!-- Nomor Antrian -->
                          <td class="px-lg py-md text-sm font-semibold">
                            <span class="px-2 py-1 bg-primary/10 text-primary rounded-full text-xs font-bold">
                              #<?= htmlspecialchars($antrian['no_antrian']) ?>
                            </span>
                          </td>
                          <!-- Nama Pasien dengan Avatar Inisial -->
                          <td class="px-lg py-md">
                            <div class="flex items-center gap-sm">
                              <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">
                                <?= $inisial ?>
                              </div>
                              <span class="text-sm font-medium"><?= htmlspecialchars($antrian['nama_lengkap']) ?></span>
                            </div>
                          </td>
                          <!-- Keluhan Utama (dipotong max 50 karakter agar rapi) -->
                          <td class="px-lg py-md text-sm text-on-surface-variant max-w-[200px] truncate">
                            <?= htmlspecialchars(mb_strimwidth($antrian['keluhan_utama'] ?? '-', 0, 50, '...')) ?>
                          </td>
                          <!-- Status: Menunggu -->
                          <td class="px-lg py-md">
                            <div class="flex items-center gap-1.5">
                              <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                              <span class="text-xs font-medium text-on-surface-variant">Menunggu</span>
                            </div>
                          </td>
                          <!-- Tombol Aksi: Link ke Input Rekam Medis -->
                          <td class="px-lg py-md text-right">
                            <?php
                            /**
                             * PENTING: Link menggunakan parameter 'id_kunjungan'
                             * (bukan id_pasien). Ini sesuai spesifikasi agar form
                             * rekam medis tahu kunjungan mana yang sedang diproses.
                             */
                            ?>
                            <a href="input-rekam-medis.php?id_kunjungan=<?= $antrian['id_kunjungan'] ?>" class="inline-flex items-center gap-1 px-4 py-1.5 bg-primary text-on-primary text-xs font-bold rounded hover:brightness-110 transition-all">
                              <span class="material-symbols-outlined text-[14px]">edit_note</span>
                              Input Rekam Medis
                            </a>
                          </td>
                        </tr>
                      <?php
                          endforeach;
                      endif;
                      ?>
                    </tbody>
                  </table>
                </div>
              </section>
            </div>

            <!-- Side Panel: Stats 3 & Important Notes -->
            <aside class="col-span-12 lg:col-span-4 space-y-lg">
              <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-2xl flex items-center gap-md border-l-4 border-l-secondary-container shadow-sm">
                <div class="w-12 h-12 bg-secondary-fixed dark:bg-secondary-fixed-dim/20 rounded-full flex items-center justify-center text-secondary dark:text-secondary-fixed-dim">
                  <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">timer</span>
                </div>
                <div>
                  <p class="text-xs font-label-caps text-on-surface-variant mb-1 uppercase tracking-wider">Menunggu Antrean</p>
                  <div class="flex items-center gap-2">
                    <!-- DATA LIVE: Jumlah pasien yang menunggu -->
                    <h3 class="text-xl font-bold"><?= $total_menunggu ?> Pasien</h3>
                    <?php if ($total_menunggu > 0) : ?>
                    <span class="px-2 py-0.5 bg-secondary-container text-white text-[10px] font-bold rounded-full">URGENT</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Catatan Penting Card -->
              <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg shadow-sm">
                <div class="flex items-center justify-between mb-md">
                  <h5 class="font-title-sm text-sm font-bold text-on-surface uppercase tracking-wide">Catatan Penting</h5>
                  <span class="material-symbols-outlined text-on-surface-variant/40">push_pin</span>
                </div>
                <div class="space-y-sm">
                  <div class="p-3 bg-secondary-fixed/30 dark:bg-secondary-fixed-dim/10 border-l-4 border-l-secondary dark:border-l-secondary-fixed-dim rounded-r flex gap-sm">
                    <span class="material-symbols-outlined text-secondary dark:text-secondary-fixed-dim text-sm">priority_high</span>
                    <p class="text-xs font-medium text-on-surface leading-normal">Pastikan stok serum Vitamin C diperiksa sebelum pasien pukul 13:00.</p>
                  </div>
                  <div class="p-3 bg-surface-container-low border-l-4 border-l-tertiary rounded-r flex gap-sm">
                    <span class="material-symbols-outlined text-tertiary text-sm">info</span>
                    <p class="text-xs font-medium text-on-surface leading-normal">Maintenance alat Laser Harmony pukul 15:30.</p>
                  </div>
                </div>
              </section>
            </aside>
          </div>
        </div>
      </main>
    </div>

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
