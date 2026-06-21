<?php
/**
 * ============================================================
 * FILE: dashboard-admin/api_log_login.php
 * DESKRIPSI: Endpoint AJAX untuk mencatat percobaan login gagal
 *            staf klinik ke dalam tabel audit_log.
 * ============================================================
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../koneksi.php';

// Hanya terima POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$username = trim(htmlspecialchars($_POST['username'] ?? 'Unknown'));
$ip_address = $_SERVER['REMOTE_ADDR'];

// Normalisasikan IP local jika IPv6 loopback
if ($ip_address === '::1') {
    $ip_address = '127.0.0.1';
}

try {
    // Cari apakah username ini terdaftar sebagai staf untuk mendapatkan id_staf
    $stmt_staf = $pdo->prepare("SELECT id_staf FROM staf WHERE username = ?");
    $stmt_staf->execute([$username]);
    $staf = $stmt_staf->fetch();
    $id_staf = $staf ? intval($staf['id_staf']) : null;

    // Format keterangan audit log
    $keterangan = "Percobaan login gagal untuk username: '$username' dari IP $ip_address";

    // Deteksi sumber dari referer
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $login_source = 'Sistem';
    if (strpos($referer, '/dashboard-dokter/') !== false) {
        $login_source = 'Dokter Dashboard';
    } elseif (strpos($referer, '/dashboard-admin/') !== false) {
        $login_source = 'Admin Dashboard';
    }

    // Insert log keamanan ke audit_log
    // nama_tabel = 'staf', id_record = 0, aksi = 'INSERT'
    $stmt_insert = $pdo->prepare("
        INSERT INTO audit_log (nama_tabel, id_record, aksi, id_staf, keterangan, ip_address, sumber)
        VALUES ('staf', 0, 'INSERT', ?, ?, ?, ?)
    ");
    $stmt_insert->execute([$id_staf, $keterangan, $ip_address, $login_source]);

    echo json_encode([
        'success' => true,
        'message' => 'Kegagalan login berhasil dicatat ke log keamanan.',
        'data' => [
            'username' => $username,
            'ip_address' => $ip_address
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal mencatat log ke database: ' . $e->getMessage()]);
}
?>
