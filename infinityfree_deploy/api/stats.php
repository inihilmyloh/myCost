<?php
// api/stats.php - Summary Statistics & Analytics API
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Tidak dapat terhubung ke database MySQL.'
    ], 500);
}

$month = !empty($_GET['month']) ? $_GET['month'] : date('Y-m');

try {
    // 1. Overall Totals
    $overallStmt = $conn->query("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as total_expense
        FROM transactions
    ");
    $overall = $overallStmt->fetch();
    $totalIncomeAll = (float)$overall['total_income'];
    $totalExpenseAll = (float)$overall['total_expense'];
    $netBalance = $totalIncomeAll - $totalExpenseAll;

    // 2. Selected Month Totals
    $monthStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as month_income,
            COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as month_expense,
            COUNT(*) as transaction_count
        FROM transactions
        WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ?
    ");
    $monthStmt->execute([$month]);
    $monthData = $monthStmt->fetch();
    $monthIncome = (float)$monthData['month_income'];
    $monthExpense = (float)$monthData['month_expense'];
    $monthNet = $monthIncome - $monthExpense;
    $transactionCount = (int)$monthData['transaction_count'];

    // 3. Category Breakdown (Expenses for the selected month or overall)
    $catStmt = $conn->prepare("
        SELECT 
            category,
            SUM(amount) as total_amount,
            COUNT(*) as count
        FROM transactions
        WHERE type = 'pengeluaran' 
          AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
        GROUP BY category
        ORDER BY total_amount DESC
    ");
    $catStmt->execute([$month]);
    $categories = $catStmt->fetchAll();

    // Fallback if empty in this month, get all-time expense categories
    if (empty($categories)) {
        $catStmtAll = $conn->query("
            SELECT 
                category,
                SUM(amount) as total_amount,
                COUNT(*) as count
            FROM transactions
            WHERE type = 'pengeluaran'
            GROUP BY category
            ORDER BY total_amount DESC
            LIMIT 7
        ");
        $categories = $catStmtAll->fetchAll();
    }

    // 4. Monthly Trend (Last 6 Months)
    $trendStmt = $conn->query("
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month_label,
            COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as expense
        FROM transactions
        WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month_label ASC
    ");
    $monthlyTrend = $trendStmt->fetchAll();

    // 5. Recent 5 Transactions
    $recentStmt = $conn->query("
        SELECT * FROM transactions 
        ORDER BY transaction_date DESC, id DESC 
        LIMIT 5
    ");
    $recentTransactions = $recentStmt->fetchAll();

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
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Error mengambil statistik: ' . $e->getMessage()
    ], 500);
}
