<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Budget;
use App\Models\PiggyBank;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create or Update Main User
        $user = User::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Izlude',
                'email' => 'izlude@mycost.local',
                'password' => Hash::make('password123'),
            ]
        );

        // 2. Clear old test transactions & items for a fresh start
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        TransactionItem::truncate();
        Transaction::truncate();
        Account::truncate();
        Budget::truncate();
        PiggyBank::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        Account::create([
            'user_id' => $user->id,
            'name' => 'Bank BCA',
            'type' => 'bank',
            'balance' => 0.00,
            'account_number' => '1234567890',
            'icon' => 'fa-building-columns',
            'color' => '#10b981',
            'is_active' => true,
        ]);

        Account::create([
            'user_id' => $user->id,
            'name' => 'Kas Tunai / Dompet',
            'type' => 'cash',
            'balance' => 0.00,
            'account_number' => null,
            'icon' => 'fa-wallet',
            'color' => '#059669',
            'is_active' => true,
        ]);

        Account::create([
            'user_id' => $user->id,
            'name' => 'GoPay / E-Wallet',
            'type' => 'ewallet',
            'balance' => 0.00,
            'account_number' => '08123456789',
            'icon' => 'fa-mobile-screen',
            'color' => '#34d399',
            'is_active' => true,
        ]);

        // 4. Reset Budgets & Piggy Banks to clean slate
        Budget::truncate();
        PiggyBank::truncate();
    }
}
