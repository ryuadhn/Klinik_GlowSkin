<?php
/**
 * ============================================================
 * FILE: dashboard-dokter/riwayat-pasien.php (sebelumnya .html)
 * DESKRIPSI: Riwayat Medis Pasien untuk Dokter GlowSkin.
 * ============================================================
 * Halaman ini menampilkan daftar seluruh pasien di sebelah kiri,
 * dan kronologi rekam medis lengkap pasien terpilih di sebelah kanan.
 * Semuanya terintegrasi live ke database MySQL.
 * ============================================================
 */

// --- SERTAKAN FILE KONEKSI DATABASE ---
require_once __DIR__ . '/../koneksi.php';

// --- INITIALIZE SESSION & DYNAMIC DOCTOR SWITCHER ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['switch_dokter'])) {
    $sw = intval($_GET['switch_dokter']);
    if ($sw === 1 || $sw === 2) {
        $_SESSION['id_dokter'] = $sw;
        // Redirect to self without switch_dokter param
        $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
        $params = $_GET;
        unset($params['switch_dokter']);
        if (!empty($params)) {
            $clean_url .= '?' . http_build_query($params);
        }
        header("Location: " . $clean_url);
        exit();
    }
}
$id_dokter_login = $_SESSION['id_dokter'] ?? 1; // Default to 1 (dr. Sarah)

