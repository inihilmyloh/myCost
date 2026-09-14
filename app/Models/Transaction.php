<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'account_id',
        'destination_account_id',
        'type',
        'amount',
        'subtotal',
        'discount',
        'tax',
        'category',
        'transaction_date',
        'notes',
        'receipt_image_url'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'account_id' => 'integer',
        'destination_account_id' => 'integer',
        'amount' => 'float',
        'subtotal' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'transaction_date' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationship to Items
    public function items()
    {
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }

    // Relationship to Account
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    // Relationship to Destination Account (for Transfers)
    public function destinationAccount()
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    // Relationship to User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        if ($userId) {
            return $query->where('user_id', $userId);
        }
        return $query;
    }

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
