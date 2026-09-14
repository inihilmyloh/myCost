<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PiggyBank extends Model
{
    use HasFactory;

    protected $table = 'piggy_banks';

    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'target_amount',
        'current_amount',
        'target_date',
        'notes',
    ];

    protected $casts = [
        'target_amount' => 'float',
        'current_amount' => 'float',
        'target_date' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function scopeForUser($query, $userId)
    {
        if ($userId) {
            return $query->where('user_id', $userId);
        }
        return $query;
    }
}