// Fetch current doctor details from database
try {
    $stmt_doc = $pdo->prepare("
        SELECT d.nama_lengkap, s.nama AS spesialisasi
        FROM dokter d
        JOIN ref_spesialisasi s ON d.id_spesialisasi = s.id
        WHERE d.id_dokter = :id
    ");
    $stmt_doc->execute([':id' => $id_dokter_login]);
    $doc_info = $stmt_doc->fetch(PDO::FETCH_ASSOC);
    $nama_dokter = $doc_info['nama_lengkap'] ?? 'dr. Sarah Sp.KK';
    $spesialisasi_dokter = $doc_info['spesialisasi'] ?? 'Spesialis Kulit & Kelamin';
} catch (PDOException $e) {
    $nama_dokter = 'dr. Sarah Sp.KK';
    $spesialisasi_dokter = 'Spesialis Kulit & Kelamin';
}

// Map profile photo
if ($id_dokter_login == 1) {
    $foto_dokter = 'https://lh3.googleusercontent.com/aida-public/AB6AXuCedHsWtVogRuLqa7IZRhxpnlVl7bf7oqPlJ13qcZtAxiUNk1IAqcpxkOoiBrEJCLlTtht4Xuw9YBdlwOsfrIcQwfL_I7svWDZ8IlUTm4b5ESA__67dSmEPEfRx7pWseaFDU15utK5kxpc6zqbz3vXpgPvQK-n2x1MAWv02ncy0y5fk3eo8aryvBftAEXZS6Jnt6Ss3tgxuEu4QKQgwaGk_bwP3jslqtZp4-u02z6xuD4PUmDAxGFOUaqX1NDAwnfmzQvSjR9PzNqI';
} else {
    $foto_dokter = 'https://lh3.googleusercontent.com/aida-public/AB6AXuCdnqV0hZQQyZHboUD9RM8O6NlzScyVOnO3-7r4g3zMK1pM65aD2aB5KAFOswI-qj41JeKvIqCaqfVVqks0zLotFZDxXSM68CVUHJ4YkkyN6PqO7iaj_H9JvoRQCLWvF6kyLZU_VaGMySI_JJJugcr8ZgDuU0CztzRvLm0av3bG5zXT7Fnl7bc0dUYV1SIwosc1R62DPSJ2KxccXrNHqjDztVUZhkq-Q3arqo247SfGQrguZzYxD9rYbkSTKkBf-rTW811qtgDcugc';
}

// --- QUERY 1: MENGAMBIL DAFTAR PASIEN & DIAGNOSIS TERAKHIRNYA ---
try {
    $stmt_patients = $pdo->query("
        SELECT
            p.id_pasien,
            p.kode_pasien,
            p.nama_lengkap,
            p.tanggal_lahir,
            p.alergi,
            p.alamat,
            p.created_at,
            rjk.nama AS jenis_kelamin,
            TIMESTAMPDIFF(YEAR, p.tanggal_lahir, CURDATE()) AS umur,
            COALESCE(MAX(k.tanggal_kunjungan), '-') AS kunjungan_terakhir,
            (
                SELECT rm.diagnosa
                FROM rekam_medis rm
                JOIN kunjungan k2 ON rm.id_kunjungan = k2.id_kunjungan
                WHERE k2.id_pasien = p.id_pasien
                ORDER BY k2.tanggal_kunjungan DESC, k2.waktu_daftar DESC, k2.id_kunjungan DESC
                LIMIT 1
            ) AS diagnosis_utama
        FROM pasien p
        JOIN ref_jenis_kelamin rjk ON p.id_jenis_kelamin = rjk.id
        LEFT JOIN kunjungan k ON p.id_pasien = k.id_pasien
        GROUP BY p.id_pasien, p.kode_pasien, p.nama_lengkap, p.tanggal_lahir, p.alergi, p.alamat, p.created_at, rjk.nama
        ORDER BY (CASE WHEN MAX(k.tanggal_kunjungan) IS NULL THEN 0 ELSE 1 END) DESC, MAX(k.tanggal_kunjungan) DESC, p.id_pasien DESC
    ");
    $patients = $stmt_patients->fetchAll();
} catch (PDOException $e) {
    $patients = [];
}

// --- QUERY 2: TIMELINE REKAM MEDIS LENGKAP UNTUK SETIAP PASIEN ---
try {
    // JOIN data rekam medis, kunjungan, dokter, dan status kunjungan untuk dicatat di timeline kronologi
    $stmt_records = $pdo->query("
        SELECT
            rm.id_rekam_medis,
            rm.id_kunjungan,
            rm.anamnesis,
            rm.diagnosa,
            rm.tindakan,
            rm.catatan_dokter,
            k.tanggal_kunjungan,
            k.id_pasien,
            d.nama_lengkap AS nama_dokter,
            rsk.nama AS status_nama
        FROM rekam_medis rm
        JOIN kunjungan k ON rm.id_kunjungan = k.id_kunjungan
        JOIN dokter d ON k.id_dokter = d.id_dokter
        JOIN ref_status_kunjungan rsk ON k.id_status = rsk.id
        ORDER BY k.tanggal_kunjungan DESC, k.waktu_daftar DESC, k.id_kunjungan DESC
    ");
    $all_records = $stmt_records->fetchAll();
    
    // Kelompokkan data rekam medis berdasarkan id_pasien agar pemanggilan timeline pasien lebih cepat
    $patient_timelines = [];
    foreach ($all_records as $rec) {
        $patient_timelines[$rec['id_pasien']][] = $rec;
    }
} catch (PDOException $e) {
    $patient_timelines = [];
}

// --- QUERY 3: MENGAMBIL RESEP OBAT PADA KUNJUNGAN PASIEN ---
try {
    // JOIN resep_obat dengan tabel obat untuk mengambil detail nama obat & resep
    $stmt_prescriptions = $pdo->query("
        SELECT
            ro.id_kunjungan,
            ro.jumlah,
            ro.aturan_pakai,
            o.nama_obat
        FROM resep_obat ro
        JOIN obat o ON ro.id_obat = o.id_obat
    ");
    $all_prescriptions = $stmt_prescriptions->fetchAll();
    
    // Kelompokkan resep obat berdasarkan id_kunjungan agar tersinkron di timeline kunjungan terkait
    $prescriptions_by_kunjungan = [];
    foreach ($all_prescriptions as $pres) {
        $prescriptions_by_kunjungan[$pres['id_kunjungan']][] = $pres;
    }
} catch (PDOException $e) {
    $prescriptions_by_kunjungan = [];
}

// Menyiapkan daftar tipe kulit demo yang konsisten
$skin_types = ["Kombinasi / Berminyak", "Kering", "Normal", "Sensitif", "Kombinasi"];
?>
<!DOCTYPE html>
<html class="light" lang="id">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>GlowSkin Dokter | Riwayat Pasien</title>

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
      }
    </script>
  </head>
  <body class="bg-background text-on-surface font-body-md transition-colors duration-300 min-h-screen overflow-hidden">
    <!-- SideNavBar Shell -->
    <aside class="fixed h-full w-[260px] left-0 top-0 flex flex-col py-lg border-r border-outline-variant bg-surface-container-lowest z-50">
      <div class="px-lg mb-xl">
        <h1 class="font-display-lg text-display-lg text-primary tracking-tight">GlowSkin</h1>
        <p class="font-label-caps text-label-caps text-on-surface-variant/60 tracking-widest uppercase">Dokter Console</p>
      </div>
      
      <!-- Nav Menu -->
      <nav class="flex-grow px-md space-y-1">
        <a class="flex items-center gap-md px-md py-sm rounded-lg hover:bg-surface-container-high dark:hover:bg-surface-variant transition-all text-on-surface-variant" href="index.php">
          <span class="material-symbols-outlined">calendar_today</span>
          <span>Jadwal Praktik</span>
        </a>
        <a class="flex items-center gap-md px-md py-sm rounded-lg hover:bg-surface-container-high dark:hover:bg-surface-variant transition-all text-on-surface-variant" href="input-rekam-medis.php">
          <span class="material-symbols-outlined">edit_note</span>
          <span>Input Rekam Medis</span>
        </a>
        <a class="flex items-center gap-md px-md py-sm rounded-lg bg-primary/10 text-primary font-bold transition-all" href="riwayat-pasien.php">
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
    <div class="ml-[260px] min-h-screen h-screen overflow-hidden flex flex-col pt-16">
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
            <span class="text-body-sm font-medium" id="current-date-display">-</span>
          </div>

          <div class="h-8 w-px bg-outline-variant"></div>

          <!-- Doctor Switcher Dropdown -->
          <div class="relative">
            <button id="profile-menu-button" class="flex items-center gap-3 hover:bg-surface-container-low p-1.5 rounded-lg transition-all outline-none">
              <div class="text-right hidden sm:block">
                <p class="font-title-sm text-title-sm leading-none text-primary"><?= htmlspecialchars($nama_dokter) ?></p>
                <p class="font-label-caps text-[10px] text-on-surface-variant"><?= htmlspecialchars($spesialisasi_dokter) ?></p>
              </div>
              <div class="w-10 h-10 rounded-full border-2 border-primary-container overflow-hidden">
                <img alt="Doctor Profile" class="w-full h-full object-cover" src="<?= $foto_dokter ?>"/>
              </div>
              <span class="material-symbols-outlined text-[16px] text-on-surface-variant">expand_more</span>
            </button>
            
            <!-- Dropdown Menu -->
            <div id="profile-dropdown" class="absolute right-0 mt-2 w-56 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-lg hidden z-50">
              <div class="p-2 border-b border-outline-variant">
                <p class="text-[10px] font-label-caps text-on-surface-variant uppercase px-3 py-1">Ganti Dokter</p>
              </div>
              <div class="p-1 space-y-1">
                <a href="?switch_dokter=1" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface-container-high transition-all <?= $id_dokter_login === 1 ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface' ?>">
                  <div class="w-8 h-8 rounded-full overflow-hidden border border-outline-variant">
                    <img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCedHsWtVogRuLqa7IZRhxpnlVl7bf7oqPlJ13qcZtAxiUNk1IAqcpxkOoiBrEJCLlTtht4Xuw9YBdlwOsfrIcQwfL_I7svWDZ8IlUTm4b5ESA__67dSmEPEfRx7pWseaFDU15utK5kxpc6zqbz3vXpgPvQK-n2x1MAWv02ncy0y5fk3eo8aryvBftAEXZS6Jnt6Ss3tgxuEu4QKQgwaGk_bwP3jslqtZp4-u02z6xuD4PUmDAxGFOUaqX1NDAwnfmzQvSjR9PzNqI" />
                  </div>
                  <div class="text-left">
                    <p class="text-xs font-semibold">dr. Sarah Sp.KK</p>
                    <p class="text-[9px] text-on-surface-variant">Dermatologist</p>
                  </div>
                </a>
                <a href="?switch_dokter=2" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface-container-high transition-all <?= $id_dokter_login === 2 ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface' ?>">
                  <div class="w-8 h-8 rounded-full overflow-hidden border border-outline-variant">
                    <img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCdnqV0hZQQyZHboUD9RM8O6NlzScyVOnO3-7r4g3zMK1pM65aD2aB5KAFOswI-qj41JeKvIqCaqfVVqks0zLotFZDxXSM68CVUHJ4YkkyN6PqO7iaj_H9JvoRQCLWvF6kyLZU_VaGMySI_JJJugcr8ZgDuU0CztzRvLm0av3bG5zXT7Fnl7bc0dUYV1SIwosc1R62DPSJ2KxccXrNHqjDztVUZhkq-Q3arqo247SfGQrguZzYxD9rYbkSTKkBf-rTW811qtgDcugc" />
                  </div>
                  <div class="text-left">
                    <p class="text-xs font-semibold">dr. Adrian</p>
                    <p class="text-[9px] text-on-surface-variant">Aesthetician</p>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content Canvas -->
      <!-- Menambahkan min-h-0 untuk membatasi tinggi agar inner scroll berjalan -->
      <main class="flex-grow overflow-hidden flex flex-col min-h-0">
        <!-- Header & Filters Area -->
        <div class="p-xl pb-0 bg-transparent">
          <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-md">
            <div>
              <nav aria-label="Breadcrumb" class="flex text-body-sm text-on-surface-variant mb-2">
                <span class="hover:text-primary cursor-pointer">Dashboard</span>
                <span class="mx-2">/</span>
                <span class="text-on-surface font-medium">Riwayat Pasien</span>
              </nav>
              <div class="flex items-center gap-md">
                <h3 class="font-display-lg text-display-lg text-on-surface">Riwayat Medis Pasien</h3>
                <span class="px-3 py-0.5 bg-primary/10 text-primary font-bold rounded-full text-xs" id="patient-count">
                  <?= count($patients) ?> Pasien Terdaftar
                </span>
              </div>
            </div>
            
            <div class="flex flex-wrap gap-md w-full lg:w-auto">
              <div class="flex flex-col gap-1">
                <label class="font-label-caps text-label-caps text-on-surface-variant">Tipe Perawatan</label>
                <select class="border border-outline-variant rounded px-3 py-2 bg-surface-container-low text-body-sm focus:ring-primary focus:border-primary text-on-surface" id="filter-treatment">
                  <option value="all">Semua Perawatan</option>
                  <option value="laser">Laser Therapy</option>
                  <option value="facial">Facial Treatment</option>
                  <option value="consult">Konsultasi / Skincare</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Split Layout for Patient List & Detail -->
        <!-- Menambahkan min-h-0 agar flexbox tidak melebihi tinggi layar -->
        <div class="flex-1 flex overflow-hidden min-h-0">
          <!-- Left: Patient List Table -->
          <div class="w-7/12 border-r border-outline-variant dark:border-outline bg-surface-container-lowest overflow-y-auto shadow-sm">
            <table class="w-full text-left border-collapse">
              <thead class="sticky top-0 bg-surface-container-low z-10 border-b border-outline-variant">
                <tr>
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant">PASIEN</th>
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant">ID PASIEN</th>
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant">KUNJUNGAN TERAKHIR</th>
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant">DIAGNOSIS UTAMA</th>
                  <th class="px-lg py-md font-label-caps text-label-caps text-on-surface-variant text-center">AKSI</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/30 dark:divide-outline/30" id="patient-table-body">
                <?php
                if (empty($patients)) :
                ?>
                  <tr>
                    <td colspan="5" class="px-lg py-xl text-center text-on-surface-variant">
                      Belum ada pasien terdaftar di database.
                    </td>
                  </tr>
                <?php else :
                    $first_patient_id = $patients[0]['id_pasien'];
                    $index = 0;
                    foreach ($patients as $patient) :
                        // Inisial avatar
                        $nama_parts = explode(' ', $patient['nama_lengkap']);
                        $inisial = strtoupper(substr($nama_parts[0], 0, 1));
                        if (count($nama_parts) > 1) {
                            $inisial .= strtoupper(substr(end($nama_parts), 0, 1));
                        }
                        
                        // Menentukan tipe kulit demo yang konsisten
                        $skin_type = $skin_types[$patient['id_pasien'] % count($skin_types)];
                        
                        // Cek apakah baris ini aktif (baris pertama)
                        $is_active = ($index === 0);
                        $active_class = $is_active ? 'bg-primary/5 dark:bg-primary-container/10 border-l-4 border-primary dark:border-primary-fixed-dim' : '';
                        
                        // Format Tanggal Kunjungan Terakhir
                        $last_visit = $patient['kunjungan_terakhir'];
                        if ($last_visit !== '-') {
                            $last_visit = date('d M Y', strtotime($last_visit));
                        }
                        
                        // Sejak kapan terdaftar
                        $since_date = date('M Y', strtotime($patient['created_at'] ?? 'now'));
                        
                        $index++;
                ?>
                  <!-- Baris pasien -->
                  <tr class="hover:bg-surface-container dark:hover:bg-surface-container-high transition-colors cursor-pointer patient-row <?= $active_class ?>" 
                      data-name="<?= htmlspecialchars($patient['nama_lengkap']) ?>" 
                      data-id="<?= htmlspecialchars($patient['kode_pasien']) ?>" 
                      data-since="<?= $since_date ?>" 
                      data-allergy="<?= htmlspecialchars($patient['alergi'] ?? 'Tidak Ada') ?>" 
                      data-skin="<?= $skin_type ?>" 
                      data-key="<?= $patient['id_pasien'] ?>">
                    <td class="px-lg py-lg">
                      <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center font-bold text-primary dark:text-primary-fixed flex-shrink-0">
                          <?= $inisial ?>
                        </div>
                        <div>
                          <p class="font-bold text-on-surface dark:text-inverse-on-surface"><?= htmlspecialchars($patient['nama_lengkap']) ?></p>
                          <p class="text-xs text-on-surface-variant"><?= $patient['umur'] ?> Th • <?= htmlspecialchars($patient['jenis_kelamin']) ?></p>
                        </div>
                      </div>
                    </td>
                    <td class="px-lg py-lg text-body-sm font-mono text-on-surface-variant whitespace-nowrap">#<?= htmlspecialchars($patient['kode_pasien']) ?></td>
                    <td class="px-lg py-lg text-body-sm whitespace-nowrap"><?= $last_visit ?></td>
                    <td class="px-lg py-lg">
                      <?php if (!empty($patient['diagnosis_utama'])) : ?>
                        <span class="px-2 py-1 rounded-full bg-primary/10 text-primary dark:text-primary-fixed text-[11px] font-bold uppercase tracking-tight">
                          <?= htmlspecialchars($patient['diagnosis_utama']) ?>
                        </span>
                      <?php else : ?>
                        <span class="text-xs text-on-surface-variant italic">-</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-lg py-lg text-center">
                      <button class="p-2 hover:bg-primary/20 rounded-full text-primary transition-all">
                        <span class="material-symbols-outlined">visibility</span>
                      </button>
                    </td>
                  </tr>
                <?php
                    endforeach;
                endif;
                ?>
              </tbody>
            </table>
          </div>

          <!-- Right: Patient Detail Timeline -->
          <div class="w-5/12 bg-surface-container-low flex flex-col shadow-inner overflow-y-auto border-l border-outline-variant dark:border-outline">
            <?php if (!empty($patients)) : 
                $default_patient = $patients[0];
                $default_skin = $skin_types[$default_patient['id_pasien'] % count($skin_types)];
                $default_since = date('M Y', strtotime($default_patient['created_at'] ?? 'now'));
            ?>
            <!-- Selected Patient Header -->
            <div class="p-lg bg-surface-container-low border-b border-outline-variant dark:border-outline">
              <div class="flex justify-between items-start mb-md">
                <div>
                  <h4 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface" id="detail-name">
                    <?= htmlspecialchars($default_patient['nama_lengkap']) ?>
                  </h4>
                  <p class="text-body-sm text-on-surface-variant" id="detail-meta">
                    ID: #<?= htmlspecialchars($default_patient['kode_pasien']) ?> | Pasien Sejak: <?= $default_since ?>
                  </p>
                </div>
              </div>
            </div>

            <!-- Timeline Section -->
            <div class="p-xl flex-1 relative bg-surface-container-low">
              <h5 class="font-title-sm text-title-sm text-on-surface dark:text-inverse-on-surface mb-lg">Kronologi Rekam Medis</h5>
              
              <?php
              // Lakukan loop timeline untuk SETIAP pasien
              foreach ($patients as $patient) :
                  $pid = $patient['id_pasien'];
                  $records = $patient_timelines[$pid] ?? [];
                  $is_default = ($pid === $first_patient_id);
                  $timeline_hidden_class = $is_default ? '' : 'hidden';
              ?>
              <!-- Timeline Container untuk Pasien ID: <?= $pid ?> -->
              <div id="timeline-<?= $pid ?>" class="patient-timeline <?= $timeline_hidden_class ?> relative border-l-2 border-outline-variant dark:border-outline ml-3 space-y-12">
                <?php
                if (empty($records)) :
                ?>
                  <div class="p-md text-on-surface-variant italic text-sm">
                    Belum ada riwayat pemeriksaan/rekam medis yang tercatat.
                  </div>
                <?php else :
                    $rec_idx = 0;
                    foreach ($records as $record) :
                        $rec_date = date('d M Y', strtotime($record['tanggal_kunjungan']));
                        $is_first_rec = ($rec_idx === 0);
                        $dot_class = $is_first_rec ? 'bg-primary' : 'bg-outline-variant dark:bg-outline';
                        $rec_idx++;
                        
                        // Dapatkan resep obat untuk kunjungan ini
                        $kunj_id = $record['id_kunjungan'];
                        $prescription_items = $prescriptions_by_kunjungan[$kunj_id] ?? [];
                ?>
                  <!-- Timeline Item -->
                  <div class="relative pl-8">
                    <!-- Dot penanda tanggal rekam medis -->
                    <div class="absolute -left-[9px] top-0 w-4 h-4 rounded-full <?= $dot_class ?> border-4 border-white dark:border-surface-container-low"></div>
                    <div class="flex justify-between items-start mb-2">
                      <div>
                        <span class="text-xs font-bold text-primary uppercase tracking-widest"><?= $rec_date ?></span>
                        <h6 class="font-bold text-on-surface dark:text-inverse-on-surface">Pemeriksaan Dokter</h6>
                      </div>
                      <span class="px-3 py-1 bg-primary/10 text-primary rounded-full text-[10px] font-bold">
                        <?= strtoupper($record['status_nama']) ?>
                      </span>
                    </div>
                    <div class="bg-surface-container-lowest p-md border border-outline-variant dark:border-outline rounded-lg shadow-sm">
                      <div class="mb-3">
                        <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1">Dokter Pemeriksa</p>
                        <p class="text-body-sm text-on-surface dark:text-inverse-on-surface"><?= htmlspecialchars($record['nama_dokter']) ?></p>
                      </div>
                      <div class="mb-3">
                        <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1">Anamnesis / Keluhan</p>
                        <p class="text-body-sm text-on-surface dark:text-inverse-on-surface bg-surface-container-low p-2 rounded">
                          <?= htmlspecialchars($record['anamnesis']) ?>
                        </p>
                      </div>
                      <div class="mb-3">
                        <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1">Diagnosa</p>
                        <p class="text-body-sm font-bold text-primary dark:text-primary-fixed uppercase">
                          <?= htmlspecialchars($record['diagnosa']) ?>
                        </p>
                      </div>
                      <?php if (!empty($record['tindakan'])) : ?>
                      <div class="mb-3">
                        <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1">Tindakan</p>
                        <p class="text-body-sm text-on-surface dark:text-inverse-on-surface"><?= htmlspecialchars($record['tindakan']) ?></p>
                      </div>
                      <?php endif; ?>
                      
                      <!-- Menampilkan resep obat jika ada -->
                      <div class="mb-3">
                        <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1">Resep &amp; Aturan Pakai</p>
                        <?php if (!empty($prescription_items)) : ?>
                          <ul class="text-body-sm list-disc ml-4 space-y-1 text-on-surface dark:text-inverse-on-surface font-semibold">
                            <?php foreach ($prescription_items as $item) : ?>
                              <li><?= htmlspecialchars($item['nama_obat']) ?> (<?= $item['jumlah'] ?> pcs) &bull; <span class="italic text-on-surface-variant"><?= htmlspecialchars($item['aturan_pakai']) ?></span></li>
                            <?php endforeach; ?>
                          </ul>
                        <?php else : ?>
                          <p class="text-xs text-on-surface-variant italic">Tidak ada resep obat tertulis</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php
                    endforeach;
                endif;
                ?>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else : ?>
              <div class="p-xl text-center text-on-surface-variant">
                Tidak ada data pasien untuk ditampilkan.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </main>
    </div>

    <!-- FAB for Quick Actions -->
    <a href="input-rekam-medis.php" class="fixed bottom-lg right-lg w-14 h-14 bg-primary text-on-primary rounded-full shadow-lg flex items-center justify-center hover:scale-110 transition-transform active:scale-95 group z-30">
      <span class="material-symbols-outlined text-3xl">add</span>
      <span class="absolute right-full mr-4 bg-on-surface text-surface px-3 py-1 rounded text-xs font-bold opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">Input Rekam Baru</span>
    </a>

    <!-- Micro-interaction & Functionality Scripts -->
    <script>
      // Initialize theme toggling
      setupThemeToggle('theme-toggle', 'dark-icon', 'light-icon');

      // Initialize current date display (Indonesian Locale)
      document.addEventListener('DOMContentLoaded', () => {
        const dateDisplay = document.getElementById('current-date-display');
        if (dateDisplay) {
          const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
          dateDisplay.textContent = new Date().toLocaleDateString('id-ID', options);
        }
      });

      // Profile Dropdown Toggle
      const profileButton = document.getElementById('profile-menu-button');
      const profileDropdown = document.getElementById('profile-dropdown');
      if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', (e) => {
          e.stopPropagation();
          profileDropdown.classList.toggle('hidden');
        });
        document.addEventListener('click', () => {
          profileDropdown.classList.add('hidden');
        });
      }

      document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search-input');
        const filterTreatment = document.getElementById('filter-treatment');
        const patientRows = document.querySelectorAll('.patient-row');

        // Selection details fields
        const detailName = document.getElementById('detail-name');
        const detailMeta = document.getElementById('detail-meta');
        const detailAllergy = document.getElementById('detail-allergy');
        const detailSkin = document.getElementById('detail-skin');
        const timelines = document.querySelectorAll('.patient-timeline');

        // Active row selection dynamic timelines toggle
        patientRows.forEach(row => {
          row.addEventListener('click', function() {
            // Remove active style classes from all rows
            patientRows.forEach(r => r.classList.remove('bg-primary/5', 'dark:bg-primary-container/10', 'border-l-4', 'border-primary', 'dark:border-primary-fixed-dim'));
            
            // Add active style class to this row
            this.classList.add('bg-primary/5', 'dark:bg-primary-container/10', 'border-l-4', 'border-primary', 'dark:border-primary-fixed-dim');

            const name = this.getAttribute('data-name');
            const id = this.getAttribute('data-id');
            const since = this.getAttribute('data-since');
            const allergy = this.getAttribute('data-allergy');
            const skin = this.getAttribute('data-skin');
            const key = this.getAttribute('data-key');

            // Update Selected Info Panel
            if (detailName) detailName.textContent = name;
            if (detailMeta) detailMeta.textContent = `ID: #${id} | Pasien Sejak: ${since}`;
            if (detailAllergy) {
              detailAllergy.textContent = allergy;
              if (allergy === 'Tidak Ada') {
                detailAllergy.className = 'text-body-sm font-bold text-primary dark:text-primary-fixed';
              } else {
                detailAllergy.className = 'text-body-sm font-bold text-secondary dark:text-secondary-fixed-dim';
              }
            }
            if (detailSkin) detailSkin.textContent = skin;

            // Toggle timeline divs
            timelines.forEach(tl => {
              if (tl.id === `timeline-${key}`) {
                tl.classList.remove('hidden');
              } else {
                tl.classList.add('hidden');
              }
            });
          });
        });

        // Search and filter implementation
        function filterPatients() {
          const query = searchInput.value.toLowerCase().trim();
          const selectedTreatment = filterTreatment.value.toLowerCase();

          patientRows.forEach(row => {
            const name = row.getAttribute('data-name').toLowerCase();
            const id = row.getAttribute('data-id').toLowerCase();
            const rowContent = row.innerText.toLowerCase();

            const matchesQuery = query === '' || name.includes(query) || id.includes(query) || rowContent.includes(query);
            
            // Filter Treatment
            let matchesTreatment = true;
            if (selectedTreatment !== 'all') {
              const diagnosisSpan = row.querySelector('td:nth-child(4) span');
              const diagnosis = diagnosisSpan ? diagnosisSpan.innerText.toLowerCase() : '';
              
              if (selectedTreatment === 'facial' && !diagnosis.includes('peel') && !diagnosis.includes('acne')) matchesTreatment = false;
              if (selectedTreatment === 'laser' && !diagnosis.includes('laser') && !diagnosis.includes('hiperpigmentasi') && !diagnosis.includes('melasma')) matchesTreatment = false;
              if (selectedTreatment === 'consult' && !diagnosis.includes('consultation') && !diagnosis.includes('kusam')) matchesTreatment = false;
            }

            if (matchesQuery && matchesTreatment) {
              row.style.display = '';
            } else {
              row.style.display = 'none';
            }
          });
        }

        searchInput.addEventListener('input', filterPatients);
        filterTreatment.addEventListener('change', filterPatients);
      });
    </script>
  </body>
</html>
