<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounts';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'account_sub_type',
        'has_interest',
        'interest_rate_default',
        'interest_tier_threshold',
        'interest_rate_tier',
        'interest_period',
        'interest_tax_threshold',
        'interest_tax_rate',
        'last_interest_accrued_date',
        'balance',
        'account_number',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'float',
        'has_interest' => 'boolean',
        'interest_rate_default' => 'float',
        'interest_tier_threshold' => 'float',
        'interest_rate_tier' => 'float',
        'interest_tax_threshold' => 'float',
        'interest_tax_rate' => 'float',
        'last_interest_accrued_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function scopeForUser($query, $userId)
    {
        if ($userId) {
            return $query->where('user_id', $userId);
        }
        return $query;
    }
}
