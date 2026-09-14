<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $table = 'budgets';

    protected $fillable = [
        'user_id',
        'category',
        'amount_limit',
        'month_year',
    ];

    protected $casts = [
        'amount_limit' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, $userId)
    {
        if ($userId) {
            return $query->where('user_id', $userId);
        }
        return $query;
    }
}
