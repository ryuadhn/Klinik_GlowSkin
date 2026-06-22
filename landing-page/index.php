<?php
/**
 * ============================================================
 * FILE: landing-page/index.php (sebelumnya index.html)
 * DESKRIPSI: Landing page publik klinik GlowSkin.
 * ============================================================
 * File ini menangani dua hal:
 * 1. Menampilkan halaman landing page (GET request biasa).
 * 2. Memproses form pendaftaran pasien baru & mencatat kunjungan
 *    online via Stored Procedure (POST request dari form).
 * ============================================================
 */

// --- SERTAKAN FILE KONEKSI DATABASE ---
// require_once memastikan file hanya di-load sekali dan akan
// menghentikan eksekusi jika file tidak ditemukan.
require_once __DIR__ . '/../koneksi.php';

// --- TANGGUNG JAWAB SESSION UNTUK POP-UP SEKALI MUNCUL (PRG PATTERN) ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pesan_sukses = $_SESSION['pesan_sukses'] ?? '';
unset($_SESSION['pesan_sukses']); // Hapus agar tidak muncul kembali saat halaman di-refresh

$pesan_ulasan = $_SESSION['pesan_ulasan'] ?? '';
unset($_SESSION['pesan_ulasan']);

$pesan_error  = '';  // Akan diisi jika terjadi kesalahan

