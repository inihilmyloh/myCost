<?php
// api/transactions.php - CRUD REST API for myCost
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Tidak dapat terhubung ke database MySQL.'
    ], 500);
}

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handlePost($conn);
        break;
    case 'PUT':
        handlePut($conn);
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        sendJsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

// ----------------------------------------------------
// GET Handlers
// ----------------------------------------------------
function handleGet($conn) {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $transaction = $stmt->fetch();
        if ($transaction) {
            sendJsonResponse(['status' => 'success', 'data' => $transaction]);
        } else {
            sendJsonResponse(['status' => 'error', 'message' => 'Transaksi tidak ditemukan'], 404);
        }
        return;
    }

    $query = "SELECT * FROM transactions WHERE 1=1";
    $params = [];

    // Filter by type
    if (!empty($_GET['type']) && in_array($_GET['type'], ['pemasukan', 'pengeluaran'])) {
        $query .= " AND type = ?";
        $params[] = $_GET['type'];
    }

    // Filter by category
    if (!empty($_GET['category'])) {
        $query .= " AND category = ?";
        $params[] = $_GET['category'];
    }

    // Filter by month (YYYY-MM)
    if (!empty($_GET['month'])) {
        $query .= " AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
        $params[] = $_GET['month'];
    }

    // Filter by date range
    if (!empty($_GET['start_date'])) {
        $query .= " AND transaction_date >= ?";
        $params[] = $_GET['start_date'];
    }
    if (!empty($_GET['end_date'])) {
        $query .= " AND transaction_date <= ?";
        $params[] = $_GET['end_date'];
    }

    // Search query in notes or category
    if (!empty($_GET['search'])) {
        $query .= " AND (notes LIKE ? OR category LIKE ?)";
        $searchVal = '%' . $_GET['search'] . '%';
        $params[] = $searchVal;
        $params[] = $searchVal;
    }

    // Ordering
    $query .= " ORDER BY transaction_date DESC, id DESC";

    // Pagination (optional)
    if (isset($_GET['limit'])) {
        $limit = max(1, (int)$_GET['limit']);
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        $query .= " LIMIT " . $limit . " OFFSET " . $offset;
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    sendJsonResponse([
        'status' => 'success',
        'count' => count($transactions),
        'data' => $transactions
    ]);
}

// ----------------------------------------------------
// POST Handler (Create or Bulk Sync)
// ----------------------------------------------------
function handlePost($conn) {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    if (!$data) {
        $data = $_POST;
    }

    // Check for bulk sync mode
    if (isset($data['sync']) && is_array($data['items'])) {
        $insertedCount = 0;
        $stmt = $conn->prepare("
            INSERT INTO transactions (type, amount, category, transaction_date, notes, receipt_image_url)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($data['items'] as $item) {
            if (!empty($item['type']) && !empty($item['amount']) && !empty($item['transaction_date'])) {
                $type = $item['type'] === 'pemasukan' ? 'pemasukan' : 'pengeluaran';
                $amount = (float)$item['amount'];
                $category = !empty($item['category']) ? trim($item['category']) : 'Lainnya';
                $date = $item['transaction_date'];
                $notes = !empty($item['notes']) ? trim($item['notes']) : null;
                $receipt = !empty($item['receipt_image_url']) ? trim($item['receipt_image_url']) : null;

                $stmt->execute([$type, $amount, $category, $date, $notes, $receipt]);
                $insertedCount++;
            }
        }

        sendJsonResponse([
            'status' => 'success',
            'message' => "Sinkronisasi berhasil: {$insertedCount} data transaksi tersimpan.",
            'synced_count' => $insertedCount
        ], 201);
        return;
    }

    // Single item creation
    if (empty($data['type']) || !isset($data['amount']) || empty($data['transaction_date'])) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Field type, amount, dan transaction_date wajib diisi.'
        ], 400);
        return;
    }

    $type = in_array($data['type'], ['pemasukan', 'pengeluaran']) ? $data['type'] : 'pengeluaran';
    $amount = (float)$data['amount'];
    if ($amount <= 0) {
        sendJsonResponse(['status' => 'error', 'message' => 'Nominal harus lebih besar dari 0.'], 400);
        return;
    }

    $category = !empty($data['category']) ? trim($data['category']) : 'Lainnya';
    $transaction_date = $data['transaction_date'];
    $notes = !empty($data['notes']) ? trim($data['notes']) : null;
    $receipt_image_url = !empty($data['receipt_image_url']) ? trim($data['receipt_image_url']) : null;

    $stmt = $conn->prepare("
        INSERT INTO transactions (type, amount, category, transaction_date, notes, receipt_image_url)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $success = $stmt->execute([$type, $amount, $category, $transaction_date, $notes, $receipt_image_url]);

    if ($success) {
        $newId = (int)$conn->lastInsertId();
        sendJsonResponse([
            'status' => 'success',
            'message' => 'Transaksi berhasil ditambahkan.',
            'data' => [
                'id' => $newId,
                'type' => $type,
                'amount' => $amount,
                'category' => $category,
                'transaction_date' => $transaction_date,
                'notes' => $notes,
                'receipt_image_url' => $receipt_image_url
            ]
        ], 201);
    } else {
        sendJsonResponse(['status' => 'error', 'message' => 'Gagal menyimpan transaksi.'], 500);
    }
}

