<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BudgetController extends Controller
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
     * Get list of budgets for a specific month with spent amounts & percentages.
     */
    public function index(Request $request)
    {
        $userId = $this->getUserId($request);
        $month = $request->query('month', date('Y-m'));

        $budgets = Budget::forUser($userId)->where('month_year', $month)->get();

        // Calculate actual expense spent for each category in this month
        $expenses = Transaction::forUser($userId)
            ->month($month)
            ->type('pengeluaran')
            ->selectRaw('category, SUM(amount) as total_spent')
            ->groupBy('category')
            ->pluck('total_spent', 'category')
            ->toArray();

        $budgetDetails = $budgets->map(function ($b) use ($expenses) {
            $spent = (float)($expenses[$b->category] ?? 0);
            $limit = (float)$b->amount_limit;
            $remaining = max(0, $limit - $spent);
            $percentage = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0;

            return [
                'id' => $b->id,
                'category' => $b->category,
                'amount_limit' => $limit,
                'total_spent' => $spent,
                'remaining' => $remaining,
                'percentage' => $percentage,
                'is_over_budget' => $spent > $limit,
                'month_year' => $b->month_year,
            ];
        });

        $totalBudget = $budgets->sum('amount_limit');
        $totalSpentInBudgets = $budgetDetails->sum('total_spent');

        return response()->json([
            'status' => 'success',
            'data' => [
                'budgets' => $budgetDetails,
                'summary' => [
                    'total_budget' => (float)$totalBudget,
                    'total_spent' => (float)$totalSpentInBudgets,
                    'total_remaining' => max(0, $totalBudget - $totalSpentInBudgets),
                    'overall_percentage' => $totalBudget > 0 ? round(($totalSpentInBudgets / $totalBudget) * 100, 1) : 0,
                ]
            ]
        ]);
    }

    /**
     * Store or update budget limit for a category.
     */
    public function store(Request $request)
    {
        $userId = $this->getUserId($request);

        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:100',
            'amount_limit' => 'required|numeric|min:1000',
            'month_year' => 'nullable|string|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $monthYear = $request->month_year ?: date('Y-m');

        $budget = Budget::updateOrCreate(
            [
                'user_id' => $userId,
                'category' => $request->category,
                'month_year' => $monthYear,
            ],
            [
                'amount_limit' => (float)$request->amount_limit,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => "Anggaran kategori {$request->category} berhasil disimpan.",
            'data' => $budget
        ]);
    }

    /**
     * Delete budget.
     */
    public function destroy(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $userId = $this->getUserId($request);
        $budget = Budget::forUser($userId)->find($id);

        if (!$budget) {
            return response()->json(['status' => 'error', 'message' => 'Anggaran tidak ditemukan.'], 404);
        }

        $budget->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Anggaran berhasil dihapus.',
            'id' => $id
        ]);
    }
}
