<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Display a listing of transactions with filtering.
     */
    public function index(Request $request)
    {
        $query = Transaction::query();

        // Filter by ID
        if ($request->filled('id')) {
            $transaction = $query->find($request->id);
            if ($transaction) {
                return response()->json(['status' => 'success', 'data' => $transaction]);
            }
            return response()->json(['status' => 'error', 'message' => 'Transaksi tidak ditemukan'], 404);
        }

        // Filter by Type
        if ($request->filled('type')) {
            $query->type($request->type);
        }

        // Filter by Category
        if ($request->filled('category')) {
            $query->category($request->category);
        }

        // Filter by Month (YYYY-MM)
        if ($request->filled('month')) {
            $query->month($request->month);
        }

        // Filter by Date Range
        if ($request->filled('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        // Search in notes or category
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Order by Date Desc, ID Desc
        $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc');

        // Optional Pagination / Limit
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
     * Store a newly created transaction or bulk sync items.
     */
    public function store(Request $request)
    {
        // Bulk Sync Mode
        if ($request->has('sync') && is_array($request->items)) {
            $synced = 0;
            foreach ($request->items as $item) {
                if (!empty($item['type']) && !empty($item['amount']) && !empty($item['transaction_date'])) {
                    Transaction::create([
                        'type' => $item['type'] === 'pemasukan' ? 'pemasukan' : 'pengeluaran',
                        'amount' => (float)$item['amount'],
                        'category' => !empty($item['category']) ? trim($item['category']) : 'Lainnya',
                        'transaction_date' => $item['transaction_date'],
                        'notes' => !empty($item['notes']) ? trim($item['notes']) : null,
                        'receipt_image_url' => !empty($item['receipt_image_url']) ? trim($item['receipt_image_url']) : null,
                    ]);
                    $synced++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Sinkronisasi berhasil: {$synced} transaksi disimpan.",
                'synced_count' => $synced
            ], 201);
        }

        // Single Transaction Validation
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
            'type' => $request->type,
            'amount' => (float)$request->amount,
            'category' => $request->category ?: 'Lainnya',
            'transaction_date' => $request->transaction_date,
            'notes' => $request->notes,
            'receipt_image_url' => $request->receipt_image_url
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil ditambahkan.',
            'data' => $transaction
        ], 201);
    }

    /**
     * Update an existing transaction.
     */
    public function update(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $transaction = Transaction::find($id);

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
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $transaction->update([
            'type' => $request->type,
            'amount' => (float)$request->amount,
            'category' => $request->category ?: 'Lainnya',
            'transaction_date' => $request->transaction_date,
            'notes' => $request->notes,
            'receipt_image_url' => $request->receipt_image_url
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $transaction
        ]);
    }

    /**
     * Remove the specified transaction.
     */
    public function destroy(Request $request, $id = null)
    {
        $id = $id ?: $request->id;
        $transaction = Transaction::find($id);

        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Transaksi tidak ditemukan atau sudah dihapus.'], 404);
        }

        $transaction->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil dihapus.',
            'id' => $id
        ]);
    }
}