/**
 * ============================================================
 * BLOK LOGIKA PHP: MENANGKAP DATA FORM PENDAFTARAN (POST)
 * ============================================================
 * Blok ini HANYA dieksekusi ketika form dikirim (method POST).
 * Alur logikanya:
 * 1. Tangkap data dari form via $_POST.
 * 2. INSERT data pasien baru ke tabel 'pasien'.
 * 3. Ambil ID pasien yang baru saja dibuat (lastInsertId).
 * 4. Panggil Stored Procedure 'sp_tambah_kunjungan' untuk mencatat
 *    antrean kunjungan online pasien tersebut.
 * 5. Tampilkan alert JavaScript jika sukses atau error.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['form_action'])) {
    try {
        // --- LANGKAH 1: Tangkap data dari form via $_POST ---
        // Fungsi trim() digunakan untuk menghapus spasi di awal/akhir input.
        // Fungsi htmlspecialchars() digunakan untuk mencegah serangan XSS.
        $nama_lengkap   = trim(htmlspecialchars($_POST['nama_lengkap'] ?? ''));
        $no_telepon     = trim(htmlspecialchars($_POST['no_telepon'] ?? ''));
        $tanggal_lahir  = trim(htmlspecialchars($_POST['tanggal_lahir'] ?? ''));
        $id_jenis_kelamin = intval($_POST['id_jenis_kelamin'] ?? 0);
        $keluhan_utama  = trim(htmlspecialchars($_POST['keluhan_utama'] ?? ''));
        $id_dokter      = intval($_POST['id_dokter'] ?? 1); // Default dokter ID 1 jika tidak dipilih
        $id_layanan     = intval($_POST['id_layanan'] ?? 1); // Default layanan ID 1 (Konsultasi) jika tidak dipilih

        // --- VALIDASI SEDERHANA: Pastikan field wajib tidak kosong ---
        if (empty($nama_lengkap) || empty($no_telepon) || empty($tanggal_lahir) || empty($id_jenis_kelamin)) {
            throw new Exception("Nama lengkap, nomor telepon, tanggal lahir, dan jenis kelamin wajib diisi!");
        }

        /**
         * --- LANGKAH 2: GENERATE KODE PASIEN OTOMATIS ---
         * Tabel 'pasien' memiliki kolom 'kode_pasien' yang wajib diisi (NOT NULL).
         * Kolom ini bertipe VARCHAR(15), sehingga panjang kode maksimal adalah 15 karakter.
         * Kita generate kode unik dengan format: PS-YYMMDD-XXXX (14 karakter)
         * Contoh: PS-260617-A3F1
         * - PS         = Prefix untuk Pasien
         * - YYMMDD     = Tanggal hari ini (tahun 2 digit)
         * - XXXX       = 4 karakter random (hex) untuk memastikan keunikan
         */
         $kode_pasien = 'PS-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        /**
         * --- LANGKAH 2B: INSERT DATA PASIEN BARU KE TABEL 'pasien' ---
         * Menggunakan Prepared Statement (:placeholder) untuk keamanan.
         * Prepared Statement mencegah SQL Injection karena data user
         * tidak pernah langsung digabungkan ke query SQL.
         * Kolom 'kode_pasien' kini turut diisi agar tidak error.
         */
        $stmt_pasien = $pdo->prepare("
            INSERT INTO pasien (kode_pasien, nama_lengkap, no_telepon, tanggal_lahir, id_jenis_kelamin)
            VALUES (:kode_pasien, :nama_lengkap, :no_telepon, :tanggal_lahir, :id_jenis_kelamin)
        ");
        $stmt_pasien->execute([
            ':kode_pasien'      => $kode_pasien,
            ':nama_lengkap'     => $nama_lengkap,
            ':no_telepon'       => $no_telepon,
            ':tanggal_lahir'    => $tanggal_lahir,
            ':id_jenis_kelamin' => $id_jenis_kelamin
        ]);

        /**
         * --- LANGKAH 3: AMBIL ID PASIEN YANG BARU SAJA DIBUAT ---
         * Fungsi lastInsertId() mengembalikan nilai AUTO_INCREMENT
         * terakhir yang di-generate oleh INSERT di atas.
         * ID ini akan digunakan untuk memanggil Stored Procedure.
         */
        $id_pasien_baru = $pdo->lastInsertId();

        /**
         * --- LANGKAH 4: PANGGIL STORED PROCEDURE 'sp_tambah_kunjungan' ---
         * Stored Procedure ini sudah dibuat di MySQL Workbench.
         * Parameter:
         * - $id_pasien_baru : ID pasien yang baru didaftarkan
         * - $id_dokter      : ID dokter yang dipilih dari form
         * - 1               : id_staf (default = 1, pendaftaran mandiri online)
         * - $keluhan_utama  : Keluhan kulit yang diisi pasien
         * - @kode           : OUT parameter - kode kunjungan yang di-generate SP
         * - @antrian        : OUT parameter - nomor antrian yang di-generate SP
         */
        $stmt_kunjungan = $pdo->prepare("
            CALL sp_tambah_kunjungan(:id_pasien, :id_dokter, 1, :keluhan, @kode, @antrian)
        ");
        $stmt_kunjungan->execute([
            ':id_pasien' => $id_pasien_baru,
            ':id_dokter' => $id_dokter,
            ':keluhan'   => $keluhan_utama
        ]);

        /**
         * --- LANGKAH 5: AMBIL HASIL OUTPUT DARI STORED PROCEDURE ---
         * Setelah SP dieksekusi, kita bisa mengambil nilai variabel
         * @kode dan @antrian yang di-set oleh SP menggunakan SELECT.
         */
        $result_sp = $pdo->query("SELECT @kode AS kode_kunjungan, @antrian AS nomor_antrian")->fetch();

        // Ambil info layanan (nama & harga) dari database untuk estimasi biaya pada alert
        $id_layanan = intval($_POST['id_layanan'] ?? 1);
        $stmt_lay = $pdo->prepare("SELECT nama_layanan, harga FROM layanan WHERE id_layanan = :id");
        $stmt_lay->execute([':id' => $id_layanan]);
        $lay_info = $stmt_lay->fetch(PDO::FETCH_ASSOC);
        $nama_layanan = $lay_info['nama_layanan'] ?? '-';
        $harga_satuan = $lay_info['harga'] ?? 0;
        $harga_format = "Rp " . number_format($harga_satuan, 0, ',', '.');

        // Simpan pesan sukses yang akan ditampilkan sebagai JavaScript alert
        $kode_kunj = $result_sp['kode_kunjungan'] ?? '-';
        $no_antri  = $result_sp['nomor_antrian'] ?? '-';
        $pesan_sukses_msg = "Pendaftaran Berhasil!\\n\\nDetail Kunjungan:\\n- Kode Kunjungan: {$kode_kunj}\\n- Nomor Antrian Anda: {$no_antri}\\n- Perawatan: {$nama_layanan}\\n- Estimasi Biaya: {$harga_format}";

        /**
         * --- LANGKAH 5B: SINKRONKAN LAYANAN YANG DIPILIH KE detail_layanan ---
         * Query id_kunjungan yang baru saja dibuat, lalu masukkan layanannya ke tabel detail_layanan.
         */
        if (!empty($kode_kunj) && $kode_kunj !== '-') {
            $stmt_get_kunj = $pdo->prepare("SELECT id_kunjungan FROM kunjungan WHERE kode_kunjungan = :kode");
            $stmt_get_kunj->execute([':kode' => $kode_kunj]);
            $id_kunjungan_baru = $stmt_get_kunj->fetchColumn();

            if ($id_kunjungan_baru) {
                // Insert ke detail_layanan menggunakan harga yang sudah diambil sebelumnya
                $stmt_det = $pdo->prepare("
                    INSERT INTO detail_layanan (id_kunjungan, id_layanan, jumlah, harga_satuan, subtotal)
                    VALUES (:id_kunjungan, :id_layanan, 1, :harga, :harga)
                ");
                $stmt_det->execute([
                    ':id_kunjungan' => $id_kunjungan_baru,
                    ':id_layanan'   => $id_layanan,
                    ':harga'        => $harga_satuan
                ]);
            }
        }

        // Simpan pesan sukses di session dan redirect (PRG Pattern) untuk mencegah alert berulang saat refresh
        $_SESSION['pesan_sukses'] = $pesan_sukses_msg;
        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        // Tangkap error database (query gagal, SP error, dll.)
        $pesan_error = "Terjadi kesalahan database: " . $e->getMessage();
    } catch (Exception $e) {
        // Tangkap error validasi umum
        $pesan_error = $e->getMessage();
    }
}

/**
 * ============================================================
 * BLOK LOGIKA PHP: MENANGKAP DATA FORM ULASAN (POST)
 * ============================================================
 * Blok ini dieksekusi ketika form ulasan dikirim (action=kirim_ulasan).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'kirim_ulasan') {
    try {
        $nama_pengulas   = trim(htmlspecialchars($_POST['nama_pengulas'] ?? ''));
        $layanan_review  = trim(htmlspecialchars($_POST['layanan_review'] ?? ''));
        $rating_review   = intval($_POST['rating_review'] ?? 5);
        $isi_ulasan      = trim(htmlspecialchars($_POST['isi_ulasan'] ?? ''));

        if (empty($nama_pengulas) || empty($layanan_review) || empty($isi_ulasan)) {
            throw new Exception("Nama, layanan, dan isi ulasan wajib diisi!");
        }
        if ($rating_review < 1 || $rating_review > 5) {
            $rating_review = 5;
        }

        $stmt_ulasan = $pdo->prepare("
            INSERT INTO ulasan_pasien (nama_pasien, layanan_diambil, rating, isi_ulasan, is_approved)
            VALUES (:nama, :layanan, :rating, :ulasan, TRUE)
        ");
        $stmt_ulasan->execute([
            ':nama'    => $nama_pengulas,
            ':layanan' => $layanan_review,
            ':rating'  => $rating_review,
            ':ulasan'  => $isi_ulasan
        ]);

        $_SESSION['pesan_ulasan'] = 'sukses';
        header("Location: index.php#testimonials");
        exit();

    } catch (PDOException $e) {
        $pesan_error = "Gagal mengirim ulasan: " . $e->getMessage();
    } catch (Exception $e) {
        $pesan_error = $e->getMessage();
    }
}

// --- QUERY DAFTAR LAYANAN DAN DOKTER UNTUK DROPDOWN FORM ---
try {
    $daftar_layanan = $pdo->query("SELECT id_layanan, nama_layanan, id_jenis_layanan FROM layanan WHERE is_active = TRUE ORDER BY id_layanan ASC")->fetchAll();
    $daftar_dokter = $pdo->query("SELECT id_dokter, nama_lengkap FROM dokter WHERE is_active = TRUE ORDER BY id_dokter ASC")->fetchAll();
    
    // Group services into categories
    $js_services = [
        'laser' => [],
        'facial' => [],
        'skincare' => []
    ];
    foreach ($daftar_layanan as $lay) {
        if ($lay['id_jenis_layanan'] == 4) {
            $js_services['laser'][] = ['id' => $lay['id_layanan'], 'name' => $lay['nama_layanan']];
        } elseif ($lay['id_jenis_layanan'] == 2 || $lay['id_jenis_layanan'] == 3) {
            $js_services['facial'][] = ['id' => $lay['id_layanan'], 'name' => $lay['nama_layanan']];
        } elseif ($lay['id_jenis_layanan'] == 1) {
            $js_services['skincare'][] = ['id' => $lay['id_layanan'], 'name' => $lay['nama_layanan']];
        }
    }
} catch (PDOException $e) {
    $daftar_layanan = [];
    $daftar_dokter = [];
    $js_services = ['laser' => [], 'facial' => [], 'skincare' => []];
}

// --- QUERY ULASAN PASIEN DARI DATABASE ---
try {
    $daftar_ulasan = $pdo->query("SELECT * FROM ulasan_pasien WHERE is_approved = TRUE ORDER BY created_at DESC LIMIT 12")->fetchAll();
} catch (PDOException $e) {
    $daftar_ulasan = [];
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>GlowSkin | Premium Aesthetic Clinic</title>

    <!-- Google Fonts & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <!-- Scripts & Styles -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="../assets/js/theme-toggle.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="css/landing.css" />

    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "inverse-on-surface": "var(--on-surface)",
              "inverse-primary": "var(--primary)",
              "primary-container": "var(--primary-container)",
              "inverse-surface": "var(--surface)",
              "on-secondary-fixed": "#40000d",
              "surface-container-low": "var(--surface-container-low)",
              "surface": "var(--surface)",
              "on-background": "var(--on-surface)",
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
              "on-primary": "var(--surface-container-lowest)",
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
              "surface-variant": "var(--on-surface-variant)",
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
  <body class="bg-background dark:bg-inverse-surface text-on-surface dark:text-inverse-on-surface font-body-md transition-colors duration-300">

    <?php
    /**
     * ============================================================
     * BLOK PHP: TAMPILKAN ALERT JAVASCRIPT BERDASARKAN HASIL PROSES
     * ============================================================
     * Jika proses POST berhasil, tampilkan alert "Pendaftaran Berhasil!".
     * Jika gagal, tampilkan alert error.
     * Script ini diletakkan di awal body agar langsung muncul
     * setelah halaman dimuat ulang pasca-submit.
     */
    if (!empty($pesan_sukses)) :
    ?>
    <script>
      alert("<?= $pesan_sukses ?>");
    </script>
    <?php elseif (!empty($pesan_error)) : ?>
    <script>
      alert("❌ Error: <?= addslashes($pesan_error) ?>");
    </script>
    <?php endif; ?>

    <!-- Header / Navigation -->
    <header class="w-full h-20 sticky top-0 z-50 bg-surface-container-lowest/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800/60">
      <div class="max-w-7xl mx-auto px-lg h-full flex justify-between items-center">
        <div class="flex items-center gap-sm">
          <span class="material-symbols-outlined text-primary text-3xl" style="font-variation-settings: 'FILL' 1;">spa</span>
          <span class="font-headline-md text-headline-md font-extrabold text-primary dark:text-primary-fixed-dim tracking-tight">GlowSkin</span>
        </div>
        <nav class="hidden md:flex items-center gap-lg">
          <a class="font-title-sm text-title-sm text-primary font-bold" href="#">Beranda</a>
          <a class="font-title-sm text-title-sm text-on-surface-variant dark:text-surface-variant hover:text-primary transition-colors" href="#services">Layanan</a>
          <a class="font-title-sm text-title-sm text-on-surface-variant dark:text-surface-variant hover:text-primary transition-colors" href="#doctors">Dokter</a>
          <a class="font-title-sm text-title-sm text-on-surface-variant dark:text-surface-variant hover:text-primary transition-colors" href="#testimonials">Ulasan</a>
          <a class="font-title-sm text-title-sm text-on-surface-variant dark:text-surface-variant hover:text-primary transition-colors" href="#faq">FAQ</a>
          <a class="font-title-sm text-title-sm text-on-surface-variant dark:text-surface-variant hover:text-primary transition-colors" href="#reservation">Reservasi</a>

          <a class="font-title-sm text-title-sm text-on-surface-variant dark:text-surface-variant hover:text-primary transition-colors" href="../login.html">Portal Staf</a>
        </nav>
        <div class="flex items-center gap-md">
          <button class="p-sm rounded-full hover:bg-surface-container-high dark:hover:bg-surface-variant transition-all text-on-surface-variant dark:text-surface-variant" id="theme-toggle">
            <span class="material-symbols-outlined hidden" id="dark-icon">light_mode</span>
            <span class="material-symbols-outlined" id="light-icon">dark_mode</span>
          </button>
          <a class="hidden sm:block bg-primary text-on-primary px-lg py-sm rounded-lg font-title-sm text-title-sm hover:bg-primary-container transition-all active:scale-95" href="#reservation">
            Reservasi
          </a>
        </div>
      </div>
    </header>

    <main>
      <!-- Hero Section -->
      <section class="relative min-h-[780px] flex items-center overflow-hidden">
        <!-- Hero Background Image (Skincare Treatment) - Aligned to the right -->
        <div class="absolute inset-y-0 right-0 w-full lg:w-[60%] z-0 pointer-events-none">
          <img class="w-full h-full object-cover opacity-50 dark:opacity-35 lg:opacity-100 lg:dark:opacity-80 transition-all duration-700" alt="Background perawatan premium" src="../assets/images/skincare_treatment.png" />
          <!-- Responsive Overlay to fade the left edge of the image on desktop -->
          <div class="absolute inset-0 bg-gradient-to-b from-background via-background/90 to-transparent/30 dark:from-inverse-surface dark:via-inverse-surface/90 lg:bg-gradient-to-r lg:from-background lg:via-background/35 lg:to-transparent lg:dark:from-inverse-surface lg:dark:via-inverse-surface/35 lg:dark:to-transparent"></div>
        </div>

        <!-- Background Decoration Blur (additional color accents) -->
        <div class="absolute top-0 right-0 w-full h-full opacity-20 dark:opacity-10 pointer-events-none z-[1]">
          <div class="absolute top-[-10%] right-[-10%] w-[600px] h-[600px] rounded-full bg-primary-container blur-[120px] bg-decoration-blur"></div>
          <div class="absolute bottom-[20%] left-0 w-[400px] h-[400px] rounded-full bg-secondary-container blur-[100px] bg-decoration-blur"></div>
        </div>

        <div class="max-w-7xl mx-auto px-xl w-full grid grid-cols-1 lg:grid-cols-2 gap-xl items-center relative z-10">
          <div class="space-y-lg animate-fade-in-up">
            <div class="inline-flex items-center gap-xs px-md py-1 rounded-full bg-primary/10 border border-primary/20 text-primary font-label-caps text-label-caps">
              <span class="material-symbols-outlined text-[16px]">verified</span>
              ESTHETIC MEDICAL EXCELLENCE
            </div>
            <h1 class="font-display-lg text-display-lg text-on-surface dark:text-inverse-on-surface leading-[1.1] font-extrabold">
              Pancarkan Pesona Kulit <span class="text-primary">Sehat</span> dan Bersinar Alami.
            </h1>
            <p class="font-body-md text-body-md text-on-surface-variant dark:text-surface-variant max-w-xl">
              Komitmen kami adalah memberikan perawatan estetika premium dengan standar medis tertinggi. Dapatkan hasil yang natural dan memukau melalui sentuhan profesional dokter spesialis kami.
            </p>
            <div class="flex flex-col sm:flex-row gap-md pt-md">
              <a class="bg-primary text-on-primary px-xl py-md rounded-lg font-title-sm text-title-sm hover:bg-primary-container transition-all text-center flex items-center justify-center gap-sm group" href="#reservation">
                Buat Janji Temu Sekarang
                <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
              </a>
              <a class="border border-outline text-on-surface-variant dark:text-surface-variant px-xl py-md rounded-lg font-title-sm text-title-sm hover:bg-surface-container-low transition-all text-center" href="#services">
                Lihat Layanan
              </a>
            </div>
          </div>
          
          <!-- Right side is kept empty for the background image to show clearly on large screens -->
          <div class="hidden lg:block"></div>
        </div>
      </section>

      <!-- Services Section -->
      <section class="py-xl bg-surface-container-lowest dark:bg-inverse-surface/50" id="services">
        <div class="max-w-7xl mx-auto px-xl">
          <div class="text-center mb-xl">
            <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface mb-sm">Layanan Perawatan Kami</h2>
            <p class="font-body-md text-body-md text-on-surface-variant dark:text-surface-variant max-w-2xl mx-auto">
              Kami menghadirkan teknologi medis tercanggih untuk mengatasi berbagai permasalahan kulit Anda dengan aman dan efektif.
            </p>
          </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-lg">
              <!-- Service 1 -->
              <div class="bg-surface dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800/60 rounded-2xl transition-all group flex flex-col h-full hover-scale overflow-hidden shadow-sm hover:shadow-md dark:hover:shadow-[0_0_30px_rgba(79,219,200,0.08)] hover:border-teal-700/30 dark:hover:border-teal-400/30">
                <!-- Service Image - scaled slightly to crop thin white borders -->
                <div class="w-full aspect-[16/10] overflow-hidden bg-surface-container-low dark:bg-surface-container-high">
                  <img class="w-full h-full object-cover scale-[1.04] transition-transform duration-700 group-hover:scale-[1.10]" alt="Terapi Laser di Klinik GlowSkin" src="../assets/images/service_laser.png" />
                </div>
                <!-- Service Content -->
                <div class="p-lg flex flex-col flex-grow">
                  <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-md group-hover:bg-primary group-hover:text-on-primary transition-all">
                    <span class="material-symbols-outlined text-[24px]">earbuds</span>
                  </div>
                  <h3 class="font-title-sm text-title-sm mb-sm text-on-surface dark:text-inverse-on-surface font-extrabold">Laser Therapy</h3>
                  <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant mb-lg flex-grow">
                    Teknologi laser mutakhir untuk mengatasi hiperpigmentasi, bekas jerawat, dan peremajaan kulit secara presisi tanpa downtime yang lama.
                  </p>
                  <div class="btn-learn-more flex items-center gap-xs text-primary font-title-sm cursor-pointer group/link" data-service="laser">
                    Pelajari Selengkapnya
                    <span class="material-symbols-outlined text-[18px] group-hover/link:translate-x-1 transition-transform">arrow_forward</span>
                  </div>
                </div>
              </div>

              <!-- Service 2 -->
              <div class="bg-surface dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800/60 rounded-2xl transition-all group flex flex-col h-full hover-scale overflow-hidden shadow-sm hover:shadow-md dark:hover:shadow-[0_0_30px_rgba(79,219,200,0.08)] hover:border-teal-700/30 dark:hover:border-teal-400/30">
                <!-- Service Image - scaled slightly to crop thin white borders -->
                <div class="w-full aspect-[16/10] overflow-hidden bg-surface-container-low dark:bg-surface-container-high">
                  <img class="w-full h-full object-cover scale-[1.04] transition-transform duration-700 group-hover:scale-[1.10]" alt="Perawatan Facial di Klinik GlowSkin" src="../assets/images/service_facial.png" />
                </div>
                <!-- Service Content -->
                <div class="p-lg flex flex-col flex-grow">
                  <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-md group-hover:bg-primary group-hover:text-on-primary transition-all">
                    <span class="material-symbols-outlined text-[24px]">face_6</span>
                  </div>
                  <h3 class="font-title-sm text-title-sm mb-sm text-on-surface dark:text-inverse-on-surface font-extrabold">Facial Treatment</h3>
                  <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant mb-lg flex-grow">
                    Perawatan wajah intensif yang disesuaikan dengan kebutuhan kulit Anda, mulai dari deep cleansing hingga hidrasi maksimal.
                  </p>
                  <div class="btn-learn-more flex items-center gap-xs text-primary font-title-sm cursor-pointer group/link" data-service="facial">
                    Pelajari Selengkapnya
                    <span class="material-symbols-outlined text-[18px] group-hover/link:translate-x-1 transition-transform">arrow_forward</span>
                  </div>
                </div>
              </div>

              <!-- Service 3 -->
              <div class="bg-surface dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800/60 rounded-2xl transition-all group flex flex-col h-full hover-scale overflow-hidden shadow-sm hover:shadow-md dark:hover:shadow-[0_0_30px_rgba(79,219,200,0.08)] hover:border-teal-700/30 dark:hover:border-teal-400/30">
                <!-- Service Image - scaled slightly to crop thin white borders -->
                <div class="w-full aspect-[16/10] overflow-hidden bg-surface-container-low dark:bg-surface-container-high">
                  <img class="w-full h-full object-cover scale-[1.04] transition-transform duration-700 group-hover:scale-[1.10]" alt="Skincare Racikan Spesialis di Klinik GlowSkin" src="../assets/images/service_skincare.png" />
                </div>
                <!-- Service Content -->
                <div class="p-lg flex flex-col flex-grow">
                  <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-md group-hover:bg-primary group-hover:text-on-primary transition-all">
                    <span class="material-symbols-outlined text-[24px]">clinical_notes</span>
                  </div>
                  <h3 class="font-title-sm text-title-sm mb-sm text-on-surface dark:text-inverse-on-surface font-extrabold">Skincare Racikan</h3>
                  <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant mb-lg flex-grow">
                    Konsultasi mendalam dengan dokter spesialis untuk mendapatkan formulasi skincare yang dirancang khusus untuk profil kulit unik Anda.
                  </p>
                  <div class="btn-learn-more flex items-center gap-xs text-primary font-title-sm cursor-pointer group/link" data-service="skincare">
                    Pelajari Selengkapnya
                    <span class="material-symbols-outlined text-[18px] group-hover/link:translate-x-1 transition-transform">arrow_forward</span>
                  </div>
                </div>
              </div>
            </div>
        </div>
      </section>

      <!-- Doctors Section -->
      <section class="py-xl bg-surface-container-lowest dark:bg-inverse-surface/5" id="doctors">
        <div class="max-w-7xl mx-auto px-xl">
          <!-- Centered Header -->
          <div class="text-center mb-xl">
            <span class="inline-flex items-center gap-xs px-md py-1 rounded-full bg-primary/10 border border-primary/20 text-primary font-label-caps text-label-caps mb-xs">
              <span class="material-symbols-outlined text-[16px]">add</span>
              DOKTER KAMI
            </span>
            <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface mb-sm">Tim Dokter Spesialis Medis</h2>
            <p class="font-body-md text-body-md text-on-surface-variant dark:text-surface-variant max-w-2xl mx-auto">
              Kesehatan kulit Anda ditangani oleh dokter spesialis kulit berpengalaman dan bersertifikasi internasional.
            </p>
          </div>

          <!-- Doctors Grid Layout (Centered for 2 doctors, shrunken container) -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-md max-w-xl mx-auto justify-center">
            <!-- Doctor Card 1: dr. Sarah -->
            <div class="bg-surface dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800/60 rounded-3xl p-3 flex flex-col justify-between shadow-md hover:shadow-lg dark:hover:shadow-[0_0_35px_rgba(79,219,200,0.15)] transition-all duration-300 relative overflow-hidden group hover:border-teal-700/30 dark:hover:border-teal-400/30">
              <div class="relative rounded-2xl overflow-hidden aspect-square bg-surface-container-low dark:bg-surface-container-high mb-md">
                <img class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" alt="dr. Sarah, Sp.KK - Spesialis Kulit & Kelamin di Klinik GlowSkin" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCedHsWtVogRuLqa7IZRhxpnlVl7bf7oqPlJ13qcZtAxiUNk1IAqcpxkOoiBrEJCLlTtht4Xuw9YBdlwOsfrIcQwfL_I7svWDZ8IlUTm4b5ESA__67dSmEPEfRx7pWseaFDU15utK5kxpc6zqbz3vXpgPvQK-n2x1MAWv02ncy0y5fk3eo8aryvBftAEXZS6Jnt6Ss3tgxuEu4QKQgwaGk_bwP3jslqtZp4-u02z6xuD4PUmDAxGFOUaqX1NDAwnfmzQvSjR9PzNqI" />
                
                <!-- Hover Action Arrow Button -->
                <div class="absolute top-sm right-sm w-10 h-10 rounded-full bg-on-surface text-surface dark:bg-inverse-on-surface dark:text-inverse-surface flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 shadow-lg">
                  <span class="material-symbols-outlined text-[20px]">arrow_outward</span>
                </div>
              </div>
              <div class="px-xs pb-xs">
                <!-- Specialization Badge: Brand Green -->
                <span class="inline-block px-md py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-bold mb-sm">
                  Spesialis Kulit &amp; Kelamin
                </span>
                <h3 class="font-title-sm text-title-sm text-on-surface dark:text-inverse-on-surface font-extrabold mb-1">
                  dr. Sarah, Sp.KK
                </h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant">
                  Anti-Aging &amp; Skin Expert
                </p>
              </div>
            </div>

            <!-- Doctor Card 2: dr. Adrian -->
            <div class="bg-surface dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800/60 rounded-3xl p-3 flex flex-col justify-between shadow-md hover:shadow-lg dark:hover:shadow-[0_0_35px_rgba(79,219,200,0.15)] transition-all duration-300 relative overflow-hidden group hover:border-teal-700/30 dark:hover:border-teal-400/30">
              <div class="relative rounded-2xl overflow-hidden aspect-square bg-surface-container-low dark:bg-surface-container-high mb-md">
                <img class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" alt="dr. Adrian, S.Ked - Dokter Estetika Medis di Klinik GlowSkin" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCdnqV0hZQQyZHboUD9RM8O6NlzScyVOnO3-7r4g3zMK1pM65aD2aB5KAFOswI-qj41JeKvIqCaqfVVqks0zLotFZDxXSM68CVUHJ4YkkyN6PqO7iaj_H9JvoRQCLWvF6kyLZU_VaGMySI_JJJugcr8ZgDuU0CztzRvLm0av3bG5zXT7Fnl7bc0dUYV1SIwosc1R62DPSJ2KxccXrNHqjDztVUZhkq-Q3arqo247SfGQrguZzYxD9rYbkSTKkBf-rTW811qtgDcugc" />
                
                <!-- Hover Action Arrow Button -->
                <div class="absolute top-sm right-sm w-10 h-10 rounded-full bg-on-surface text-surface dark:bg-inverse-on-surface dark:text-inverse-surface flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 shadow-lg">
                  <span class="material-symbols-outlined text-[20px]">arrow_outward</span>
                </div>
              </div>
              <div class="px-xs pb-xs">
                <!-- Specialization Badge: Brand Green -->
                <span class="inline-block px-md py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-bold mb-sm">
                  Dokter Estetika Medis
                </span>
                <h3 class="font-title-sm text-title-sm text-on-surface dark:text-inverse-on-surface font-extrabold mb-1">
                  dr. Adrian, S.Ked
                </h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant">
                  Aesthetic &amp; Laser Specialist
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Testimonials Section -->
      <section class="py-xl bg-surface-container-lowest dark:bg-inverse-surface/30" id="testimonials">
        <div class="max-w-7xl mx-auto px-xl">
          <div class="text-center mb-xl">
            <span class="px-md py-1 rounded-full bg-primary/10 border border-primary/20 text-primary font-label-caps text-label-caps inline-block mb-xs">TESTIMONIALS</span>
            <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface mb-sm">Ulasan Pasien Setia</h2>
            <p class="font-body-md text-body-md text-on-surface-variant dark:text-surface-variant max-w-2xl mx-auto">
              Lebih dari sekadar kecantikan, kepuasan dan rasa percaya diri pasien adalah pencapaian terbesar kami.
            </p>
          </div>

          <?php if (!empty($pesan_ulasan) && $pesan_ulasan === 'sukses') : ?>
          <div class="mb-lg p-md bg-primary/10 border border-primary/30 rounded-xl flex items-center gap-md animate-fade-in-up">
            <span class="material-symbols-outlined text-primary text-2xl">check_circle</span>
            <p class="font-body-md text-on-surface dark:text-inverse-on-surface">Terima kasih! Ulasan Anda berhasil dikirim. 🎉</p>
          </div>
          <?php endif; ?>

                    <!-- Ulasan dari Database -->
          <div class="relative group/testi max-w-7xl mx-auto px-4 md:px-8">
            <!-- Left and Right Nav Buttons -->
            <button type="button" id="prev-testi" class="absolute -left-2 md:-left-4 top-1/2 -translate-y-1/2 w-10 h-10 md:w-12 md:h-12 rounded-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline text-primary hover:bg-primary hover:text-on-primary shadow-lg flex items-center justify-center transition-all z-10 opacity-0 group-hover/testi:opacity-100 focus:opacity-100 focus-visible:opacity-100" aria-label="Sebelumnya">
              <span class="material-symbols-outlined text-[20px] md:text-[24px]">chevron_left</span>
            </button>
            <button type="button" id="next-testi" class="absolute -right-2 md:-right-4 top-1/2 -translate-y-1/2 w-10 h-10 md:w-12 md:h-12 rounded-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline text-primary hover:bg-primary hover:text-on-primary shadow-lg flex items-center justify-center transition-all z-10 opacity-0 group-hover/testi:opacity-100 focus:opacity-100 focus-visible:opacity-100" aria-label="Selanjutnya">
              <span class="material-symbols-outlined text-[20px] md:text-[24px]">chevron_right</span>
            </button>

            <!-- Testimonials Slider Container -->
            <div id="testi-slider" class="flex overflow-x-auto snap-x snap-mandatory scroll-smooth gap-md md:gap-lg pb-md select-none [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
              <?php if (!empty($daftar_ulasan)) : ?>
                <?php foreach ($daftar_ulasan as $ulasan) : ?>
                <div class="min-w-full md:min-w-[calc(50%-12px)] lg:min-w-[calc(33.333%-16px)] snap-start bg-surface dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800/60 p-md rounded-2xl flex flex-col justify-between hover-scale shadow-sm dark:shadow-md dark:shadow-black/20 hover:border-teal-700/30 dark:hover:border-teal-400/30 dark:hover:shadow-[0_0_25px_rgba(79,219,200,0.06)] transition-all">
                  <div class="space-y-sm">
                    <div class="flex text-amber-400">
                      <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <?php if ($i <= $ulasan['rating']) : ?>
                          <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">star</span>
                        <?php else : ?>
                          <span class="material-symbols-outlined text-[20px] text-on-surface-variant/20">star</span>
                        <?php endif; ?>
                      <?php endfor; ?>
                    </div>
                    <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant italic leading-relaxed">
                      "<?= htmlspecialchars($ulasan['isi_ulasan']) ?>"
                    </p>
                  </div>
                  <div class="flex items-center gap-sm pt-md mt-md border-t border-slate-200/80 dark:border-slate-800/60">
                    <div class="w-10 h-10 rounded-full overflow-hidden border border-primary/30 bg-primary/10 flex items-center justify-center text-primary">
                      <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">person</span>
                    </div>
                    <div>
                      <h4 class="font-title-sm text-body-sm text-on-surface dark:text-inverse-on-surface font-bold"><?= htmlspecialchars($ulasan['nama_pasien']) ?></h4>
                      <p class="font-body-xs text-[11px] text-on-surface-variant dark:text-surface-variant">Pasien <?= htmlspecialchars($ulasan['layanan_diambil']) ?></p>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php else : ?>
                <div class="w-full text-center py-xl">
                  <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 mb-md block">reviews</span>
                  <p class="font-body-md text-on-surface-variant">Belum ada ulasan. Jadilah yang pertama memberikan ulasan!</p>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Toggle Review Form Button -->
          <div class="text-center mt-lg">
            <button type="button" id="toggle-review-form" class="inline-flex items-center gap-sm bg-primary/10 border border-teal-700/20 dark:border-teal-400/20 text-primary px-xl py-sm rounded-lg font-title-sm text-title-sm hover:bg-primary hover:text-on-primary transition-all active:scale-95 duration-300">
              <span class="material-symbols-outlined text-[20px]">rate_review</span>
              Tulis Ulasan Baru
            </button>
          </div>

          <!-- Form Kirim Ulasan (Hidden by default, smooth slide animation) -->
          <div id="review-form-wrapper" class="max-h-0 overflow-hidden opacity-0 transition-all duration-500 ease-in-out">
            <div class="mt-lg bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-2xl p-lg">
              <div class="flex items-center gap-md mb-lg">
                <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                  <span class="material-symbols-outlined">rate_review</span>
                </div>
                <div>
                  <h3 class="font-title-sm text-title-sm text-on-surface dark:text-inverse-on-surface">Bagikan Pengalaman Anda</h3>
                  <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant">Ulasan Anda membantu pasien lain dalam memilih perawatan yang tepat.</p>
                </div>
              </div>

              <form id="review-form" method="POST" action="#testimonials" class="space-y-md">
                <input type="hidden" name="form_action" value="kirim_ulasan" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                  <div class="space-y-xs">
                    <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">NAMA ANDA</label>
                    <input name="nama_pengulas" class="w-full bg-surface-container-lowest dark:bg-inverse-surface/50 border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" placeholder="Masukkan nama Anda" required type="text" />
                  </div>
                  <div class="space-y-xs">
                    <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">LAYANAN YANG DIAMBIL</label>
                    <select name="layanan_review" class="w-full bg-surface-container-lowest dark:bg-inverse-surface/50 border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" required>
                      <option disabled selected value="">Pilih layanan</option>
                      <option value="Laser Therapy">Laser Therapy</option>
                      <option value="Facial Treatment">Facial Treatment</option>
                      <option value="Skincare Racikan">Skincare Racikan</option>
                    </select>
                  </div>
                </div>

                <div class="space-y-xs">
                  <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">RATING</label>
                  <div class="flex gap-xs items-center" id="star-rating-input">
                    <?php for ($s = 1; $s <= 5; $s++) : ?>
                    <button type="button" class="star-btn text-on-surface-variant/30 hover:text-amber-400 transition-colors" data-value="<?= $s ?>">
                      <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">star</span>
                    </button>
                    <?php endfor; ?>
                    <input type="hidden" name="rating_review" id="rating-value" value="5" />
                    <span id="rating-text" class="ml-sm font-body-sm text-on-surface-variant dark:text-surface-variant">5 / 5</span>
                  </div>
                </div>

                <div class="space-y-xs">
                  <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">ISI ULASAN</label>
                  <textarea name="isi_ulasan" class="w-full bg-surface-container-lowest dark:bg-inverse-surface/50 border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" placeholder="Ceritakan pengalaman perawatan Anda di GlowSkin..." rows="3" required></textarea>
                </div>

                <button type="submit" class="bg-primary text-on-primary px-xl py-sm rounded-lg font-title-sm text-title-sm hover:bg-primary-container transition-all active:scale-95 flex items-center gap-sm">
                  <span class="material-symbols-outlined text-[20px]">send</span>
                  Kirim Ulasan
                </button>
              </form>
            </div>
          </div>

          <!-- JavaScript to Toggle the review form container with a smooth slide animation -->
          <script>
            document.getElementById('toggle-review-form').addEventListener('click', function() {
              const wrapper = document.getElementById('review-form-wrapper');
              const isCollapsed = wrapper.classList.contains('opacity-0');
              
              if (isCollapsed) {
                // Expand
                wrapper.classList.remove('opacity-0');
                wrapper.style.maxHeight = wrapper.scrollHeight + 'px';
                wrapper.style.opacity = '1';
                this.innerHTML = `
                  <span class="material-symbols-outlined text-[20px]">close</span>
                  Batal Menulis Ulasan
                `;
                // Change button style to neutral outline
                this.classList.remove('bg-primary/10', 'text-primary', 'border-teal-700/20', 'dark:border-teal-400/20');
                this.classList.add('bg-surface-container-high', 'text-on-surface-variant', 'border-slate-300', 'dark:border-slate-700');
              } else {
                // Collapse
                wrapper.style.maxHeight = '0px';
                wrapper.style.opacity = '0';
                // Wait for the transition to finish before adding class back
                setTimeout(() => {
                  wrapper.classList.add('opacity-0');
                }, 500);
                this.innerHTML = `
                  <span class="material-symbols-outlined text-[20px]">rate_review</span>
                  Tulis Ulasan Baru
                `;
                // Change button style back to primary green
                this.classList.remove('bg-surface-container-high', 'text-on-surface-variant', 'border-slate-300', 'dark:border-slate-700');
                this.classList.add('bg-primary/10', 'text-primary', 'border-teal-700/20', 'dark:border-teal-400/20');
              }
            });
          </script>
        </div>
      </section>

      <!-- FAQ Section -->
      <section class="py-xl bg-surface dark:bg-inverse-surface/10" id="faq">
        <div class="max-w-3xl mx-auto px-xl">
          <div class="text-center mb-xl">
            <span class="px-md py-1 rounded-full bg-primary/10 border border-primary/20 text-primary font-label-caps text-label-caps inline-block mb-xs">FAQ</span>
            <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface mb-sm">Pertanyaan Populer</h2>
            <p class="font-body-md text-body-md text-on-surface-variant dark:text-surface-variant max-w-2xl mx-auto">
              Temukan jawaban cepat untuk pertanyaan-pertanyaan yang sering diajukan mengenai layanan kami.
            </p>
          </div>

          <div class="space-y-md">
            <!-- FAQ 1 -->
            <details name="faq" class="group bg-surface-container-low dark:bg-surface-container-low/50 border border-outline-variant dark:border-outline rounded-2xl p-md [&_summary::-webkit-details-marker]:hidden transition-all duration-300">
              <summary class="flex justify-between items-center font-body-md text-body-md text-on-surface dark:text-inverse-on-surface font-bold cursor-pointer outline-none">
                <span>Berapa lama waktu yang dibutuhkan untuk melihat hasil perawatan?</span>
                <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180 text-primary">expand_more</span>
              </summary>
              <div class="mt-sm text-body-sm text-on-surface-variant dark:text-surface-variant leading-relaxed border-t border-slate-200/80 dark:border-slate-800/60 pt-sm">
                Hasil bervariasi bergantung pada kondisi kulit individu dan jenis perawatan. Namun, untuk perawatan dasar seperti Facial Treatment, kesegaran instan dapat dirasakan langsung setelah sesi selesai. Untuk terapi laser, hasil optimal terlihat dalam 2-4 minggu setelah tindakan.
              </div>
            </details>

            <!-- FAQ 2 -->
            <details name="faq" class="group bg-surface-container-low dark:bg-surface-container-low/50 border border-outline-variant dark:border-outline rounded-2xl p-md [&_summary::-webkit-details-marker]:hidden transition-all duration-300">
              <summary class="flex justify-between items-center font-body-md text-body-md text-on-surface dark:text-inverse-on-surface font-bold cursor-pointer outline-none">
                <span>Apakah krim racikan dokter GlowSkin aman dan bersertifikat BPOM?</span>
                <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180 text-primary">expand_more</span>
              </summary>
              <div class="mt-sm text-body-sm text-on-surface-variant dark:text-surface-variant leading-relaxed border-t border-slate-200/80 dark:border-slate-800/60 pt-sm">
                Ya, semua bahan dasar krim dan produk kami bersertifikat BPOM dan diracik secara personal berdasarkan resep khusus dokter spesialis kulit kami agar sesuai dengan profil kulit unik Anda secara aman dan terkontrol.
              </div>
            </details>

            <!-- FAQ 3 -->
            <details name="faq" class="group bg-surface-container-low dark:bg-surface-container-low/50 border border-outline-variant dark:border-outline rounded-2xl p-md [&_summary::-webkit-details-marker]:hidden transition-all duration-300">
              <summary class="flex justify-between items-center font-body-md text-body-md text-on-surface dark:text-inverse-on-surface font-bold cursor-pointer outline-none">
                <span>Bagaimana cara melakukan pembatalan atau perubahan jadwal reservasi?</span>
                <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180 text-primary">expand_more</span>
              </summary>
              <div class="mt-sm text-body-sm text-on-surface-variant dark:text-surface-variant leading-relaxed border-t border-slate-200/80 dark:border-slate-800/60 pt-sm">
                Anda dapat menghubungi kami langsung melalui WhatsApp konfirmasi yang Anda terima setelah melakukan pendaftaran online, minimal 24 jam sebelum jadwal konsultasi dimulai.
              </div>
            </details>

            <!-- FAQ 4 -->
            <details name="faq" class="group bg-surface-container-low dark:bg-surface-container-low/50 border border-outline-variant dark:border-outline rounded-2xl p-md [&_summary::-webkit-details-marker]:hidden transition-all duration-300">
              <summary class="flex justify-between items-center font-body-md text-body-md text-on-surface dark:text-inverse-on-surface font-bold cursor-pointer outline-none">
                <span>Apakah perawatan medis di GlowSkin terasa sakit?</span>
                <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180 text-primary">expand_more</span>
              </summary>
              <div class="mt-sm text-body-sm text-on-surface-variant dark:text-surface-variant leading-relaxed border-t border-slate-200/80 dark:border-slate-800/60 pt-sm">
                Kami mengutamakan kenyamanan pasien. Untuk perawatan medis tertentu seperti laser atau injeksi, kami menggunakan krim anestesi topikal berkualitas tinggi untuk meminimalisir rasa tidak nyaman selama tindakan berlangsung.
              </div>
            </details>

            <!-- FAQ 5 -->
            <details name="faq" class="group bg-surface-container-low dark:bg-surface-container-low/50 border border-outline-variant dark:border-outline rounded-2xl p-md [&_summary::-webkit-details-marker]:hidden transition-all duration-300">
              <summary class="flex justify-between items-center font-body-md text-body-md text-on-surface dark:text-inverse-on-surface font-bold cursor-pointer outline-none">
                <span>Metode pembayaran apa saja yang diterima di GlowSkin?</span>
                <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180 text-primary">expand_more</span>
              </summary>
              <div class="mt-sm text-body-sm text-on-surface-variant dark:text-surface-variant leading-relaxed border-t border-slate-200/80 dark:border-slate-800/60 pt-sm">
                Kami menerima pembayaran tunai, kartu debit/kredit (Visa, Mastercard, BCA, Mandiri), serta transfer bank dan QRIS. Pembayaran dilakukan di klinik setelah perawatan selesai.
              </div>
            </details>
          </div>
        </div>
      </section>

      <!-- Reservation Section -->
      <section class="py-xl bg-surface-container-low dark:bg-surface-container-low/10" id="reservation">
        <div class="max-w-7xl mx-auto px-xl">
          <div class="bg-surface-container-lowest dark:bg-inverse-surface border border-outline-variant dark:border-outline shadow-sm grid grid-cols-1 lg:grid-cols-12">
            <!-- Left Side: Clinic Interior Image -->
            <div class="hidden lg:block lg:col-span-5 relative overflow-hidden">
              <img class="w-full h-full object-cover object-right" alt="Interior Klinik GlowSkin" src="../assets/images/clinic_interior.png" />
              <!-- Subtle overlay for integration -->
              <div class="absolute inset-0 bg-gradient-to-r from-transparent to-background/10 dark:to-inverse-surface/10"></div>
            </div>

            <!-- Right Side: Booking Form -->
            <div class="lg:col-span-7 p-lg md:p-xl flex flex-col justify-center">
              <div class="text-left mb-xl">
                <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface mb-sm">Formulir Pendaftaran &amp; Janji Temu</h2>
                <p class="font-body-md text-body-md text-on-surface-variant dark:text-surface-variant">Lengkapi data diri Anda untuk memulai perjalanan menuju kulit sehat impian.</p>
              </div>

              <?php
              /**
               * ============================================================
               * FORM PENDAFTARAN PASIEN BARU
               * ============================================================
               * PERUBAHAN UTAMA dari versi HTML:
               * 1. Atribut method="POST" dan action="" (submit ke diri sendiri).
               * 2. Setiap input diberi atribut 'name' yang sinkron dengan
               *    variabel $_POST di blok PHP di atas.
               * 3. Dropdown dokter menggunakan value numerik (ID dokter dari DB).
               * ============================================================
               */
              ?>
              <form class="space-y-lg" id="booking-form" method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                  <div class="space-y-xs">
                    <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">NAMA LENGKAP</label>
                    <!-- name="nama_lengkap" → sinkron dengan $_POST['nama_lengkap'] -->
                    <input id="booking-name" name="nama_lengkap" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" placeholder="Masukkan nama lengkap Anda" required="" type="text" />
                    <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                  </div>
                  <div class="space-y-xs">
                    <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">NOMOR WHATSAPP / HP</label>
                    <!-- name="no_telepon" → sinkron dengan $_POST['no_telepon'] -->
                    <input name="no_telepon" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" placeholder="Contoh: 08123456789" required="" type="tel" />
                    <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                  <div class="space-y-xs">
                    <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">TANGGAL LAHIR</label>
                    <input name="tanggal_lahir" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" required="" type="date" />
                    <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                  </div>
                  <div class="space-y-xs">
                    <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">JENIS KELAMIN</label>
                    <select name="id_jenis_kelamin" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" required="">
                      <option disabled="" selected="" value="">Pilih jenis kelamin</option>
                      <option value="1">Laki-laki</option>
                      <option value="2">Perempuan</option>
                    </select>
                    <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                  </div>
                </div>

                <!-- Perawatan Category Row: Adjusted to 2 columns for space -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                  <div class="space-y-xs">
                    <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">KATEGORI PERAWATAN</label>
                    <select id="booking-category" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" required="">
                      <option disabled="" selected="" value="">Pilih kategori utama</option>
                      <option value="laser">Laser Therapy</option>
                      <option value="facial">Facial Treatment</option>
                      <option value="skincare">Skincare Racikan</option>
                    </select>
                    <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                  </div>
                  <div class="space-y-xs">
                    <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">DETAIL PERAWATAN</label>
                    <select id="booking-service" name="id_layanan" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" required="" disabled="">
                      <option disabled="" selected="" value="">Pilih kategori dahulu</option>
                    </select>
                    <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                  </div>
                </div>

                <!-- Doctor Selection Row: Moved to a full-width row for better spacing -->
                <div class="space-y-xs">
                  <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">PILIH DOKTER</label>
                  <!-- name="id_dokter" → sinkron dengan $_POST['id_dokter'] -->
                  <!-- value menggunakan ID numerik sesuai tabel dokter di database -->
                  <select name="id_dokter" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" required="">
                    <option disabled="" selected="" value="">Pilih dokter</option>
                    <?php foreach ($daftar_dokter as $dokter) : ?>
                      <option value="<?= $dokter['id_dokter'] ?>"><?= htmlspecialchars($dokter['nama_lengkap']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                </div>

                <div class="space-y-xs">
                  <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">CATATAN KELUHAN KULIT</label>
                  <!-- name="keluhan_utama" → sinkron dengan $_POST['keluhan_utama'] -->
                  <textarea name="keluhan_utama" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" placeholder="Ceritakan kondisi atau keluhan kulit Anda saat ini..." rows="4"></textarea>
                  <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                </div>

                <button class="w-full bg-primary text-on-primary py-md rounded-xl font-headline-md text-headline-md hover:bg-primary-container transition-all active:scale-[0.98] flex items-center justify-center gap-md" type="submit">
                  <span class="material-symbols-outlined">event_available</span>
                  Daftar &amp; Booking Jadwal
                </button>
              </form>
            </div>
          </div>
        </div>
      </section>
      <!-- Informasi Klinik Section -->
      <section class="py-xl bg-surface dark:bg-inverse-surface/10" id="info-klinik">
        <div class="max-w-7xl mx-auto px-xl">
          <div class="text-center mb-xl">
            <span class="px-md py-1 rounded-full bg-primary/10 border border-primary/20 text-primary font-label-caps text-label-caps inline-block mb-xs">INFO KLINIK</span>
            <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface mb-sm">Jam Operasional &amp; Lokasi</h2>
            <p class="font-body-md text-body-md text-on-surface-variant dark:text-surface-variant max-w-2xl mx-auto">
              Kunjungi kami atau hubungi untuk informasi lebih lanjut mengenai perawatan kulit Anda.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-lg">
            <!-- Jam Operasional -->
            <div class="bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline p-xl rounded-2xl text-center hover-scale">
              <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-lg mx-auto">
                <span class="material-symbols-outlined text-3xl">schedule</span>
              </div>
              <h3 class="font-title-sm text-title-sm mb-md text-on-surface dark:text-inverse-on-surface">Jam Operasional</h3>
              <div class="space-y-xs text-on-surface-variant dark:text-surface-variant font-body-sm">
                <p><span class="font-semibold text-on-surface dark:text-inverse-on-surface">Senin &ndash; Jumat:</span> 09.00 &ndash; 20.00 WIB</p>
                <p><span class="font-semibold text-on-surface dark:text-inverse-on-surface">Sabtu:</span> 09.00 &ndash; 17.00 WIB</p>
                <p><span class="font-semibold text-on-surface dark:text-inverse-on-surface">Minggu &amp; Hari Libur:</span> Tutup</p>
              </div>
            </div>

            <!-- Alamat -->
            <div class="bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline p-xl rounded-2xl text-center hover-scale">
              <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-lg mx-auto">
                <span class="material-symbols-outlined text-3xl">location_on</span>
              </div>
              <h3 class="font-title-sm text-title-sm mb-md text-on-surface dark:text-inverse-on-surface">Alamat Klinik</h3>
              <div class="space-y-xs text-on-surface-variant dark:text-surface-variant font-body-sm">
                <p>Jl. Kecantikan No. 88, Lantai 2</p>
                <p>Kelurahan Sehat Berseri</p>
                <p>Kota Bandung, Jawa Barat 40123</p>
              </div>
            </div>

            <!-- Kontak -->
            <div class="bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline p-xl rounded-2xl text-center hover-scale">
              <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-lg mx-auto">
                <span class="material-symbols-outlined text-3xl">call</span>
              </div>
              <h3 class="font-title-sm text-title-sm mb-md text-on-surface dark:text-inverse-on-surface">Hubungi Kami</h3>
              <div class="space-y-xs text-on-surface-variant dark:text-surface-variant font-body-sm">
                <p><span class="font-semibold text-on-surface dark:text-inverse-on-surface">Telepon:</span> (022) 8888-1234</p>
                <p><span class="font-semibold text-on-surface dark:text-inverse-on-surface">WhatsApp:</span> 0812-3456-7890</p>
                <p><span class="font-semibold text-on-surface dark:text-inverse-on-surface">Email:</span> info@glowskin.id</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="py-xl bg-surface-container-lowest dark:bg-inverse-surface border-t border-outline-variant dark:border-outline">
      <div class="max-w-7xl mx-auto px-xl text-center">
        <div class="flex items-center justify-center gap-sm mb-lg">
          <span class="material-symbols-outlined text-primary text-2xl" style="font-variation-settings: 'FILL' 1;">spa</span>
          <span class="font-title-sm text-title-sm font-bold text-primary tracking-tight">GlowSkin Aesthetic Clinic</span>
        </div>
        <div class="flex flex-wrap justify-center gap-xl mb-md text-on-surface-variant dark:text-surface-variant font-body-sm font-semibold">
          <a class="hover:text-primary transition-colors" href="#services">Layanan</a>
          <a class="hover:text-primary transition-colors" href="#doctors">Dokter</a>
          <a class="hover:text-primary transition-colors" href="#testimonials">Ulasan</a>
          <a class="hover:text-primary transition-colors" href="#faq">FAQ</a>
          <a class="hover:text-primary transition-colors" href="#reservation">Reservasi</a>
        </div>
        <div class="flex flex-wrap justify-center gap-xl mb-md text-on-surface-variant/70 dark:text-surface-variant/70 font-body-sm text-[13px]">
          <a class="hover:text-primary transition-colors" href="#">Syarat &amp; Ketentuan</a>
          <a class="hover:text-primary transition-colors" href="#">Kebijakan Privasi</a>
          <a class="hover:text-primary transition-colors" href="#">Pusat Bantuan</a>
        </div>
        <div class="flex flex-wrap justify-center gap-lg mb-md text-on-surface-variant dark:text-surface-variant font-body-sm">
          <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-primary text-[16px]">call</span> (022) 8888-1234</span>
          <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-primary text-[16px]">chat</span> 0812-3456-7890</span>
          <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-primary text-[16px]">mail</span> info@glowskin.id</span>
        </div>
        <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant">
          &copy; 2025 GlowSkin Aesthetic Clinic. Semua Hak Dilindungi.
        </p>
      </div>
    </footer>

    <script>
      // Initialize theme toggling
      setupThemeToggle('theme-toggle', 'dark-icon', 'light-icon');

      // Simple Fade-in Animation on Scroll
      const observerOptions = {
        threshold: 0.1
      };

      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('opacity-100', 'translate-y-0');
            entry.target.classList.remove('opacity-0', 'translate-y-10');
          }
        });
      }, observerOptions);

      document.querySelectorAll('section').forEach(section => {
        section.classList.add('transition-all', 'duration-700', 'opacity-0', 'translate-y-10');
        observer.observe(section);
      });
    </script>
    
    <script>
      (function() {
        const form = document.getElementById('booking-form');
        const inputs = form.querySelectorAll('input, select, textarea');

        const showError = (el, msg) => {
          const errorSpan = el.nextElementSibling;
          if (errorSpan && errorSpan.classList.contains('error-msg')) {
            errorSpan.textContent = msg;
            errorSpan.classList.remove('hidden');
            el.classList.add('border-error');
            el.classList.remove('border-outline-variant');
          }
        };

        const hideError = (el) => {
          const errorSpan = el.nextElementSibling;
          if (errorSpan && errorSpan.classList.contains('error-msg')) {
            errorSpan.classList.add('hidden');
            el.classList.remove('border-error');
            el.classList.add('border-outline-variant');
          }
        };

        form.addEventListener('submit', function(e) {
          /**
           * PERUBAHAN PENTING:
           * Kita TIDAK lagi memanggil e.preventDefault() secara default.
           * Validasi JS dilakukan terlebih dahulu. Jika valid, form akan
           * di-submit secara normal ke PHP (method POST).
           * Jika tidak valid, baru kita panggil e.preventDefault().
           */
          let isValid = true;

          inputs.forEach(input => {
            hideError(input);
            
            if (!input.value.trim()) {
              showError(input, 'Bagian ini tidak boleh kosong');
              isValid = false;
            } else if (input.type === 'text' && input.value.length < 3) {
              showError(input, 'Nama minimal 3 karakter');
              isValid = false;
            } else if (input.type === 'tel') {
              if (!/^\d+$/.test(input.value)) {
                showError(input, 'Hanya boleh berisi angka');
                isValid = false;
              } else if (input.value.length < 10) {
                showError(input, 'Minimal 10 digit nomor');
                isValid = false;
              }
            }
          });

          if (!isValid) {
            // Hanya cegah submit jika validasi gagal
            e.preventDefault();
          }
          // Jika isValid = true, form akan di-submit ke PHP secara normal
        });
      })();
    </script>
    <script>
      // Service Details Data
      const serviceDetailsData = {
        laser: {
          title: "Laser Therapy",
          icon: "earbuds",
          desc: "Terapi laser modern menggunakan teknologi Q-Switched Nd:YAG untuk menargetkan sel pigmen kulit secara selektif. Sangat efektif untuk memudarkan noda hitam, flek membandel, meratakan warna kulit, merangsang regenerasi kolagen, dan menyamarkan garis halus tanpa rasa sakit yang berlebih.",
          duration: "45 Menit",
          price: "Rp 750.000 - Rp 1.500.000",
          benefits: [
            "Menyamarkan hiperpigmentasi & flek melasma",
            "Meratakan warna kulit wajah secara efektif",
            "Mengecilkan pori-pori dan merangsang produksi kolagen",
            "Downtime minimal (kemerahan ringan hanya 1-2 jam)"
          ],
          selectValue: "2"
        },
        facial: {
          title: "Facial Treatment",
          icon: "face_6",
          desc: "Perawatan spa medis wajah lengkap yang memadukan eksfoliasi kimiawi ringan, ekstraksi komedo higienis oleh terapis ahli, pemijatan relaksasi wajah untuk melancarkan sirkulasi darah, serta masker serum bernutrisi tinggi yang disesuaikan dengan jenis kulit Anda (kering, berminyak, sensitif, atau berjerawat).",
          duration: "60 Menit",
          price: "Rp 250.000 - Rp 500.000",
          benefits: [
            "Membersihkan pori-pori secara mendalam dari komedo",
            "Mengangkat tumpukan sel kulit mati yang kusam",
            "Memberikan kelembapan instan dan hidrasi maksimal",
            "Wajah tampak bersih, kenyal, dan segar bercahaya"
          ],
          selectValue: "3"
        },
        skincare: {
          title: "Skincare Racikan",
          icon: "clinical_notes",
          desc: "Konsultasi medis eksklusif secara langsung dengan Dokter Spesialis Kulit kami untuk menganalisis kondisi wajah Anda (menggunakan skin analyzer jika diperlukan). Dokter akan merancang formula produk perawatan khusus (cleanser, toner, serum, krim pagi & malam) yang disesuaikan dengan masalah spesifik kulit Anda secara aman dan terkontrol.",
          duration: "30 Menit (Konsultasi)",
          price: "Rp 350.000 - Rp 800.000",
          benefits: [
            "Konsultasi personal langsung dengan dokter spesialis",
            "Formulasi produk berstandar medis tinggi & teruji klinis",
            "Penanganan jerawat aktif, bekas luka, atau jerawat melasma tepat sasaran",
            "Pemantauan kemajuan kesehatan kulit secara berkala"
          ],
          selectValue: "1"
        }
      };

      (function() {
        // Data pelayanan berdasarkan kategori (dihasilkan secara dinamis dari PHP)
        const servicesByCategory = <?= json_encode($js_services) ?>;
        
        // Setup dependent dropdown
        const categorySelect = document.getElementById('booking-category');
        const serviceSelect = document.getElementById('booking-service');

        if (categorySelect && serviceSelect) {
          categorySelect.addEventListener('change', function() {
            const category = this.value;
            // Reset detail perawatan
            serviceSelect.innerHTML = '<option disabled selected value="">Pilih tindakan spesifik</option>';
            
            if (category && servicesByCategory[category] && servicesByCategory[category].length > 0) {
              serviceSelect.disabled = false;
              servicesByCategory[category].forEach(service => {
                const opt = document.createElement('option');
                opt.value = service.id;
                opt.textContent = service.name;
                serviceSelect.appendChild(opt);
              });
            } else {
              serviceSelect.disabled = true;
            }
          });
        }

        // Create and inject modal elements
        const serviceModalContainer = document.createElement('div');
        serviceModalContainer.innerHTML = `
          <!-- Modal Detail Layanan -->
          <div id="service-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
            <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-slate-800 rounded-3xl w-full max-w-lg p-xl shadow-2xl transform scale-95 transition-transform duration-300 relative">
              <button id="close-service-modal" class="absolute top-md right-md text-on-surface-variant dark:text-slate-400 hover:text-primary transition-colors flex items-center p-xs rounded-full hover:bg-surface-container-low dark:hover:bg-slate-800">
                <span class="material-symbols-outlined text-[24px]">close</span>
              </button>
              
              <div class="space-y-lg">
                <div class="flex items-center gap-md">
                  <div id="service-modal-icon" class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center text-primary flex-shrink-0">
                    <span class="material-symbols-outlined text-4xl">earbuds</span>
                  </div>
                  <div>
                    <span class="px-sm py-0.5 bg-primary/10 text-primary rounded-full text-[10px] font-bold tracking-wider font-label-caps">LAYANAN MEDIS</span>
                    <h3 id="service-modal-title" class="font-headline-md text-headline-md text-on-surface dark:text-slate-100 mt-1">Laser Therapy</h3>
                  </div>
                </div>
                
                <div class="space-y-md">
                  <div>
                    <h4 class="text-body-sm font-bold text-on-surface dark:text-slate-200">Deskripsi Perawatan</h4>
                    <p id="service-modal-desc" class="text-body-sm text-on-surface-variant dark:text-slate-350 mt-xs leading-relaxed">
                      Teknologi laser mutakhir untuk mengatasi hiperpigmentasi, bekas jerawat, dan peremajaan kulit secara presisi tanpa downtime yang lama.
                    </p>
                  </div>
                  
                  <div class="grid grid-cols-2 gap-md pt-xs">
                    <div class="bg-surface-container-low/50 dark:bg-slate-800/50 p-md rounded-xl border border-outline-variant/20 dark:border-slate-800">
                      <span class="text-[11px] font-bold text-on-surface-variant dark:text-slate-400 uppercase tracking-wider block">Durasi</span>
                      <span id="service-modal-duration" class="text-body-md font-bold text-primary dark:text-primary-fixed-dim mt-1 block">45 Menit</span>
                    </div>
                    <div class="bg-surface-container-low/50 dark:bg-slate-800/50 p-md rounded-xl border border-outline-variant/20 dark:border-slate-800">
                      <span class="text-[11px] font-bold text-on-surface-variant dark:text-slate-400 uppercase tracking-wider block">Estimasi Biaya</span>
                      <span id="service-modal-price" class="text-body-md font-bold text-primary dark:text-primary-fixed-dim mt-1 block">Rp 750.000+</span>
                    </div>
                  </div>

                  <div class="pt-xs">
                    <h4 class="text-body-sm font-bold text-on-surface dark:text-slate-200">Manfaat Utama</h4>
                    <ul id="service-modal-benefits" class="list-disc list-inside text-body-sm text-on-surface-variant dark:text-slate-350 mt-xs space-y-xs">
                      <li>Menyamarkan noda hitam & flek</li>
                      <li>Merangsang kolagen</li>
                      <li>Menghaluskan tekstur kulit</li>
                    </ul>
                  </div>
                </div>

                <div class="flex gap-md pt-md border-t border-outline-variant/20 dark:border-slate-800/80">
                  <button id="close-service-modal-btn" class="flex-1 py-3 border border-outline-variant dark:border-slate-700 text-on-surface-variant dark:text-slate-300 rounded-xl font-title-sm text-body-md hover:bg-surface-container-low dark:hover:bg-slate-800 transition-all text-center">
                    Tutup
                  </button>
                  <button id="book-service-modal-btn" class="flex-1 py-3 bg-primary text-on-primary rounded-xl font-title-sm text-body-md hover:brightness-110 transition-all text-center flex items-center justify-center gap-sm">
                    <span class="material-symbols-outlined text-[20px]">event_available</span>
                    Booking Sekarang
                  </button>
                </div>
              </div>
            </div>
          </div>
        `;
        document.body.appendChild(serviceModalContainer);

        const modal = document.getElementById('service-modal');
        const modalIcon = document.getElementById('service-modal-icon');
        const modalTitle = document.getElementById('service-modal-title');
        const modalDesc = document.getElementById('service-modal-desc');
        const modalDuration = document.getElementById('service-modal-duration');
        const modalPrice = document.getElementById('service-modal-price');
        const modalBenefits = document.getElementById('service-modal-benefits');
        let currentActiveService = null;
        let currentActiveCategory = null;

        function openServiceModal(serviceKey) {
          const data = serviceDetailsData[serviceKey];
          if (!data) return;

          currentActiveService = data.selectValue;
          currentActiveCategory = serviceKey;

          // Populate details
          modalIcon.innerHTML = `<span class="material-symbols-outlined text-4xl">${data.icon}</span>`;
          modalTitle.textContent = data.title;
          modalDesc.textContent = data.desc;
          modalDuration.textContent = data.duration;
          modalPrice.textContent = data.price;
          
          // Populate benefits
          modalBenefits.innerHTML = '';
          data.benefits.forEach(benefit => {
            const li = document.createElement('li');
            li.className = 'leading-relaxed';
            li.textContent = benefit;
            modalBenefits.appendChild(li);
          });

          // Show transition
          modal.classList.remove('hidden');
          setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
          }, 10);
        }

        function closeServiceModal() {
          modal.classList.add('opacity-0');
          modal.querySelector('div').classList.add('scale-95');
          setTimeout(() => {
            modal.classList.add('hidden');
          }, 300);
        }

        // Hook up learn more buttons
        document.querySelectorAll('.btn-learn-more').forEach(btn => {
          btn.addEventListener('click', () => {
            const serviceKey = btn.getAttribute('data-service');
            openServiceModal(serviceKey);
          });
        });

        // Hook up close buttons
        document.getElementById('close-service-modal')?.addEventListener('click', closeServiceModal);
        document.getElementById('close-service-modal-btn')?.addEventListener('click', closeServiceModal);

        // Hook up booking button inside modal
        document.getElementById('book-service-modal-btn')?.addEventListener('click', () => {
          closeServiceModal();
          
          // Scroll to reservation
          const reservationSection = document.getElementById('reservation');
          if (reservationSection) {
            reservationSection.scrollIntoView({ behavior: 'smooth' });
          }

          // Auto select treatment dropdown (Category + Detail)
          const categorySelect = document.getElementById('booking-category');
          const serviceSelect = document.getElementById('booking-service');
          if (categorySelect && serviceSelect && currentActiveCategory && currentActiveService) {
            categorySelect.value = currentActiveCategory;
            // Trigger change event to populate serviceSelect
            categorySelect.dispatchEvent(new Event('change'));
            serviceSelect.value = currentActiveService;
          }

          // Focus on name input
          const nameInput = document.getElementById('booking-name');
          if (nameInput) {
            setTimeout(() => {
              nameInput.focus();
            }, 600); // Wait for smooth scroll
          }
        });
      })();
    </script>
    <script>
      // Interactive Star Rating for Review Form
      (function() {
        const starContainer = document.getElementById('star-rating-input');
        const ratingInput = document.getElementById('rating-value');
        const ratingText = document.getElementById('rating-text');
        if (!starContainer || !ratingInput) return;

        const starBtns = starContainer.querySelectorAll('.star-btn');
        let currentRating = 5;

        function updateStars(value) {
          starBtns.forEach(btn => {
            const val = parseInt(btn.getAttribute('data-value'));
            if (val <= value) {
              btn.classList.remove('text-on-surface-variant/30');
              btn.classList.add('text-amber-400');
            } else {
              btn.classList.add('text-on-surface-variant/30');
              btn.classList.remove('text-amber-400');
            }
          });
        }

        // Initialize all stars as selected (default 5)
        updateStars(5);

        starBtns.forEach(btn => {
          btn.addEventListener('mouseenter', () => {
            updateStars(parseInt(btn.getAttribute('data-value')));
          });

          btn.addEventListener('click', () => {
            currentRating = parseInt(btn.getAttribute('data-value'));
            ratingInput.value = currentRating;
            if (ratingText) ratingText.textContent = currentRating + ' / 5';
            updateStars(currentRating);
          });
        });

        starContainer.addEventListener('mouseleave', () => {
          updateStars(currentRating);
        });
      })();
    </script>
    <script>
      // Testimonials Carousel Control
      (function() {
        const slider = document.getElementById('testi-slider');
        const prevBtn = document.getElementById('prev-testi');
        const nextBtn = document.getElementById('next-testi');
        if (!slider || !prevBtn || !nextBtn) return;

        // Dynamic scroll calculation based on card width
        function getScrollStep() {
          const firstCard = slider.firstElementChild;
          if (!firstCard) return 300;
          
          // Width of card plus gap
          const cardWidth = firstCard.getBoundingClientRect().width;
          const style = window.getComputedStyle(slider);
          const gap = parseFloat(style.columnGap || style.gap) || 24;
          return cardWidth + gap;
        }

        prevBtn.addEventListener('click', () => {
          slider.scrollBy({ left: -getScrollStep(), behavior: 'smooth' });
        });

        nextBtn.addEventListener('click', () => {
          slider.scrollBy({ left: getScrollStep(), behavior: 'smooth' });
        });
      })();
    </script>
    <script>
      // FAQ Accordion Fallback
      (function() {
        const details = document.querySelectorAll('details[name="faq"]');
        details.forEach((targetDetail) => {
          targetDetail.addEventListener('toggle', () => {
            if (targetDetail.open) {
              details.forEach((detail) => {
                if (detail !== targetDetail && detail.open) {
                  detail.open = false; // Close other details
                }
              });
            }
          });
        });
      })();
    </script>
  </body>
</html>
