<?php
// api/stats.php - Summary Statistics & Analytics API (Multi-User)
require_once __DIR__ . '/../config/database.php';

session_start();

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    sendJsonResponse(['status' => 'error', 'message' => 'Tidak dapat terhubung ke database MySQL.'], 500);
}

function getUserId() {
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    $headers = getallheaders();
    if (!empty($headers['X-User-Id'])) return (int)$headers['X-User-Id'];
    if (!empty($headers['x-user-id'])) return (int)$headers['x-user-id'];
    return 1;
}

$userId = getUserId();
$month = !empty($_GET['month']) ? $_GET['month'] : date('Y-m');

try {
    // 1. Overall Totals
    $overallStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as total_expense
        FROM transactions
        WHERE user_id = ?
    ");
    $overallStmt->execute([$userId]);
    $overall = $overallStmt->fetch();
    $totalIncomeAll = (float)($overall['total_income'] ?? 0);
    $totalExpenseAll = (float)($overall['total_expense'] ?? 0);
    $netBalance = $totalIncomeAll - $totalExpenseAll;

    // 2. Selected Month Totals
    $monthStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as month_income,
            COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as month_expense,
            COUNT(*) as transaction_count
        FROM transactions
        WHERE user_id = ? AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
    ");
    $monthStmt->execute([$userId, $month]);
    $monthData = $monthStmt->fetch();
    $monthIncome = (float)($monthData['month_income'] ?? 0);
    $monthExpense = (float)($monthData['month_expense'] ?? 0);
    $monthNet = $monthIncome - $monthExpense;
    $transactionCount = (int)($monthData['transaction_count'] ?? 0);

    // 3. Category Breakdown
    $catStmt = $conn->prepare("
        SELECT 
            category,
            SUM(amount) as total_amount,
            COUNT(*) as count
        FROM transactions
        WHERE user_id = ? AND type = 'pengeluaran' 
          AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
        GROUP BY category
        ORDER BY total_amount DESC
    ");
    $catStmt->execute([$userId, $month]);
    $categories = $catStmt->fetchAll();

    if (empty($categories)) {
        $catStmtAll = $conn->prepare("
            SELECT 
                category,
                SUM(amount) as total_amount,
                COUNT(*) as count
            FROM transactions
            WHERE user_id = ? AND type = 'pengeluaran'
            GROUP BY category
            ORDER BY total_amount DESC
            LIMIT 7
        ");
        $catStmtAll->execute([$userId]);
        $categories = $catStmtAll->fetchAll();
    }

    // 4. Monthly Trend (Last 6 Months)
    $trendStmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month_label,
            COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as expense
        FROM transactions
        WHERE user_id = ? AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month_label ASC
    ");
    $trendStmt->execute([$userId]);
    $monthlyTrend = $trendStmt->fetchAll();

    // 5. Recent 5 Transactions with line items
    $recentStmt = $conn->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ?
        ORDER BY transaction_date DESC, id DESC 
        LIMIT 5
    ");
    $recentStmt->execute([$userId]);
    $recentTransactions = $recentStmt->fetchAll();

    foreach ($recentTransactions as &$t) {
        $itemStmt = $conn->prepare("SELECT * FROM transaction_items WHERE transaction_id = ?");
        $itemStmt->execute([(int)$t['id']]);
        $t['items'] = $itemStmt->fetchAll();
    }

    sendJsonResponse([
        'status' => 'success',
        'data' => [
            'summary' => [
                'net_balance' => $netBalance,
                'total_income' => $totalIncomeAll,
                'total_expense' => $totalExpenseAll,
                'month' => $month,
                'month_income' => $monthIncome,
                'month_expense' => $monthExpense,
                'month_net' => $monthNet,
                'month_transaction_count' => $transactionCount
            ],
            'categories' => $categories,
            'monthly_trend' => $monthlyTrend,
            'recent_transactions' => $recentTransactions
        ]
    ]);

} catch (PDOException $e) {
    sendJsonResponse(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()], 500);
}
