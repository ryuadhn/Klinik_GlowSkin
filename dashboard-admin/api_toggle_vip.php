<?php
/**
 * ============================================================
 * FILE: dashboard-admin/api_toggle_vip.php
 * DESKRIPSI: Endpoint AJAX untuk Admin mengubah status
 *            kategori pasien antara 'vip' dan 'reguler'.
 * ============================================================
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../koneksi.php';

// Hanya terima POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$id_pasien = intval($_POST['id_pasien'] ?? 0);
$kategori_baru = $_POST['kategori'] ?? ''; // 'vip', 'member', atau 'reguler'

if ($id_pasien <= 0 || !in_array($kategori_baru, ['vip', 'member', 'reguler'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE pasien SET kategori = ? WHERE id_pasien = ?");
    $stmt->execute([$kategori_baru, $id_pasien]);

    $label_map = ['vip' => 'VIP', 'member' => 'Member', 'reguler' => 'Reguler'];
    $label = $label_map[$kategori_baru] ?? 'Reguler';
    echo json_encode([
        'success'       => true,
        'message'       => "Status pasien berhasil diubah menjadi $label",
        'kategori_baru' => $kategori_baru,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
