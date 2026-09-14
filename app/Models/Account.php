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
        'balance',
        'account_number',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'float',
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
