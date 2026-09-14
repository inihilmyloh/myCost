<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    private function getUserId(Request $request)
    {
        return $request->header('X-User-Id') ?: (Auth::id() ?: 1);
    }

    /**
     * Display a listing of transactions with filtering and items.
     */
    public function index(Request $request)
    {
        $userId = $this->getUserId($request);
        $query = Transaction::with('items')->forUser($userId);

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
     * Store a newly created transaction with line items.
     */
    public function store(Request $request)
    {
        $userId = $this->getUserId($request);

        // Bulk Sync Mode
        if ($request->has('sync') && is_array($request->items)) {
            $synced = 0;
            foreach ($request->items as $item) {
                if (!empty($item['type']) && !empty($item['amount']) && !empty($item['transaction_date'])) {
                    $trans = Transaction::create([
                        'user_id' => $userId,
                        'type' => $item['type'] === 'pemasukan' ? 'pemasukan' : 'pengeluaran',
                        'amount' => (float)$item['amount'],
                        'subtotal' => (float)($item['subtotal'] ?? $item['amount']),
                        'discount' => (float)($item['discount'] ?? 0),
                        'tax' => (float)($item['tax'] ?? 0),
                        'category' => !empty($item['category']) ? trim($item['category']) : 'Lainnya',
                        'transaction_date' => $item['transaction_date'],
                        'notes' => !empty($item['notes']) ? trim($item['notes']) : null,
                        'receipt_image_url' => !empty($item['receipt_image_url']) ? trim($item['receipt_image_url']) : null,
                    ]);

                    if (!empty($item['items']) && is_array($item['items'])) {
                        foreach ($item['items'] as $it) {
                            if (!empty($it['item_name'])) {
                                TransactionItem::create([
                                    'transaction_id' => $trans->id,
                                    'item_name' => trim($it['item_name']),
                                    'qty' => (float)($it['qty'] ?? 1),
                                    'unit_price' => (float)($it['unit_price'] ?? 0),
                                    'discount' => (float)($it['discount'] ?? 0),
                                    'total_price' => (float)($it['total_price'] ?? 0),
                                ]);
                            }
                        }
                    }
                    $synced++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Sinkronisasi berhasil: {$synced} transaksi disimpan.",
                'synced_count' => $synced
            ], 201);
        }

        // Single Transaction
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:pemasukan,pengeluaran',
            'amount' => 'required|numeric|min:1',
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
            'type' => $request->type,
            'amount' => (float)$request->amount,
            'subtotal' => (float)($request->subtotal ?? $request->amount),
            'discount' => (float)($request->discount ?? 0),
            'tax' => (float)($request->tax ?? 0),
            'category' => $request->category ?: 'Lainnya',
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

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil ditambahkan.',
            'data' => $transaction->load('items')
        ], 201);
    }

    /**
     * Update an existing transaction with items.
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
            'type' => 'required|in:pemasukan,pengeluaran',
            'amount' => 'required|numeric|min:1',
            'category' => 'nullable|string|max:50',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string',
            'receipt_image_url' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $transaction->update([
            'type' => $request->type,
            'amount' => (float)$request->amount,
            'subtotal' => (float)($request->subtotal ?? $request->amount),
            'discount' => (float)($request->discount ?? 0),
            'tax' => (float)($request->tax ?? 0),
            'category' => $request->category ?: 'Lainnya',
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

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $transaction->load('items')
        ]);
    }

    /**
     * Remove transaction and its items.
     */
    public function destroy(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $userId = $this->getUserId($request);
        $transaction = Transaction::forUser($userId)->find($id);

        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaksi tidak ditemukan atau sudah dihapus.'], 404);
        }

        TransactionItem::where('transaction_id', $transaction->id)->delete();
        $transaction->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil dihapus.',
            'id' => $id
        ]);
    }
}
