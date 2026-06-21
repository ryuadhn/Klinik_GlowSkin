<?php
/**
 * ========================================================================
 * FILE: koneksi.php
 * DESKRIPSI: File konfigurasi koneksi database menggunakan PDO.
 * ========================================================================
 * File ini bertugas membuat koneksi ke database MySQL 'glowskin_db'
 * menggunakan ekstensi PDO (PHP Data Objects). PDO dipilih karena
 * lebih aman (mendukung prepared statement) dan fleksibel.
 *
 * File ini akan di-include/require oleh semua halaman PHP lain
 * yang membutuhkan akses ke database.
 * ========================================================================
 */

// --- KONFIGURASI PARAMETER DATABASE ---
// Sesuaikan nilai-nilai di bawah ini dengan pengaturan server MySQL kamu.
$host   = 'localhost';      // Alamat server database (biasanya 'localhost' untuk XAMPP)
$dbname = 'glowskin_db';    // Nama database yang sudah dibuat di MySQL Workbench
$user   = 'root';           // Username database (default XAMPP adalah 'root')
$pass   = 'root123';        // Password database (default XAMPP biasanya kosong, sesuaikan jika ada)

/**
 * --- BLOK TRY-CATCH UNTUK KONEKSI DATABASE ---
 * try   : Mencoba membuat koneksi ke database.
 * catch : Menangkap error jika koneksi gagal, lalu menampilkan pesan error.
 */
try {
    /**
     * Membuat objek PDO baru untuk koneksi ke database MySQL.
     * Parameter DSN (Data Source Name) berisi:
     * - mysql:host  = alamat server database
     * - dbname      = nama database yang dituju
     * - charset     = set karakter UTF-8 agar mendukung karakter Indonesia
     */
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass
    );

    /**
     * Mengatur mode error PDO menjadi EXCEPTION.
     * Artinya, setiap error SQL akan di-throw sebagai PDOException,
     * sehingga bisa ditangkap oleh blok catch.
     * Ini WAJIB diaktifkan agar debugging menjadi lebih mudah.
     */
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /**
     * Mengatur default fetch mode menjadi FETCH_ASSOC.
     * Artinya, hasil query akan dikembalikan sebagai array asosiatif
     * (menggunakan nama kolom sebagai key), bukan array numerik.
     * Contoh: $row['nama_lengkap'] bukan $row[0].
     */
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- SETUP SESSION AUDIT VARIABLES FOR TRIGGERS ---
    $ip = '127.0.0.1';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if ($ip === '::1') {
        $ip = '127.0.0.1';
    }

    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    $source = 'Sistem';
    if (strpos($current_uri, '/dashboard-dokter/') !== false) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $id_doc = $_SESSION['id_dokter'] ?? 1;
        $source = ($id_doc == 2) ? 'dr. Adrian, S.Ked' : 'dr. Sarah, Sp.KK';
    } elseif (strpos($current_uri, '/dashboard-admin/') !== false) {
        $source = 'Admin Dashboard';
    } elseif (strpos($current_uri, '/landing-page/') !== false) {
        $source = 'Landing Page';
    }

    $stmt_set = $pdo->prepare("SET @current_ip = ?, @current_source = ?");
    $stmt_set->execute([$ip, $source]);

} catch (PDOException $e) {
    /**
     * Jika koneksi gagal, PDO akan melempar (throw) PDOException.
     * Blok catch ini menangkap exception tersebut dan menghentikan
     * eksekusi program sambil menampilkan pesan error yang informatif.
     *
     * PENTING: Di lingkungan PRODUKSI (live website), jangan tampilkan
     * detail error ke pengguna. Ganti dengan pesan generik dan log errornya.
     * Untuk tahap pengembangan/asistensi, kita tampilkan detail errornya.
     */
    die("❌ KONEKSI DATABASE GAGAL: " . $e->getMessage());
}
?>
