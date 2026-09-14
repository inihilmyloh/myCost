<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'type',
        'amount',
        'category',
        'transaction_date',
        'notes',
        'receipt_image_url'
    ];

    protected $casts = [
        'amount' => 'float',
        'transaction_date' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Scopes for easy filtering
    public function scopeMonth($query, $yearMonth)
    {
        if ($yearMonth) {
            return $query->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$yearMonth]);
        }
        return $query;
    }

    public function scopeType($query, $type)
    {
        if ($type && in_array($type, ['pemasukan', 'pengeluaran'])) {
            return $query->where('type', $type);
        }
        return $query;
    }

    public function scopeCategory($query, $category)
    {
        if ($category) {
            return $query->where('category', $category);
        }
        return $query;
    }

    public function scopeSearch($query, $keyword)
    {
        if ($keyword) {
            return $query->where(function ($q) use ($keyword) {
                $q->where('notes', 'LIKE', "%{$keyword}%")
                  ->orWhere('category', 'LIKE', "%{$keyword}%");
            });
        }
        return $query;
    }
}
