<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    use HasFactory;

    protected $table = 'transaction_items';

    protected $fillable = [
        'transaction_id',
        'item_name',
        'qty',
        'unit_price',
        'discount',
        'total_price'
    ];

    protected $casts = [
        'qty' => 'float',
        'unit_price' => 'float',
        'discount' => 'float',
        'total_price' => 'float'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
