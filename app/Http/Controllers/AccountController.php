<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
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
     * Get all accounts for user. Seed defaults if empty.
     */
    public function index(Request $request)
    {
        $userId = $this->getUserId($request);
        $accounts = Account::forUser($userId)->where('is_active', true)->get();

        // If user has 0 accounts, seed default accounts (Kas Tunai, Rekening Bank, E-Wallet)
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
            'balance' => 'nullable|numeric',
            'account_number' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $account = Account::create([
            'user_id' => $userId,
            'name' => $request->name,
            'type' => $request->type,
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

        $account->update($request->only([
            'name', 'type', 'balance', 'account_number', 'icon', 'color', 'is_active'
        ]));

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
}
