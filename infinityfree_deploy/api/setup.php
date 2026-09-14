<?php
// api/setup.php - Check MySQL status and seed sample data
require_once __DIR__ . '/../config/database.php';

header("Content-Type: application/json; charset=UTF-8");

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Gagal terhubung ke MySQL. Pastikan MySQL di Laragon sudah berjalan (Start All).',
        'connected' => false
    ], 500);
}

// Check transaction count
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM transactions");
    $count = $stmt->fetch()['total'];

    // If empty, insert sample data
    $seeded = false;
    if ($count == 0) {
        $insertQuery = "
            INSERT INTO `transactions` (`type`, `amount`, `category`, `transaction_date`, `notes`, `receipt_image_url`) VALUES
            ('pemasukan', 5000000.00, 'Gaji', CURDATE(), 'Gaji bulanan', NULL),
            ('pengeluaran', 45000.00, 'Makanan & Minuman', CURDATE(), 'Makan siang ayam geprek', NULL),
            ('pengeluaran', 150000.00, 'Belanja', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Belanja bulanan minimarket', NULL),
            ('pengeluaran', 50000.00, 'Transportasi', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Bensin motor', NULL),
            ('pemasukan', 500000.00, 'Freelance', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Project desain logo', NULL),
            ('pengeluaran', 75000.00, 'Hiburan', DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Nonton bioskop', NULL);
        ";
        $conn->exec($insertQuery);
        $stmt = $conn->query("SELECT COUNT(*) as total FROM transactions");
        $count = $stmt->fetch()['total'];
        $seeded = true;
    }

    sendJsonResponse([
        'status' => 'success',
        'message' => 'Database MySQL terhubung dan siap digunakan!',
        'connected' => true,
        'table_ready' => true,
        'total_transactions' => (int)$count,
        'seeded' => $seeded
    ]);

} catch (PDOException $e) {
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Error query database: ' . $e->getMessage(),
        'connected' => true,
        'table_ready' => false
    ], 500);
}
