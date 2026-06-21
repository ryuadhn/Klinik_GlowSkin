<?php
/**
 * ============================================================
 * FILE: dashboard-dokter/input-rekam-medis.php (sebelumnya .html)
 * DESKRIPSI: Form Input Rekam Medis untuk Dokter GlowSkin.
 * ============================================================
 * File ini menangani:
 * 1. Menampilkan form input rekam medis (GET request).
 *    - Menerima parameter 'id_kunjungan' via GET (?id_kunjungan=X)
 *    - Menampilkan info pasien terkait kunjungan tersebut.
 * 2. Memproses penyimpanan rekam medis (POST request).
 *    - Tangkap data anamnesis dan diagnosa dari form.
 *    - INSERT ke tabel 'rekam_medis'.
 *    - TIDAK perlu UPDATE status kunjungan, karena Trigger MySQL
 *      'trg_rekam_medis_after_insert' sudah otomatis mengubah
 *      status kunjungan menjadi 'Selesai' (id_status=3).
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

// --- VARIABEL UNTUK PESAN FEEDBACK ---
$pesan_sukses = '';
$pesan_error  = '';

/**
 * ============================================================
 * BLOK PHP: AMBIL DATA KUNJUNGAN & PASIEN DARI DATABASE
 * ============================================================
 * Mengambil id_kunjungan dari parameter GET (URL query string).
 * Lalu query informasi kunjungan + nama pasien untuk ditampilkan
 * di header form, agar dokter tahu siapa yang sedang diperiksa.
 */
$id_kunjungan = intval($_GET['id_kunjungan'] ?? 0);
$data_kunjungan = null;

