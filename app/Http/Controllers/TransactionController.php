<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    private function getUserId(Request $request)
    {
        $headerId = $request->header('X-User-Id');
        if ($headerId && User::where('id', $headerId)->exists()) {
            return (int)$headerId;
        }
        if (Auth::check()) {
            return Auth::id();
        }
        return null;
    }

    /**
     * Helper to adjust account balances based on transaction type.
     */
    private function applyAccountBalance(Transaction $trans, $isRevert = false)
    {
        $multiplier = $isRevert ? -1 : 1;
        $amount = (float)$trans->amount * $multiplier;

        if ($trans->type === 'pemasukan' && $trans->account_id) {
            $account = Account::find($trans->account_id);
            if ($account) {
                $account->balance += $amount;
                $account->save();
            }
        } elseif ($trans->type === 'pengeluaran' && $trans->account_id) {
            $account = Account::find($trans->account_id);
            if ($account) {
                $account->balance -= $amount;
                $account->save();
            }
        } elseif ($trans->type === 'transfer') {
            if ($trans->account_id) {
                $src = Account::find($trans->account_id);
                if ($src) {
                    $src->balance -= $amount;
                    $src->save();
                }
            }
            if ($trans->destination_account_id) {
                $dst = Account::find($trans->destination_account_id);
                if ($dst) {
                    $dst->balance += $amount;
                    $dst->save();
                }
            }
        }
    }

    /**
     * Display a listing of transactions with filtering, items, and accounts.
     */
    public function index(Request $request)
    {
        $userId = $this->getUserId($request);
        $query = Transaction::with(['items', 'account', 'destinationAccount'])->forUser($userId);

        // Filter by ID
        if ($request->filled('id')) {
            $transaction = $query->find($request->id);
            if ($transaction) {
                return response()->json(['status' => 'success', 'data' => $transaction]);
            }
            return response()->json(['status' => 'error', 'message' => 'Transaksi tidak ditemukan'], 404);
        }

        if ($request->filled('type')) {
            $query->type($request->type);
        }

        if ($request->filled('account_id')) {
            $accId = $request->account_id;
            $query->where(function ($q) use ($accId) {
                $q->where('account_id', $accId)->orWhere('destination_account_id', $accId);
            });
        }

        if ($request->filled('category')) {
            $query->category($request->category);
        }

        if ($request->filled('month')) {
            $query->month($request->month);
        }

        if ($request->filled('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc');

        if ($request->filled('limit')) {
            $limit = max(1, (int)$request->limit);
            $transactions = $query->limit($limit)->get();
        } else {
            $transactions = $query->get();
        }

        return response()->json([
            'status' => 'success',
            'count' => $transactions->count(),
            'data' => $transactions
        ]);
    }

    /**
     * Store a newly created transaction with line items & account balance mutation.
     */
    public function store(Request $request)
    {
        $userId = $this->getUserId($request);

        // Single Transaction Validation
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:pemasukan,pengeluaran,transfer',
            'amount' => 'required|numeric|min:1',
            'account_id' => 'nullable|exists:accounts,id',
            'destination_account_id' => 'nullable|exists:accounts,id',
            'category' => 'nullable|string|max:50',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string',
            'receipt_image_url' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction = Transaction::create([
            'user_id' => $userId,
            'account_id' => $request->account_id,
            'destination_account_id' => $request->destination_account_id,
            'type' => $request->type,
            'amount' => (float)$request->amount,
            'subtotal' => (float)($request->subtotal ?? $request->amount),
            'discount' => (float)($request->discount ?? 0),
            'tax' => (float)($request->tax ?? 0),
            'category' => $request->type === 'transfer' ? 'Transfer Antar Rekening' : ($request->category ?: 'Lainnya'),
            'transaction_date' => $request->transaction_date,
            'notes' => $request->notes,
            'receipt_image_url' => $request->receipt_image_url
        ]);

        // Save Line Items
        if ($request->has('items') && is_array($request->items)) {
            foreach ($request->items as $it) {
                if (!empty($it['item_name'])) {
                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'item_name' => trim($it['item_name']),
                        'qty' => (float)($it['qty'] ?? 1),
                        'unit_price' => (float)($it['unit_price'] ?? 0),
                        'discount' => (float)($it['discount'] ?? 0),
                        'total_price' => (float)($it['total_price'] ?? 0),
                    ]);
                }
            }
        }

        // Apply balance mutation to account
        $this->applyAccountBalance($transaction, false);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil dicatat.',
            'data' => $transaction->load(['items', 'account', 'destinationAccount'])
        ], 201);
    }

    /**
     * Update an existing transaction.
     */
    public function update(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $userId = $this->getUserId($request);
        $transaction = Transaction::forUser($userId)->find($id);

        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaksi tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:pemasukan,pengeluaran,transfer',
            'amount' => 'required|numeric|min:1',
            'account_id' => 'nullable|exists:accounts,id',
            'destination_account_id' => 'nullable|exists:accounts,id',
            'category' => 'nullable|string|max:50',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string',
            'receipt_image_url' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        // Revert old account balance mutation
        $this->applyAccountBalance($transaction, true);

        $transaction->update([
            'account_id' => $request->account_id,
            'destination_account_id' => $request->destination_account_id,
            'type' => $request->type,
            'amount' => (float)$request->amount,
            'subtotal' => (float)($request->subtotal ?? $request->amount),
            'discount' => (float)($request->discount ?? 0),
            'tax' => (float)($request->tax ?? 0),
            'category' => $request->type === 'transfer' ? 'Transfer Antar Rekening' : ($request->category ?: 'Lainnya'),
            'transaction_date' => $request->transaction_date,
            'notes' => $request->notes,
            'receipt_image_url' => $request->receipt_image_url
        ]);

        // Refresh line items if provided
        if ($request->has('items') && is_array($request->items)) {
            TransactionItem::where('transaction_id', $transaction->id)->delete();
            foreach ($request->items as $it) {
                if (!empty($it['item_name'])) {
                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'item_name' => trim($it['item_name']),
                        'qty' => (float)($it['qty'] ?? 1),
                        'unit_price' => (float)($it['unit_price'] ?? 0),
                        'discount' => (float)($it['discount'] ?? 0),
                        'total_price' => (float)($it['total_price'] ?? 0),
                    ]);
                }
            }
        }

        // Apply new account balance mutation
        $this->applyAccountBalance($transaction, false);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $transaction->load(['items', 'account', 'destinationAccount'])
        ]);
    }

    /**
     * Remove transaction, its items, and revert account balance.
     */
    public function destroy(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $userId = $this->getUserId($request);
        $transaction = Transaction::forUser($userId)->find($id);

        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaksi tidak ditemukan atau sudah dihapus.'], 404);
        }

        // Revert balance mutation
        $this->applyAccountBalance($transaction, true);

        TransactionItem::where('transaction_id', $transaction->id)->delete();
        $transaction->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil dihapus.',
            'id' => $id
        ]);
    }
}
