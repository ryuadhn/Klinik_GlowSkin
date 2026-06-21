<?php
/**
 * ============================================================
 * FILE: dashboard-admin/api_proses_transaksi.php
 * DESKRIPSI: Endpoint AJAX untuk Admin memproses billing kasir
 *            dan menandai pembayaran kunjungan menjadi LUNAS.
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

$id_kunjungan = intval($_POST['id_kunjungan'] ?? 0);
$id_metode = intval($_POST['id_metode'] ?? 0);

if ($id_kunjungan <= 0 || $id_metode <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter input tidak valid.']);
    exit;
}

try {
    // Mulai Database Transaction (TCL) untuk keamanan data finansial
    $pdo->beginTransaction();

    // 1. Verifikasi apakah kunjungan ada
    $stmt_kunjungan = $pdo->prepare("SELECT id_kunjungan, kode_kunjungan FROM kunjungan WHERE id_kunjungan = ?");
    $stmt_kunjungan->execute([$id_kunjungan]);
    $kunjungan = $stmt_kunjungan->fetch();

    if (!$kunjungan) {
        throw new Exception("Data kunjungan tidak ditemukan.");
    }

    // 2. Hitung total biaya layanan & resep secara live
    $stmt_layanan = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM detail_layanan WHERE id_kunjungan = ?");
    $stmt_layanan->execute([$id_kunjungan]);
    $total_layanan = floatval($stmt_layanan->fetchColumn());

    $stmt_obat = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM resep_obat WHERE id_kunjungan = ?");
    $stmt_obat->execute([$id_kunjungan]);
    $total_obat = floatval($stmt_obat->fetchColumn());

    $grand_total = $total_layanan + $total_obat;

    if ($grand_total <= 0) {
        // Jika belum ada tindakan/resep, anggap biaya konsultasi dasar (150.000)
        // dari layanan LYN-001 (Consultation)
        $total_layanan = 150000.00;
        $grand_total = 150000.00;
    }

    // 3. Cek apakah record pembayaran sudah pernah dibuat (misal status belum_lunas)
    $stmt_check = $pdo->prepare("SELECT id_pembayaran, kode_pembayaran, status_bayar FROM pembayaran WHERE id_kunjungan = ?");
    $stmt_check->execute([$id_kunjungan]);
    $existing_pay = $stmt_check->fetch();

    $tanggal_bayar = date('Y-m-d H:i:s');

    if ($existing_pay) {
        if ($existing_pay['status_bayar'] === 'lunas') {
            throw new Exception("Kunjungan ini sudah lunas sebelumnya.");
        }

        // Update status menjadi Lunas
        $stmt_update = $pdo->prepare("
            UPDATE pembayaran 
            SET total_layanan = ?, total_obat = ?, grand_total = ?, id_metode = ?, status_bayar = 'lunas', tanggal_bayar = ?
            WHERE id_kunjungan = ?
        ");
        $stmt_update->execute([$total_layanan, $total_obat, $grand_total, $id_metode, $tanggal_bayar, $id_kunjungan]);
        $kode_pembayaran = $existing_pay['kode_pembayaran'];
    } else {
        // Buat kode pembayaran unik format PAY-YYYYMMDD-[id_kunjungan]
        $kode_pembayaran = 'PAY-' . date('Ymd') . '-' . sprintf('%03d', $id_kunjungan);

        // Insert pembayaran baru
        $stmt_insert = $pdo->prepare("
            INSERT INTO pembayaran (kode_pembayaran, id_kunjungan, total_layanan, total_obat, grand_total, id_metode, status_bayar, tanggal_bayar)
            VALUES (?, ?, ?, ?, ?, ?, 'lunas', ?)
        ");
        $stmt_insert->execute([$kode_pembayaran, $id_kunjungan, $total_layanan, $total_obat, $grand_total, $id_metode, $tanggal_bayar]);
    }

    // Commit transaksi ke database jika semua step berhasil
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pembayaran berhasil diproses dan dikonfirmasi Lunas.',
        'data' => [
            'kode_pembayaran' => $kode_pembayaran,
            'grand_total' => $grand_total,
            'tanggal_bayar' => $tanggal_bayar
        ]
    ]);

} catch (Exception $e) {
    // Batalkan seluruh operasi jika ada kegagalan
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()
    ]);
}