if ($id_kunjungan > 0) {
    try {
        /**
         * Query untuk mengambil detail kunjungan beserta data pasien.
         * JOIN tabel kunjungan dengan tabel pasien menggunakan id_pasien.
         */
        $stmt_info = $pdo->prepare("
            SELECT
                k.id_kunjungan,
                k.keluhan_utama,
                k.no_antrian,
                k.tanggal_kunjungan,
                p.nama_lengkap,
                p.no_telepon
            FROM kunjungan k
            JOIN pasien p ON k.id_pasien = p.id_pasien
            WHERE k.id_kunjungan = :id_kunjungan
        ");
        $stmt_info->execute([':id_kunjungan' => $id_kunjungan]);
        $data_kunjungan = $stmt_info->fetch();
    } catch (PDOException $e) {
        $pesan_error = "Gagal mengambil data kunjungan: " . $e->getMessage();
    }
}

// --- AMBIL DAFTAR OBAT UNTUK DROPDOWN RESEP OBAT ---
try {
    $daftar_obat = $pdo->query("SELECT id_obat, nama_obat, stok FROM obat ORDER BY nama_obat ASC")->fetchAll();
} catch (PDOException $e) {
    $daftar_obat = [];
}

/**
 * ============================================================
 * BLOK LOGIKA PHP: PROSES SIMPAN REKAM MEDIS (POST)
 * ============================================================
 * Alur:
 * 1. Tangkap data dari form (id_kunjungan, anamnesis, diagnosa).
 * 2. INSERT ke tabel 'rekam_medis'.
 * 3. JANGAN buat query UPDATE status kunjungan!
 *    Trigger 'trg_rekam_medis_after_insert' di MySQL Workbench
 *    sudah otomatis mengubah status menjadi Selesai (id_status=3).
 * 4. Simpan resep obat ke tabel 'resep_obat' (jika diisi).
 * 5. Redirect atau tampilkan alert sukses.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- LANGKAH 1: Tangkap data dari form via $_POST ---
        $post_id_kunjungan = intval($_POST['id_kunjungan'] ?? 0);
        $anamnesis         = trim(htmlspecialchars($_POST['anamnesis'] ?? ''));
        $diagnosa          = trim(htmlspecialchars($_POST['diagnosa'] ?? ''));
        
        $id_obat           = intval($_POST['id_obat'] ?? 0);
        $jumlah_obat       = intval($_POST['jumlah_obat'] ?? 0);
        $aturan_pakai      = trim(htmlspecialchars($_POST['aturan_pakai'] ?? ''));

        // --- VALIDASI: Pastikan data penting tidak kosong ---
        if ($post_id_kunjungan <= 0) {
            throw new Exception("ID Kunjungan tidak valid! Pastikan Anda membuka halaman ini dari daftar antrean.");
        }
        if (empty($anamnesis) || empty($diagnosa)) {
            throw new Exception("Anamnesis dan Diagnosa wajib diisi!");
        }

        // --- MULAI TRANSAKSI DATABASE ---
        $pdo->beginTransaction();

        /**
         * --- LANGKAH 2: INSERT DATA REKAM MEDIS KE DATABASE ---
         * Menggunakan Prepared Statement untuk keamanan (anti SQL Injection).
         * Kolom yang diisi:
         * - id_kunjungan : ID kunjungan yang sedang diproses
         * - anamnesis    : Keluhan & riwayat yang dicatat dokter
         * - diagnosa     : Diagnosa dokter (ICD-10 atau deskripsi)
         *
         * CATATAN PENTING:
         * Setelah INSERT ini berhasil, Trigger MySQL
         * 'trg_rekam_medis_after_insert' akan OTOMATIS dijalankan
         * oleh database engine. Trigger tersebut akan:
         *   UPDATE kunjungan SET id_status = 3 WHERE id_kunjungan = NEW.id_kunjungan
         * Jadi kita TIDAK PERLU menulis query UPDATE di PHP!
         */
        $stmt_rekam = $pdo->prepare("
            INSERT INTO rekam_medis (id_kunjungan, anamnesis, diagnosa)
            VALUES (:id_kunjungan, :anamnesis, :diagnosa)
        ");
        $stmt_rekam->execute([
            ':id_kunjungan' => $post_id_kunjungan,
            ':anamnesis'    => $anamnesis,
            ':diagnosa'     => $diagnosa
        ]);

        // --- LANGKAH 2B: PROSES INPUT RESEP OBAT ---
        if ($id_obat > 0 && $jumlah_obat > 0) {
            // Validasi: Cek kecukupan stok obat terlebih dahulu
            $stmt_cek = $pdo->prepare("SELECT stok, harga_jual FROM obat WHERE id_obat = :id");
            $stmt_cek->execute([':id' => $id_obat]);
            $obat_info = $stmt_cek->fetch();
            $stok_sekarang = $obat_info['stok'] ?? 0;
            $harga_satuan = floatval($obat_info['harga_jual'] ?? 0);

            if ($stok_sekarang < $jumlah_obat) {
                throw new Exception("Stok obat tidak mencukupi! Stok saat ini: $stok_sekarang");
            }

            // Hitung subtotal secara dinamis
            $subtotal = $harga_satuan * $jumlah_obat;

            // Simpan resep obat ke tabel resep_obat dengan harga_satuan & subtotal (NOT NULL)
            // Catatan: Setelah ini ter-insert, trigger 'trg_resep_after_insert' di MySQL
            // akan otomatis mengurangi stok obat di tabel obat.
            $stmt_resep = $pdo->prepare("
                INSERT INTO resep_obat (id_kunjungan, id_obat, jumlah, aturan_pakai, harga_satuan, subtotal)
                VALUES (:id_kunjungan, :id_obat, :jumlah, :aturan_pakai, :harga_satuan, :subtotal)
            ");
            $stmt_resep->execute([
                ':id_kunjungan' => $post_id_kunjungan,
                ':id_obat'      => $id_obat,
                ':jumlah'       => $jumlah_obat,
                ':aturan_pakai' => $aturan_pakai,
                ':harga_satuan' => $harga_satuan,
                ':subtotal'     => $subtotal
            ]);
        }

        // --- COMMIT TRANSAKSI ---
        $pdo->commit();

        // --- LANGKAH 3: Set pesan sukses ---
        $pesan_sukses = "Rekam Medis berhasil disimpan!\\nResep obat telah tercatat dan stok obat otomatis terpotong via database Trigger.";

        // Update id_kunjungan untuk refresh data tampilan
        $id_kunjungan = $post_id_kunjungan;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pesan_error = "Kesalahan database: " . $e->getMessage();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pesan_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Input Rekam Medis - GlowSkin Dashboard</title>

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

    <?php
    /**
     * ============================================================
     * BLOK PHP: TAMPILKAN ALERT JAVASCRIPT HASIL PROSES
     * ============================================================
     */
    if (!empty($pesan_sukses)) :
    ?>
    <script>
      alert("✅ <?= $pesan_sukses ?>");
      // Setelah sukses, redirect kembali ke halaman jadwal dokter
      window.location.href = "index.php";
    </script>
    <?php elseif (!empty($pesan_error)) : ?>
    <script>
      alert("❌ Error: <?= addslashes($pesan_error) ?>");
    </script>
    <?php endif; ?>

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
        <a class="flex items-center gap-md px-md py-sm rounded-lg bg-primary/10 text-primary font-bold transition-all" href="input-rekam-medis.php">
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
                <a href="?switch_dokter=1<?= isset($_GET['id_kunjungan']) ? '&id_kunjungan=' . urlencode($_GET['id_kunjungan']) : '' ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface-container-high transition-all <?= $id_dokter_login === 1 ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface' ?>">
                  <div class="w-8 h-8 rounded-full overflow-hidden border border-outline-variant">
                    <img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCedHsWtVogRuLqa7IZRhxpnlVl7bf7oqPlJ13qcZtAxiUNk1IAqcpxkOoiBrEJCLlTtht4Xuw9YBdlwOsfrIcQwfL_I7svWDZ8IlUTm4b5ESA__67dSmEPEfRx7pWseaFDU15utK5kxpc6zqbz3vXpgPvQK-n2x1MAWv02ncy0y5fk3eo8aryvBftAEXZS6Jnt6Ss3tgxuEu4QKQgwaGk_bwP3jslqtZp4-u02z6xuD4PUmDAxGFOUaqX1NDAwnfmzQvSjR9PzNqI" />
                  </div>
                  <div class="text-left">
                    <p class="text-xs font-semibold">dr. Sarah Sp.KK</p>
                    <p class="text-[9px] text-on-surface-variant">Dermatologist</p>
                  </div>
                </a>
                <a href="?switch_dokter=2<?= isset($_GET['id_kunjungan']) ? '&id_kunjungan=' . urlencode($_GET['id_kunjungan']) : '' ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-surface-container-high transition-all <?= $id_dokter_login === 2 ? 'bg-primary/10 text-primary font-bold' : 'text-on-surface' ?>">
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

      <!-- Page Canvas -->
      <main class="mt-16 p-xl flex-1 max-w-[1440px] mx-auto w-full bg-background dark:bg-transparent">
        <!-- Header & Breadcrumb Area -->
        <div class="p-xl pb-0 bg-transparent">
          <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-md">
            <div>
              <nav aria-label="Breadcrumb" class="flex text-body-sm text-on-surface-variant mb-2">
                <span class="hover:text-primary cursor-pointer">Dashboard</span>
                <span class="mx-2">/</span>
                <span class="text-on-surface font-medium">Input Rekam Medis</span>
              </nav>
              <h3 class="font-display-lg text-display-lg text-on-surface">Input Rekam Medis Baru</h3>
            </div>
          </div>
        </div>

        <!-- Content Container -->
        <div class="flex-grow p-xl space-y-xl overflow-y-auto bg-background">
          <div class="max-w-[1200px] mx-auto space-y-lg">

            <!-- Patient Header Card (Data Live dari Database) -->
            <?php
            /**
             * ============================================================
             * BLOK PHP: MENAMPILKAN INFORMASI PASIEN DARI DATABASE
             * ============================================================
             * Jika $data_kunjungan ditemukan (id_kunjungan valid),
             * tampilkan nama pasien, keluhan, dan info kunjungan.
             * Jika tidak ditemukan, tampilkan pesan peringatan.
             */
            if ($data_kunjungan) :
            ?>
            <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg flex items-center justify-between shadow-sm">
              <div class="flex items-center gap-lg">
                <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center text-primary border border-primary/20 flex-shrink-0">
                  <span class="material-symbols-outlined text-4xl">person</span>
                </div>
                <div>
                  <!-- Nama Pasien dari Database -->
                  <h3 class="font-headline-md text-xl text-on-surface mb-xs" id="patient-name-header">
                    <?= htmlspecialchars($data_kunjungan['nama_lengkap']) ?>
                  </h3>
                  <div class="flex items-center gap-md text-on-surface-variant">
                    <span class="font-body-sm text-body-sm">No Antrian: #<?= htmlspecialchars($data_kunjungan['no_antrian']) ?></span>
                    <span class="w-1 h-1 bg-outline-variant rounded-full"></span>
                    <span class="font-body-sm text-body-sm">ID Kunjungan: <?= $id_kunjungan ?></span>
                  </div>
                </div>
              </div>
              <div class="flex flex-col items-end">
                <span class="px-3 py-1 bg-primary/10 dark:bg-primary-container/20 text-primary dark:text-primary-fixed-dim text-xs font-bold rounded-full mb-xs">DALAM KONSULTASI</span>
                <p class="font-label-caps text-[10px] text-on-surface-variant">
                  Tanggal: <?= date('d M Y', strtotime($data_kunjungan['tanggal_kunjungan'])) ?>
                </p>
              </div>
            </section>
            <?php else : ?>
            <!-- Pesan jika id_kunjungan tidak valid atau tidak ditemukan -->
            <section class="bg-error-container border border-error/30 rounded-2xl p-lg shadow-sm">
              <div class="flex items-center gap-md">
                <span class="material-symbols-outlined text-error text-3xl">warning</span>
                <div>
                  <h3 class="font-title-sm text-error">Data Kunjungan Tidak Ditemukan</h3>
                  <p class="text-body-sm text-on-surface-variant">Silakan pilih pasien dari <a href="index.php" class="text-primary font-bold hover:underline">Daftar Antrean</a> terlebih dahulu.</p>
                </div>
              </div>
            </section>
            <?php endif; ?>

            <!-- Input Form Grid -->
            <?php
            /**
             * ============================================================
             * FORM INPUT REKAM MEDIS
             * ============================================================
             * PERUBAHAN UTAMA dari versi HTML:
             * 1. method="POST" → data dikirim ke PHP untuk diproses.
             * 2. Hidden input 'id_kunjungan' → mengirim ID kunjungan
             *    (BUKAN id_pasien) ke blok $_POST di atas.
             * 3. Textarea 'anamnesis' → name="anamnesis"
             * 4. Input 'diagnosa' → name="diagnosa"
             * 5. Semua atribut 'name' disinkronkan dengan $_POST.
             */
            ?>
            <form class="grid grid-cols-12 gap-lg" id="medical-form" method="POST" action="">
              <?php
              /**
               * --- HIDDEN INPUT: ID KUNJUNGAN ---
               * Input tersembunyi ini mengirimkan id_kunjungan ke PHP
               * saat form di-submit. Ini adalah KEY utama untuk mengetahui
               * kunjungan mana yang sedang dibuat rekam medisnya.
               */
              ?>
              <input type="hidden" name="id_kunjungan" value="<?= $id_kunjungan ?>">

              <!-- Left Column: Anamnesis & Vital Signs -->
              <div class="col-span-12 lg:col-span-8 space-y-lg">
                <!-- Anamnesis & Vitals -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg shadow-sm">
                  <h4 class="font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim mb-lg flex items-center gap-sm uppercase">
                    <span class="material-symbols-outlined text-sm">monitor_heart</span>
                    ANAMNESIS & VITAL SIGNS
                  </h4>
                  <div class="space-y-lg">
                    <div>
                      <label class="block font-label-caps text-[10px] text-on-surface-variant mb-xs">ANAMNESIS (KELUHAN & RIWAYAT)</label>
                      <!-- name="anamnesis" → sinkron dengan $_POST['anamnesis'] -->
                      <textarea name="anamnesis" class="w-full bg-surface border border-outline-variant rounded-lg p-md text-body-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-on-surface" placeholder="Deskripsikan keluhan pasien secara detail: riwayat penyakit, alergi, obat yang sedang dikonsumsi..." rows="4" id="complaint-input" required><?= htmlspecialchars($data_kunjungan['keluhan_utama'] ?? '') ?></textarea>
                    </div>
                  </div>
                </div>

                <!-- Diagnosis & Clinical Notes -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg shadow-sm">
                  <h4 class="font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim mb-lg flex items-center gap-sm uppercase">
                    <span class="material-symbols-outlined text-sm">psychiatry</span>
                    DIAGNOSA & CATATAN KLINIS
                  </h4>
                  <div class="space-y-lg">
                    <div>
                      <label class="block font-label-caps text-[10px] text-on-surface-variant mb-xs">DIAGNOSA UTAMA</label>
                      <!-- name="diagnosa" → sinkron dengan $_POST['diagnosa'] -->
                      <div class="relative">
                        <input name="diagnosa" list="diagnosa-list" class="w-full bg-surface border border-outline-variant rounded-lg pl-md pr-10 py-sm text-body-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-on-surface" placeholder="Pilih atau cari diagnosa utama..." type="text" required />
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                        <datalist id="diagnosa-list">
                          <option value="L70.0 Acne Vulgaris (Jerawat / Komedo / Papul)">
                          <option value="L81.1 Chloasma / Melasma (Flek Hitam Pigmentasi)">
                          <option value="L81.4 Post-Inflammatory Hyperpigmentation (Bekas Jerawat Kehitaman)">
                          <option value="L90.5 Acne Scars and Fibrosis (Bopeng / Bekas Luka Jerawat)">
                          <option value="L57.0 Actinic Keratosis (Bercak Kasar Akibat Sinar Matahari)">
                          <option value="L57.8 Photoaging / Skin Aging (Kerutan & Penuaan Dini UV)">
                          <option value="L71.9 Rosacea (Kemerahan & Pelebaran Pembuluh Darah Wajah)">
                          <option value="L30.9 Dermatitis / Eksim (Radang & Iritasi Kulit)">
                        </datalist>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Right Column: Treatments & Prescription -->
              <div class="col-span-12 lg:col-span-4 space-y-lg">
                <!-- Info Kunjungan -->
                <?php if ($data_kunjungan) : ?>
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg shadow-sm">
                  <h4 class="font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim mb-lg flex items-center gap-sm uppercase">
                    <span class="material-symbols-outlined text-sm">info</span>
                    INFO KUNJUNGAN
                  </h4>
                  <div class="space-y-md">
                    <div>
                      <p class="text-[10px] font-label-caps text-on-surface-variant uppercase tracking-wider mb-1">Keluhan Saat Pendaftaran</p>
                      <p class="text-sm font-medium text-on-surface bg-surface-container-low p-2 rounded">
                        <?= htmlspecialchars($data_kunjungan['keluhan_utama'] ?? 'Tidak ada keluhan tercatat') ?>
                      </p>
                    </div>
                    <div>
                      <p class="text-[10px] font-label-caps text-on-surface-variant uppercase tracking-wider mb-1">No Telepon</p>
                      <p class="text-sm font-bold text-primary dark:text-primary-fixed-dim">
                        <?= htmlspecialchars($data_kunjungan['no_telepon'] ?? '-') ?>
                      </p>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <!-- Resep Obat (Opsional) -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-lg shadow-sm">
                  <h4 class="font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim mb-lg flex items-center gap-sm uppercase">
                    <span class="material-symbols-outlined text-sm">prescriptions</span>
                    RESEP OBAT (OPSIONAL)
                  </h4>
                  <div class="space-y-md">
                    <div>
                      <label class="block font-label-caps text-[10px] text-on-surface-variant mb-xs">Pilih Obat</label>
                      <select name="id_obat" class="w-full bg-surface border border-outline-variant rounded-lg p-md text-body-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-on-surface">
                        <option value="0">-- Tidak Ada Resep --</option>
                        <?php foreach ($daftar_obat as $obat) : ?>
                          <option value="<?= $obat['id_obat'] ?>">
                            <?= htmlspecialchars($obat['nama_obat']) ?> (Stok: <?= $obat['stok'] ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="block font-label-caps text-[10px] text-on-surface-variant mb-xs">Jumlah</label>
                      <input type="number" name="jumlah_obat" min="0" value="0" class="w-full bg-surface border border-outline-variant rounded-lg p-md text-body-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-on-surface">
                    </div>
                    <div>
                      <label class="block font-label-caps text-[10px] text-on-surface-variant mb-xs">Aturan Pakai</label>
                      <input type="text" name="aturan_pakai" placeholder="Contoh: 3x1 sehari setelah makan" class="w-full bg-surface border border-outline-variant rounded-lg p-md text-body-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-on-surface">
                    </div>
                  </div>
                </div>

                <!-- Catatan Trigger -->
                <div class="bg-primary/5 border border-primary/20 rounded-2xl p-lg shadow-sm">
                  <div class="flex items-start gap-sm">
                    <span class="material-symbols-outlined text-primary text-lg mt-0.5">auto_fix_high</span>
                    <div>
                      <p class="text-xs font-bold text-primary mb-1">Trigger Otomatis Aktif</p>
                      <p class="text-[11px] text-on-surface-variant leading-relaxed">
                        Setelah rekam medis disimpan, status kunjungan akan <strong>otomatis berubah menjadi "Selesai"</strong> oleh Trigger MySQL <code class="bg-surface px-1 rounded text-[10px]">trg_rekam_medis_after_insert</code>.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Footer Actions -->
              <div class="col-span-12 flex flex-col gap-sm">
                <button class="w-full bg-primary text-on-primary py-md rounded-xl font-title-sm hover:brightness-110 transition-all active:scale-[0.98] flex items-center justify-center gap-md shadow-sm" type="submit" <?= !$data_kunjungan ? 'disabled' : '' ?>>
                  <span class="material-symbols-outlined">save</span>
                  Simpan Rekam Medis
                </button>
                <a href="index.php" class="w-full bg-surface-container-low border border-outline-variant text-on-surface-variant py-md rounded-xl font-title-sm hover:bg-surface-container-high transition-all flex items-center justify-center">
                  Batal & Kembali
                </a>
              </div>
            </form>
          </div>
        </div>
      </main>
    </div>

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
    </script>
  </body>
</html>
