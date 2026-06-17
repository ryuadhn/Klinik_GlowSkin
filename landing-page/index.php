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

// --- VARIABEL UNTUK MENAMPUNG PESAN FEEDBACK KE PENGGUNA ---
$pesan_sukses = '';  // Akan diisi jika pendaftaran berhasil
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- LANGKAH 1: Tangkap data dari form via $_POST ---
        // Fungsi trim() digunakan untuk menghapus spasi di awal/akhir input.
        // Fungsi htmlspecialchars() digunakan untuk mencegah serangan XSS.
        $nama_lengkap   = trim(htmlspecialchars($_POST['nama_lengkap'] ?? ''));
        $no_telepon     = trim(htmlspecialchars($_POST['no_telepon'] ?? ''));
        $keluhan_utama  = trim(htmlspecialchars($_POST['keluhan_utama'] ?? ''));
        $id_dokter      = intval($_POST['id_dokter'] ?? 1); // Default dokter ID 1 jika tidak dipilih
        $id_layanan     = intval($_POST['id_layanan'] ?? 1); // Default layanan ID 1 (Konsultasi) jika tidak dipilih

        // --- VALIDASI SEDERHANA: Pastikan field wajib tidak kosong ---
        if (empty($nama_lengkap) || empty($no_telepon)) {
            throw new Exception("Nama lengkap dan nomor telepon wajib diisi!");
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
            VALUES (:kode_pasien, :nama_lengkap, :no_telepon, '1970-01-01', 1)
        ");
        $stmt_pasien->execute([
            ':kode_pasien'  => $kode_pasien,
            ':nama_lengkap' => $nama_lengkap,
            ':no_telepon'   => $no_telepon
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

        // Simpan pesan sukses yang akan ditampilkan sebagai JavaScript alert
        $kode_kunj = $result_sp['kode_kunjungan'] ?? '-';
        $no_antri  = $result_sp['nomor_antrian'] ?? '-';
        $pesan_sukses = "Pendaftaran Berhasil!\\nKode Kunjungan: {$kode_kunj}\\nNomor Antrian Anda: {$no_antri}";

        /**
         * --- LANGKAH 5B: SINKRONKAN LAYANAN YANG DIPILIH KE detail_layanan ---
         * Query id_kunjungan yang baru saja dibuat, lalu masukkan layanannya ke tabel detail_layanan.
         */
        if (!empty($kode_kunj) && $kode_kunj !== '-') {
            $stmt_get_kunj = $pdo->prepare("SELECT id_kunjungan FROM kunjungan WHERE kode_kunjungan = :kode");
            $stmt_get_kunj->execute([':kode' => $kode_kunj]);
            $id_kunjungan_baru = $stmt_get_kunj->fetchColumn();

            if ($id_kunjungan_baru) {
                // Ambil harga layanan dari database
                $stmt_lay = $pdo->prepare("SELECT harga FROM layanan WHERE id_layanan = :id");
                $stmt_lay->execute([':id' => $id_layanan]);
                $harga_satuan = $stmt_lay->fetchColumn() ?? 0;

                // Insert ke detail_layanan
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

    } catch (PDOException $e) {
        // Tangkap error database (query gagal, SP error, dll.)
        $pesan_error = "Terjadi kesalahan database: " . $e->getMessage();
    } catch (Exception $e) {
        // Tangkap error validasi umum
        $pesan_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>GlowSkin | Premium Aesthetic Clinic</title>

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
    <header class="w-full h-20 sticky top-0 z-50 bg-surface-container-lowest/80 dark:bg-inverse-surface/80 backdrop-blur-md border-b border-outline-variant/30 dark:border-outline/30">
      <div class="max-w-7xl mx-auto px-lg h-full flex justify-between items-center">
        <div class="flex items-center gap-sm">
          <span class="material-symbols-outlined text-primary text-3xl" style="font-variation-settings: 'FILL' 1;">spa</span>
          <span class="font-headline-md text-headline-md font-extrabold text-primary dark:text-primary-fixed-dim tracking-tight">GlowSkin</span>
        </div>
        <nav class="hidden md:flex items-center gap-xl">
          <a class="font-title-sm text-title-sm text-primary font-bold" href="#">Beranda</a>
          <a class="font-title-sm text-title-sm text-on-surface-variant dark:text-surface-variant hover:text-primary transition-colors" href="#services">Layanan</a>
          <a class="font-title-sm text-title-sm text-on-surface-variant dark:text-surface-variant hover:text-primary transition-colors" href="#doctors">Dokter</a>
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
      <section class="relative min-h-[921px] flex items-center overflow-hidden">
        <!-- Background Decoration -->
        <div class="absolute top-0 right-0 w-1/2 h-full opacity-10 dark:opacity-5 pointer-events-none">
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

          <div class="relative hidden lg:block">
            <div class="rounded-3xl overflow-hidden aspect-[4/5] border border-outline-variant dark:border-outline shadow-xl relative group">
              <img class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" data-alt="A serene, professional skincare treatment scene in a high-end medical clinic. A patient with clear, glowing skin lies peacefully while a professional aesthetician in a clinical uniform gently applies a premium serum using specialized tools. The lighting is bright, soft, and sophisticated, emphasizing a clean and sterile medical environment. The color palette features whites, soft teals, and clinical greys, reflecting a luxurious spa-like yet authoritative atmosphere." src="../assets/images/skincare_treatment.png" />
              <div class="absolute bottom-md left-md right-md glass-card p-lg rounded-xl">
                <div class="flex items-center gap-md">
                  <div class="w-12 h-12 rounded-full bg-primary flex items-center justify-center text-on-primary">
                    <span class="material-symbols-outlined">award_star</span>
                  </div>
                  <div>
                    <p class="font-title-sm text-title-sm text-on-surface">Klinik Terpercaya</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Lebih dari 10.000+ pasien puas</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
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
            <div class="bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline p-xl rounded-2xl hover:border-primary/50 transition-all group flex flex-col h-full hover-scale">
              <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-lg group-hover:bg-primary group-hover:text-on-primary transition-all">
                <span class="material-symbols-outlined text-3xl">earbuds</span>
              </div>
              <h3 class="font-title-sm text-title-sm mb-md text-on-surface dark:text-inverse-on-surface">Laser Therapy</h3>
              <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant mb-xl flex-grow">
                Teknologi laser mutakhir untuk mengatasi hiperpigmentasi, bekas jerawat, dan peremajaan kulit secara presisi tanpa downtime yang lama.
              </p>
              <div class="btn-learn-more flex items-center gap-xs text-primary font-title-sm cursor-pointer group/link" data-service="laser">
                Pelajari Selengkapnya
                <span class="material-symbols-outlined text-[18px] group-hover/link:translate-x-1 transition-transform">arrow_forward</span>
              </div>
            </div>

            <!-- Service 2 -->
            <div class="bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline p-xl rounded-2xl hover:border-primary/50 transition-all group flex flex-col h-full hover-scale">
              <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-lg group-hover:bg-primary group-hover:text-on-primary transition-all">
                <span class="material-symbols-outlined text-3xl">face_6</span>
              </div>
              <h3 class="font-title-sm text-title-sm mb-md text-on-surface dark:text-inverse-on-surface">Facial Treatment</h3>
              <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant mb-xl flex-grow">
                Perawatan wajah intensif yang disesuaikan dengan kebutuhan kulit Anda, mulai dari deep cleansing hingga hidrasi maksimal.
              </p>
              <div class="btn-learn-more flex items-center gap-xs text-primary font-title-sm cursor-pointer group/link" data-service="facial">
                Pelajari Selengkapnya
                <span class="material-symbols-outlined text-[18px] group-hover/link:translate-x-1 transition-transform">arrow_forward</span>
              </div>
            </div>

            <!-- Service 3 -->
            <div class="bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline p-xl rounded-2xl hover:border-primary/50 transition-all group flex flex-col h-full hover-scale">
              <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-lg group-hover:bg-primary group-hover:text-on-primary transition-all">
                <span class="material-symbols-outlined text-3xl">clinical_notes</span>
              </div>
              <h3 class="font-title-sm text-title-sm mb-md text-on-surface dark:text-inverse-on-surface">Skincare Racikan</h3>
              <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant mb-xl flex-grow">
                Konsultasi mendalam dengan dokter spesialis untuk mendapatkan formulasi skincare yang dirancang khusus untuk profil kulit unik Anda.
              </p>
              <div class="btn-learn-more flex items-center gap-xs text-primary font-title-sm cursor-pointer group/link" data-service="skincare">
                Pelajari Selengkapnya
                <span class="material-symbols-outlined text-[18px] group-hover/link:translate-x-1 transition-transform">arrow_forward</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Doctors Section -->
      <section class="py-xl" id="doctors">
        <div class="max-w-7xl mx-auto px-xl">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-xl items-center">
            <div>
              <h2 class="font-headline-md text-headline-md text-on-surface dark:text-inverse-on-surface mb-md">Ditangani Oleh Ahlinya</h2>
              <p class="font-body-md text-body-md text-on-surface-variant dark:text-surface-variant mb-lg">
                Kesehatan kulit Anda adalah prioritas kami. Tim medis kami terdiri dari dokter spesialis kulit yang berpengalaman dan bersertifikasi internasional.
              </p>
              <ul class="space-y-md">
                <li class="flex gap-md">
                  <div class="mt-1 w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                  </div>
                  <div>
                    <p class="font-title-sm text-title-sm text-on-surface">dr. Sarah, Sp.KK</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Spesialis Kulit &amp; Kelamin - Anti Aging Expert</p>
                  </div>
                </li>
                <li class="flex gap-md">
                  <div class="mt-1 w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                  </div>
                  <div>
                    <p class="font-title-sm text-title-sm text-on-surface">dr. Adrian</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Medical Aesthetician - Laser Treatment Specialist</p>
                  </div>
                </li>
              </ul>
            </div>

            <div class="grid grid-cols-2 gap-md items-start">
              <div class="rounded-2xl overflow-hidden border border-outline-variant">
                <img class="w-full h-64 object-cover" data-alt="A professional female dermatologist in a clean, crisp white medical coat, smiling warmly in a bright clinical setting. She holds a medical chart and has a stethoscope around her neck. The background is a minimalist, modern clinic interior with soft ivory walls and high-end medical equipment. The atmosphere is professional, empathetic, and trustworthy, conveying medical expertise in a luxurious environment." src="https://lh3.googleusercontent.com/aida-public/AB6AXuCedHsWtVogRuLqa7IZRhxpnlVl7bf7oqPlJ13qcZtAxiUNk1IAqcpxkOoiBrEJCLlTtht4Xuw9YBdlwOsfrIcQwfL_I7svWDZ8IlUTm4b5ESA__67dSmEPEfRx7pWseaFDU15utK5kxpc6zqbz3vXpgPvQK-n2x1MAWv02ncy0y5fk3eo8aryvBftAEXZS6Jnt6Ss3tgxuEu4QKQgwaGk_bwP3jslqtZp4-u02z6xuD4PUmDAxGFOUaqX1NDAwnfmzQvSjR9PzNqI" />
              </div>
              <div class="rounded-2xl overflow-hidden border border-outline-variant mt-xl">
                <img class="w-full h-64 object-cover" data-alt="A professional male doctor specialized in aesthetic medicine, dressed in a formal white clinical lab coat, standing in a contemporary medical office. He looks confident and welcoming, with high-end laser equipment visible in the background. The lighting is clinical yet warm, highlighting a high-tech medical standard. The color palette is composed of clean whites, teals, and steel greys, creating a sense of advanced medical care." src="https://lh3.googleusercontent.com/aida-public/AB6AXuCdnqV0hZQQyZHboUD9RM8O6NlzScyVOnO3-7r4g3zMK1pM65aD2aB5KAFOswI-qj41JeKvIqCaqfVVqks0zLotFZDxXSM68CVUHJ4YkkyN6PqO7iaj_H9JvoRQCLWvF6kyLZU_VaGMySI_JJJugcr8ZgDuU0CztzRvLm0av3bG5zXT7Fnl7bc0dUYV1SIwosc1R62DPSJ2KxccXrNHqjDztVUZhkq-Q3arqo247SfGQrguZzYxD9rYbkSTKkBf-rTW811qtgDcugc" />
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Reservation Section -->
      <section class="py-xl bg-surface-container-low dark:bg-surface-container-low/10" id="reservation">
        <div class="max-w-4xl mx-auto px-xl">
          <div class="bg-surface-container-lowest dark:bg-inverse-surface border border-outline-variant dark:border-outline rounded-3xl p-xl shadow-sm">
            <div class="text-center mb-xl">
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
                  <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">JENIS PERAWATAN</label>
                  <select id="booking-service" name="id_layanan" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" required="">
                    <option disabled="" selected="" value="">Pilih perawatan</option>
                    <option value="2">Laser Therapy</option>
                    <option value="3">Facial Treatment</option>
                    <option value="1">Konsultasi &amp; Skincare</option>
                  </select>
                  <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                </div>
                <div class="space-y-xs">
                  <label class="font-label-caps text-label-caps text-on-surface-variant dark:text-surface-variant ml-1">PILIH DOKTER</label>
                  <!-- name="id_dokter" → sinkron dengan $_POST['id_dokter'] -->
                  <!-- value menggunakan ID numerik sesuai tabel dokter di database -->
                  <select name="id_dokter" class="w-full bg-surface dark:bg-surface-container-low border border-outline-variant dark:border-outline rounded-lg px-md py-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none dark:text-inverse-on-surface" required="">
                    <option disabled="" selected="" value="">Pilih dokter</option>
                    <option value="1">dr. Sarah, Sp.KK</option>
                    <option value="2">dr. Adrian</option>
                  </select>
                  <span class="error-msg text-error text-label-caps mt-1 hidden">Field ini wajib diisi</span>
                </div>
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
      </section>
    </main>

    <!-- Footer -->
    <footer class="py-xl bg-surface-container-lowest dark:bg-inverse-surface border-t border-outline-variant dark:border-outline">
      <div class="max-w-7xl mx-auto px-xl text-center">
        <div class="flex items-center justify-center gap-sm mb-lg">
          <span class="material-symbols-outlined text-primary text-2xl" style="font-variation-settings: 'FILL' 1;">spa</span>
          <span class="font-title-sm text-title-sm font-bold text-primary tracking-tight">GlowSkin Aesthetic Clinic</span>
        </div>
        <div class="flex flex-wrap justify-center gap-xl mb-xl text-on-surface-variant dark:text-surface-variant font-body-sm">
          <a class="hover:text-primary transition-colors" href="#">Syarat &amp; Ketentuan</a>
          <a class="hover:text-primary transition-colors" href="#">Kebijakan Privasi</a>
          <a class="hover:text-primary transition-colors" href="#">Pusat Bantuan</a>
        </div>
        <p class="font-body-sm text-body-sm text-on-surface-variant dark:text-surface-variant">
          © 2024 GlowSkin Aesthetic Clinic. Semua Hak Dilindungi.
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

      document.addEventListener('DOMContentLoaded', () => {
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

        function openServiceModal(serviceKey) {
          const data = serviceDetailsData[serviceKey];
          if (!data) return;

          currentActiveService = data.selectValue;

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

          // Auto select treatment dropdown
          const selectElement = document.getElementById('booking-service');
          if (selectElement && currentActiveService) {
            selectElement.value = currentActiveService;
          }

          // Focus on name input
          const nameInput = document.getElementById('booking-name');
          if (nameInput) {
            setTimeout(() => {
              nameInput.focus();
            }, 600); // Wait for smooth scroll
          }
        });
      });
    </script>
  </body>
</html>
