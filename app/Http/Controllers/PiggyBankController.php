<?php

namespace App\Http\Controllers;

use App\Models\PiggyBank;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PiggyBankController extends Controller
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
     * Get all piggy banks for user.
     */
    public function index(Request $request)
    {
        $userId = $this->getUserId($request);
        $goals = PiggyBank::forUser($userId)->with('account')->get();

        $goalDetails = $goals->map(function ($g) {
            $target = (float)$g->target_amount;
            $current = (float)$g->current_amount;
            $percentage = $target > 0 ? min(100, round(($current / $target) * 100, 1)) : 0;
            $remaining = max(0, $target - $current);

            return [
                'id' => $g->id,
                'name' => $g->name,
                'target_amount' => $target,
                'current_amount' => $current,
                'remaining' => $remaining,
                'percentage' => $percentage,
                'target_date' => $g->target_date ? $g->target_date->format('Y-m-d') : null,
                'account' => $g->account ? ['id' => $g->account->id, 'name' => $g->account->name] : null,
                'notes' => $g->notes,
                'is_completed' => $current >= $target && $target > 0,
            ];
        });

        $totalSaved = $goals->sum('current_amount');
        $totalTarget = $goals->sum('target_amount');

        return response()->json([
            'status' => 'success',
            'data' => [
                'piggy_banks' => $goalDetails,
                'summary' => [
                    'total_saved' => (float)$totalSaved,
                    'total_target' => (float)$totalTarget,
                    'total_remaining' => max(0, $totalTarget - $totalSaved),
                ]
            ]
        ]);
    }

    /**
     * Store new piggy bank.
     */
    public function store(Request $request)
    {
        $userId = $this->getUserId($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'target_amount' => 'required|numeric|min:1000',
            'current_amount' => 'nullable|numeric|min:0',
            'target_date' => 'nullable|date',
            'account_id' => 'nullable|exists:accounts,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $goal = PiggyBank::create([
            'user_id' => $userId,
            'name' => $request->name,
            'target_amount' => (float)$request->target_amount,
            'current_amount' => (float)($request->current_amount ?? 0),
            'target_date' => $request->target_date,
            'account_id' => $request->account_id,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Celengan / Target Tabungan berhasil dibuat!',
            'data' => $goal
        ], 201);
    }

    /**
     * Add money to piggy bank (or subtract).
     */
    public function adjustMoney(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $userId = $this->getUserId($request);
        $goal = PiggyBank::forUser($userId)->find($id);

        if (!$goal) {
            return response()->json(['status' => 'error', 'message' => 'Celengan tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric', // positive to add, negative to withdraw
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $amount = (float)$request->amount;
        $newAmount = max(0, $goal->current_amount + $amount);
        $goal->update(['current_amount' => $newAmount]);

        // If linked to account, optionally adjust balance
        if ($goal->account_id && $request->boolean('adjust_account_balance', false)) {
            $account = Account::find($goal->account_id);
            if ($account) {
                // If adding to piggy bank, deduct from account; if withdrawing, add to account
                $account->balance -= $amount;
                $account->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => $amount >= 0 ? 'Tabungan berhasil ditambahkan!' : 'Dana tabungan berhasil ditarik.',
            'data' => $goal
        ]);
    }

    /**
     * Delete piggy bank.
     */
    public function destroy(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $userId = $this->getUserId($request);
        $goal = PiggyBank::forUser($userId)->find($id);

        if (!$goal) {
            return response()->json(['status' => 'error', 'message' => 'Celengan tidak ditemukan.'], 404);
        }

        $goal->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Celengan berhasil dihapus.',
            'id' => $id
        ]);
    }
}
