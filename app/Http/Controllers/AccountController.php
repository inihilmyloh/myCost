<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use App\Services\AccountInterestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
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
     * Get all accounts for user. Seed defaults if empty. Accrue daily interest automatically.
     */
    public function index(Request $request, AccountInterestService $interestService)
    {
        $userId = $this->getUserId($request);

        // Auto-accrue daily interest for active savings accounts
        try {
            $interestService->accrueAllAccounts($userId);
        } catch (\Exception $e) {
            // Log & continue without breaking UI
        }

        $accounts = Account::forUser($userId)->where('is_active', true)->get();

        // If user has 0 accounts, seed default accounts (Kas Tunai, Rekening Bank, E-Wallet)
        if ($accounts->isEmpty()) {
            $defaultAccounts = [
                ['name' => 'Kas Tunai / Dompet', 'type' => 'cash', 'account_sub_type' => 'regular', 'has_interest' => false, 'balance' => 0, 'icon' => 'fa-wallet', 'color' => '#10b981'],
                ['name' => 'Seabank', 'type' => 'bank', 'account_sub_type' => 'savings', 'has_interest' => true, 'interest_rate_default' => 2.50, 'interest_tier_threshold' => 150000000.00, 'interest_rate_tier' => 3.50, 'interest_period' => 'daily', 'balance' => 0, 'icon' => 'fa-building-columns', 'color' => '#059669', 'last_interest_accrued_date' => now()->toDateString()],
                ['name' => 'GoPay / E-Wallet', 'type' => 'ewallet', 'account_sub_type' => 'regular', 'has_interest' => false, 'balance' => 0, 'icon' => 'fa-mobile-screen-button', 'color' => '#34d399'],
            ];

            foreach ($defaultAccounts as $acc) {
                Account::create(array_merge($acc, ['user_id' => $userId]));
            }
            $accounts = Account::forUser($userId)->where('is_active', true)->get();
        }

        $totalNetWorth = $accounts->sum('balance');

        return response()->json([
            'status' => 'success',
            'data' => [
                'accounts' => $accounts,
                'total_net_worth' => (float)$totalNetWorth,
            ]
        ]);
    }

    /**
     * Store new account.
     */
    public function store(Request $request)
    {
        $userId = $this->getUserId($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:cash,bank,ewallet,investment',
            'account_sub_type' => 'nullable|string|in:regular,savings',
            'has_interest' => 'nullable|boolean',
            'interest_rate_default' => 'nullable|numeric|min:0|max:100',
            'interest_tier_threshold' => 'nullable|numeric|min:0',
            'interest_rate_tier' => 'nullable|numeric|min:0|max:100',
            'interest_period' => 'nullable|string|in:daily,monthly',
            'balance' => 'nullable|numeric',
            'account_number' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $hasInterest = $request->boolean('has_interest') || $request->account_sub_type === 'savings';
        $subType = $request->account_sub_type ?? ($hasInterest ? 'savings' : 'regular');

        $account = Account::create([
            'user_id' => $userId,
            'name' => $request->name,
            'type' => $request->type,
            'account_sub_type' => $subType,
            'has_interest' => $hasInterest,
            'interest_rate_default' => (float)($request->interest_rate_default ?? 2.50),
            'interest_tier_threshold' => (float)($request->interest_tier_threshold ?? 150000000.00),
            'interest_rate_tier' => (float)($request->interest_rate_tier ?? 3.50),
            'interest_period' => $request->interest_period ?? 'daily',
            'interest_tax_threshold' => 7500000.00,
            'interest_tax_rate' => 20.00,
            'last_interest_accrued_date' => $hasInterest ? now()->toDateString() : null,
            'balance' => (float)($request->balance ?? 0),
            'account_number' => $request->account_number,
            'icon' => $request->icon ?? 'fa-wallet',
            'color' => $request->color ?? '#10b981',
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekening / Dompet berhasil ditambahkan.',
            'data' => $account
        ], 201);
    }

    /**
     * Update account.
     */
    public function update(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $userId = $this->getUserId($request);
        $account = Account::forUser($userId)->find($id);

        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Rekening tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'type' => 'nullable|string|in:cash,bank,ewallet,investment',
            'account_sub_type' => 'nullable|string|in:regular,savings',
            'has_interest' => 'nullable|boolean',
            'interest_rate_default' => 'nullable|numeric|min:0|max:100',
            'interest_tier_threshold' => 'nullable|numeric|min:0',
            'interest_rate_tier' => 'nullable|numeric|min:0|max:100',
            'interest_period' => 'nullable|string|in:daily,monthly',
            'balance' => 'nullable|numeric',
            'account_number' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $data = $request->only([
            'name', 'type', 'balance', 'account_number', 'icon', 'color', 'is_active',
            'interest_rate_default', 'interest_tier_threshold', 'interest_rate_tier', 'interest_period'
        ]);

        if ($request->has('account_sub_type') || $request->has('has_interest')) {
            $hasInterest = $request->has('has_interest') 
                ? $request->boolean('has_interest') 
                : ($request->account_sub_type === 'savings');
            $subType = $request->account_sub_type ?? ($hasInterest ? 'savings' : 'regular');
            $data['has_interest'] = $hasInterest;
            $data['account_sub_type'] = $subType;
            if ($hasInterest && empty($account->last_interest_accrued_date)) {
                $data['last_interest_accrued_date'] = now()->toDateString();
            }
        }

        $account->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekening berhasil diperbarui.',
            'data' => $account
        ]);
    }

    /**
     * Delete account (soft/deactivate).
     */
    public function destroy(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $userId = $this->getUserId($request);
        $account = Account::forUser($userId)->find($id);

        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Rekening tidak ditemukan.'], 404);
        }

        $account->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekening berhasil dihapus.',
            'id' => $id
        ]);
    }

    /**
     * Simulate interest calculations for an account or custom amount.
     */
    public function simulateInterest(Request $request, $id = null, AccountInterestService $service)
    {
        $userId = $this->getUserId($request);
        $account = null;
        if ($id || $request->id) {
            $account = Account::forUser($userId)->find($id ?: $request->id);
        }

        $balance = (float)($request->balance ?? ($account ? $account->balance : 1000000));
        $defaultRate = (float)($request->interest_rate_default ?? ($account ? $account->interest_rate_default : 2.50));
        $tierRate = (float)($request->interest_rate_tier ?? ($account ? $account->interest_rate_tier : 3.50));
        $tierThreshold = (float)($request->interest_tier_threshold ?? ($account ? $account->interest_tier_threshold : 150000000.00));

        $simulation = $service->simulate($balance, $defaultRate, $tierRate, $tierThreshold);

        return response()->json([
            'status' => 'success',
            'data' => [
                'account' => $account,
                'simulation' => $simulation
            ]
        ]);
    }

    /**
     * Manually trigger interest accrual for an account.
     */
    public function accrueInterest(Request $request, $id, AccountInterestService $service)
    {
        $userId = $this->getUserId($request);
        $account = Account::forUser($userId)->find($id);

        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Rekening tidak ditemukan.'], 404);
        }

        $result = $service->accrueAccountInterest($account);

        return response()->json([
            'status' => 'success',
            'message' => $result['message'] ?? 'Bunga berhasil diproses.',
            'data' => $result
        ]);
    }
}
