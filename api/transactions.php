<?php
// api/transactions.php - CRUD REST API with Itemized Breakdown & Multi-User Support
require_once __DIR__ . '/../config/database.php';

session_start();

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    sendJsonResponse(['status' => 'error', 'message' => 'Tidak dapat terhubung ke database MySQL.'], 500);
}

function getUserId() {
    if (!empty($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    $headers = getallheaders();
    if (!empty($headers['X-User-Id'])) return (int)$headers['X-User-Id'];
    if (!empty($headers['x-user-id'])) return (int)$headers['x-user-id'];
    return 1;
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

function handleGet($conn) {
    $userId = getUserId();

    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $transaction = $stmt->fetch();
        if ($transaction) {
            $itemStmt = $conn->prepare("SELECT * FROM transaction_items WHERE transaction_id = ?");
            $itemStmt->execute([$id]);
            $transaction['items'] = $itemStmt->fetchAll();
            sendJsonResponse(['status' => 'success', 'data' => $transaction]);
        } else {
            sendJsonResponse(['status' => 'error', 'message' => 'Transaksi tidak ditemukan'], 404);
        }
        return;
    }

    $query = "SELECT * FROM transactions WHERE user_id = ?";
    $params = [$userId];

    if (!empty($_GET['type']) && in_array($_GET['type'], ['pemasukan', 'pengeluaran'])) {
        $query .= " AND type = ?";
        $params[] = $_GET['type'];
    }

    if (!empty($_GET['category'])) {
        $query .= " AND category = ?";
        $params[] = $_GET['category'];
    }

    if (!empty($_GET['month'])) {
        $query .= " AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
        $params[] = $_GET['month'];
    }

    if (!empty($_GET['start_date'])) {
        $query .= " AND transaction_date >= ?";
        $params[] = $_GET['start_date'];
    }
    if (!empty($_GET['end_date'])) {
        $query .= " AND transaction_date <= ?";
        $params[] = $_GET['end_date'];
    }

    if (!empty($_GET['search'])) {
        $query .= " AND (notes LIKE ? OR category LIKE ?)";
        $searchVal = '%' . $_GET['search'] . '%';
        $params[] = $searchVal;
        $params[] = $searchVal;
    }

    $query .= " ORDER BY transaction_date DESC, id DESC";

    if (isset($_GET['limit'])) {
        $limit = max(1, (int)$_GET['limit']);
        $query .= " LIMIT " . $limit;
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    // Attach items for each transaction
    foreach ($transactions as &$t) {
        $itemStmt = $conn->prepare("SELECT * FROM transaction_items WHERE transaction_id = ?");
        $itemStmt->execute([(int)$t['id']]);
        $t['items'] = $itemStmt->fetchAll();
    }

    sendJsonResponse([
        'status' => 'success',
        'count' => count($transactions),
        'data' => $transactions
    ]);
}

function handlePost($conn) {
    $userId = getUserId();
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true) ?: $_POST;

    // Bulk sync
    if (isset($data['sync']) && is_array($data['items'])) {
        $synced = 0;
        $stmt = $conn->prepare("
            INSERT INTO transactions (user_id, type, amount, subtotal, discount, tax, category, transaction_date, notes, receipt_image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $itemStmt = $conn->prepare("
            INSERT INTO transaction_items (transaction_id, item_name, qty, unit_price, discount, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($data['items'] as $item) {
            if (!empty($item['type']) && !empty($item['amount']) && !empty($item['transaction_date'])) {
                $subtotal = isset($item['subtotal']) ? (float)$item['subtotal'] : (float)$item['amount'];
                $discount = isset($item['discount']) ? (float)$item['discount'] : 0;
                $tax = isset($item['tax']) ? (float)$item['tax'] : 0;
                $stmt->execute([
                    $userId,
                    $item['type'],
                    (float)$item['amount'],
                    $subtotal,
                    $discount,
                    $tax,
                    !empty($item['category']) ? trim($item['category']) : 'Lainnya',
                    $item['transaction_date'],
                    !empty($item['notes']) ? trim($item['notes']) : null,
                    !empty($item['receipt_image_url']) ? trim($item['receipt_image_url']) : null
                ]);
                $newId = (int)$conn->lastInsertId();

                if (!empty($item['items']) && is_array($item['items'])) {
                    foreach ($item['items'] as $it) {
                        if (!empty($it['item_name'])) {
                            $itemStmt->execute([
                                $newId,
                                trim($it['item_name']),
                                (float)($it['qty'] ?? 1),
                                (float)($it['unit_price'] ?? 0),
                                (float)($it['discount'] ?? 0),
                                (float)($it['total_price'] ?? 0)
                            ]);
                        }
                    }
                }
                $synced++;
            }
        }

        sendJsonResponse(['status' => 'success', 'message' => "{$synced} transaksi disinkronkan.", 'synced_count' => $synced], 201);
        return;
    }

    if (empty($data['type']) || !isset($data['amount']) || empty($data['transaction_date'])) {
        sendJsonResponse(['status' => 'error', 'message' => 'Field type, amount, dan transaction_date wajib diisi.'], 400);
        return;
    }

    $type = in_array($data['type'], ['pemasukan', 'pengeluaran']) ? $data['type'] : 'pengeluaran';
    $amount = (float)$data['amount'];
    $subtotal = isset($data['subtotal']) ? (float)$data['subtotal'] : $amount;
    $discount = isset($data['discount']) ? (float)$data['discount'] : 0;
    $tax = isset($data['tax']) ? (float)$data['tax'] : 0;
    $category = !empty($data['category']) ? trim($data['category']) : 'Lainnya';
    $transaction_date = $data['transaction_date'];
    $notes = !empty($data['notes']) ? trim($data['notes']) : null;
    $receipt_image_url = !empty($data['receipt_image_url']) ? trim($data['receipt_image_url']) : null;

    $stmt = $conn->prepare("
        INSERT INTO transactions (user_id, type, amount, subtotal, discount, tax, category, transaction_date, notes, receipt_image_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $type, $amount, $subtotal, $discount, $tax, $category, $transaction_date, $notes, $receipt_image_url]);
    $newId = (int)$conn->lastInsertId();

    // Insert Items
    if (!empty($data['items']) && is_array($data['items'])) {
        $itemStmt = $conn->prepare("
            INSERT INTO transaction_items (transaction_id, item_name, qty, unit_price, discount, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['items'] as $it) {
            if (!empty($it['item_name'])) {
                $itemStmt->execute([
                    $newId,
                    trim($it['item_name']),
                    (float)($it['qty'] ?? 1),
                    (float)($it['unit_price'] ?? 0),
                    (float)($it['discount'] ?? 0),
                    (float)($it['total_price'] ?? 0)
                ]);
            }
        }
    }

    sendJsonResponse([
        'status' => 'success',
        'message' => 'Transaksi berhasil dicatat!',
        'data' => ['id' => $newId, 'amount' => $amount, 'transaction_date' => $transaction_date]
    ], 201);
}

function handlePut($conn) {
    $userId = getUserId();
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true) ?: $_POST;

    if (empty($data['id'])) {
        sendJsonResponse(['status' => 'error', 'message' => 'ID transaksi wajib.'], 400);
    }

    $id = (int)$data['id'];
    $type = in_array($data['type'], ['pemasukan', 'pengeluaran']) ? $data['type'] : 'pengeluaran';
    $amount = (float)$data['amount'];
    $subtotal = isset($data['subtotal']) ? (float)$data['subtotal'] : $amount;
    $discount = isset($data['discount']) ? (float)$data['discount'] : 0;
    $tax = isset($data['tax']) ? (float)$data['tax'] : 0;
    $category = !empty($data['category']) ? trim($data['category']) : 'Lainnya';
    $transaction_date = $data['transaction_date'];
    $notes = isset($data['notes']) ? trim($data['notes']) : null;
    $receipt_image_url = isset($data['receipt_image_url']) ? trim($data['receipt_image_url']) : null;

    $stmt = $conn->prepare("
        UPDATE transactions
        SET type = ?, amount = ?, subtotal = ?, discount = ?, tax = ?, category = ?, transaction_date = ?, notes = ?, receipt_image_url = ?
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$type, $amount, $subtotal, $discount, $tax, $category, $transaction_date, $notes, $receipt_image_url, $id, $userId]);

    // Update items
    if (isset($data['items']) && is_array($data['items'])) {
        $conn->prepare("DELETE FROM transaction_items WHERE transaction_id = ?")->execute([$id]);
        $itemStmt = $conn->prepare("
            INSERT INTO transaction_items (transaction_id, item_name, qty, unit_price, discount, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['items'] as $it) {
            if (!empty($it['item_name'])) {
                $itemStmt->execute([
                    $id,
                    trim($it['item_name']),
                    (float)($it['qty'] ?? 1),
                    (float)($it['unit_price'] ?? 0),
                    (float)($it['discount'] ?? 0),
                    (float)($it['total_price'] ?? 0)
                ]);
            }
        }
    }

    sendJsonResponse(['status' => 'success', 'message' => 'Transaksi berhasil diperbarui.']);
}

function handleDelete($conn) {
    $userId = getUserId();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        $raw = json_decode(file_get_contents("php://input"), true);
        if (!empty($raw['id'])) $id = (int)$raw['id'];
    }

    if (!$id) {
        sendJsonResponse(['status' => 'error', 'message' => 'ID transaksi wajib.'], 400);
    }

    $conn->prepare("DELETE FROM transaction_items WHERE transaction_id = ?")->execute([$id]);
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);

    sendJsonResponse(['status' => 'success', 'message' => 'Transaksi berhasil dihapus.']);
}