// ----------------------------------------------------
// PUT Handler (Update)
// ----------------------------------------------------
function handlePut($conn) {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    if (empty($data['id'])) {
        sendJsonResponse(['status' => 'error', 'message' => 'ID transaksi wajib disertakan.'], 400);
        return;
    }

    $id = (int)$data['id'];

    // Check existing
    $checkStmt = $conn->prepare("SELECT id FROM transactions WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendJsonResponse(['status' => 'error', 'message' => 'Transaksi tidak ditemukan.'], 404);
        return;
    }

    $type = in_array($data['type'], ['pemasukan', 'pengeluaran']) ? $data['type'] : 'pengeluaran';
    $amount = (float)$data['amount'];
    $category = !empty($data['category']) ? trim($data['category']) : 'Lainnya';
    $transaction_date = !empty($data['transaction_date']) ? $data['transaction_date'] : date('Y-m-d');
    $notes = isset($data['notes']) ? trim($data['notes']) : null;
    $receipt_image_url = isset($data['receipt_image_url']) ? trim($data['receipt_image_url']) : null;

    $stmt = $conn->prepare("
        UPDATE transactions
        SET type = ?, amount = ?, category = ?, transaction_date = ?, notes = ?, receipt_image_url = ?
        WHERE id = ?
    ");
    $success = $stmt->execute([$type, $amount, $category, $transaction_date, $notes, $receipt_image_url, $id]);

    if ($success) {
        sendJsonResponse([
            'status' => 'success',
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => [
                'id' => $id,
                'type' => $type,
                'amount' => $amount,
                'category' => $category,
                'transaction_date' => $transaction_date,
                'notes' => $notes,
                'receipt_image_url' => $receipt_image_url
            ]
        ]);
    } else {
        sendJsonResponse(['status' => 'error', 'message' => 'Gagal memperbarui transaksi.'], 500);
    }
}

// ----------------------------------------------------
// DELETE Handler
// ----------------------------------------------------
function handleDelete($conn) {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    $id = null;
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
    } elseif (!empty($data['id'])) {
        $id = (int)$data['id'];
    }

    if (!$id) {
        sendJsonResponse(['status' => 'error', 'message' => 'ID transaksi wajib disertakan.'], 400);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
    $success = $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendJsonResponse([
            'status' => 'success',
            'message' => 'Transaksi berhasil dihapus.',
            'id' => $id
        ]);
    } else {
        sendJsonResponse(['status' => 'error', 'message' => 'Transaksi tidak ditemukan atau sudah dihapus.'], 404);
    }
}
