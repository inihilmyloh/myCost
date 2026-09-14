<?php

namespace Database\Seeders;

use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $today = Carbon::today();

        Transaction::firstOrCreate(
            ['notes' => 'Gaji bulanan', 'transaction_date' => $today->toDateString()],
            [
                'type' => 'pemasukan',
                'amount' => 5000000.00,
                'category' => 'Gaji',
                'transaction_date' => $today->toDateString(),
                'notes' => 'Gaji bulanan',
                'receipt_image_url' => null
            ]
        );

        Transaction::firstOrCreate(
            ['notes' => 'Makan siang ayam geprek', 'transaction_date' => $today->toDateString()],
            [
                'type' => 'pengeluaran',
                'amount' => 45000.00,
                'category' => 'Makanan & Minuman',
                'transaction_date' => $today->toDateString(),
                'notes' => 'Makan siang ayam geprek',
                'receipt_image_url' => null
            ]
        );

        Transaction::firstOrCreate(
            ['notes' => 'Belanja bulanan minimarket', 'transaction_date' => $today->copy()->subDays(1)->toDateString()],
            [
                'type' => 'pengeluaran',
                'amount' => 150000.00,
                'category' => 'Belanja',
                'transaction_date' => $today->copy()->subDays(1)->toDateString(),
                'notes' => 'Belanja bulanan minimarket',
                'receipt_image_url' => null
            ]
        );

        Transaction::firstOrCreate(
            ['notes' => 'Bensin motor', 'transaction_date' => $today->copy()->subDays(2)->toDateString()],
            [
                'type' => 'pengeluaran',
                'amount' => 50000.00,
                'category' => 'Transportasi',
                'transaction_date' => $today->copy()->subDays(2)->toDateString(),
                'notes' => 'Bensin motor',
                'receipt_image_url' => null
            ]
        );

        Transaction::firstOrCreate(
            ['notes' => 'Project desain logo freelance', 'transaction_date' => $today->copy()->subDays(3)->toDateString()],
            [
                'type' => 'pemasukan',
                'amount' => 500000.00,
                'category' => 'Freelance',
                'transaction_date' => $today->copy()->subDays(3)->toDateString(),
                'notes' => 'Project desain logo freelance',
                'receipt_image_url' => null
            ]
        );
    }
}
