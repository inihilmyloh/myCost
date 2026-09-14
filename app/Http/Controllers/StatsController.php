<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class StatsController extends Controller
{
    /**
     * Get financial statistics and chart data for the active user.
     */
    public function index(Request $request)
    {
        $userId = $request->header('X-User-Id') ?: (Auth::id() ?: 1);
        $month = $request->input('month', Carbon::now()->format('Y-m'));

        // 1. Overall Totals for user
        $overall = Transaction::forUser($userId)->selectRaw("
            COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as total_expense
        ")->first();

        $totalIncome = (float)($overall->total_income ?? 0);
        $totalExpense = (float)($overall->total_expense ?? 0);
        $netBalance = $totalIncome - $totalExpense;

        // 2. Selected Month Totals for user
        $monthData = Transaction::forUser($userId)->month($month)->selectRaw("
            COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as month_income,
            COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as month_expense,
            COUNT(*) as transaction_count
        ")->first();

        $monthIncome = (float)($monthData->month_income ?? 0);
        $monthExpense = (float)($monthData->month_expense ?? 0);
        $monthNet = $monthIncome - $monthExpense;
        $transactionCount = (int)($monthData->transaction_count ?? 0);

        // 3. Category Breakdown
        $categories = Transaction::forUser($userId)->month($month)
            ->where('type', 'pengeluaran')
            ->selectRaw('category, SUM(amount) as total_amount, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();

        if ($categories->isEmpty()) {
            $categories = Transaction::forUser($userId)
                ->where('type', 'pengeluaran')
                ->selectRaw('category, SUM(amount) as total_amount, COUNT(*) as count')
                ->groupBy('category')
                ->orderByDesc('total_amount')
                ->limit(7)
                ->get();
        }

        // 4. Monthly Trend (Last 6 Months)
        $monthlyTrend = Transaction::forUser($userId)
            ->where('transaction_date', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->selectRaw("
                DATE_FORMAT(transaction_date, '%Y-%m') as month_label,
                COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as expense
            ")
            ->groupBy(DB::raw("DATE_FORMAT(transaction_date, '%Y-%m')"))
            ->orderBy('month_label', 'asc')
            ->get();

        // 5. Recent 5 Transactions with items
        $recentTransactions = Transaction::with('items')
            ->forUser($userId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'net_balance' => $netBalance,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
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
    }
}
