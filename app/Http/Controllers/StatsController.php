<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\PiggyBank;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class StatsController extends Controller
{
    private function getUserId(Request $request)
    {
        $headerId = $request->header('X-User-Id');
        if ($headerId && User::where('id', $headerId)->exists()) {
            return (int)$headerId;
        }
        if (auth()->check()) {
            return auth()->id();
        }
        return null;
    }

    /**
     * Get financial statistics, Net Worth, Accounts, Budgets, and chart data.
     */
    public function index(Request $request)
    {
        $userId = $this->getUserId($request);
        $month = $request->input('month', Carbon::now()->format('Y-m'));

        // 1. Accounts & Total Net Worth
        $accounts = Account::forUser($userId)->where('is_active', true)->get();
        if ($accounts->isEmpty()) {
            $defaultAccounts = [
                ['name' => 'Kas Tunai / Dompet', 'type' => 'cash', 'balance' => 0, 'icon' => 'fa-wallet', 'color' => '#10b981'],
                ['name' => 'Bank BCA', 'type' => 'bank', 'balance' => 0, 'icon' => 'fa-building-columns', 'color' => '#059669'],
                ['name' => 'GoPay / E-Wallet', 'type' => 'ewallet', 'balance' => 0, 'icon' => 'fa-mobile-screen-button', 'color' => '#34d399'],
            ];
            foreach ($defaultAccounts as $acc) {
                Account::create(array_merge($acc, ['user_id' => $userId]));
            }
            $accounts = Account::forUser($userId)->where('is_active', true)->get();
        }
        $totalNetWorth = (float)$accounts->sum('balance');

        // 2. Selected Month Totals
        $monthData = Transaction::forUser($userId)->month($month)->selectRaw("
            COALESCE(SUM(CASE WHEN type = 'pemasukan' THEN amount ELSE 0 END), 0) as month_income,
            COALESCE(SUM(CASE WHEN type = 'pengeluaran' THEN amount ELSE 0 END), 0) as month_expense,
            COUNT(*) as transaction_count
        ")->first();

        $monthIncome = (float)($monthData->month_income ?? 0);
        $monthExpense = (float)($monthData->month_expense ?? 0);
        $monthNet = $monthIncome - $monthExpense;
        $transactionCount = (int)($monthData->transaction_count ?? 0);

        // 3. Category Breakdown (Expenses)
        $categories = Transaction::forUser($userId)->month($month)
            ->where('type', 'pengeluaran')
            ->selectRaw('category, SUM(amount) as total_amount, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();

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

        // 5. Budgets Progress
        $budgets = Budget::forUser($userId)->where('month_year', $month)->get();
        $expensesMap = $categories->pluck('total_amount', 'category')->toArray();

        $budgetList = $budgets->map(function ($b) use ($expensesMap) {
            $spent = (float)($expensesMap[$b->category] ?? 0);
            $limit = (float)$b->amount_limit;
            return [
                'id' => $b->id,
                'category' => $b->category,
                'amount_limit' => $limit,
                'total_spent' => $spent,
                'remaining' => max(0, $limit - $spent),
                'percentage' => $limit > 0 ? round(($spent / $limit) * 100, 1) : 0,
            ];
        });

        // 6. Savings Goals (Piggy Banks)
        $piggyBanks = PiggyBank::forUser($userId)->limit(4)->get()->map(function ($g) {
            $target = (float)$g->target_amount;
            $current = (float)$g->current_amount;
            return [
                'id' => $g->id,
                'name' => $g->name,
                'target_amount' => $target,
                'current_amount' => $current,
                'percentage' => $target > 0 ? min(100, round(($current / $target) * 100, 1)) : 0,
            ];
        });

        // 7. Recent Transactions
        $recentTransactions = Transaction::with(['items', 'account', 'destinationAccount'])
            ->forUser($userId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'net_worth' => $totalNetWorth,
                    'net_balance' => $totalNetWorth,
                    'month' => $month,
                    'month_income' => $monthIncome,
                    'month_expense' => $monthExpense,
                    'month_net' => $monthNet,
                    'month_transaction_count' => $transactionCount
                ],
                'accounts' => $accounts,
                'budgets' => $budgetList,
                'piggy_banks' => $piggyBanks,
                'categories' => $categories,
                'monthly_trend' => $monthlyTrend,
                'recent_transactions' => $recentTransactions
            ]
        ]);
    }
}
