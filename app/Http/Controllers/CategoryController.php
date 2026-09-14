<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Return list of categories with icons and color codes.
     */
    public function index()
    {
        $categories = [
            'pengeluaran' => [
                ['name' => 'Makanan & Minuman', 'icon' => 'fa-utensils', 'color' => '#f59e0b'],
                ['name' => 'Belanja', 'icon' => 'fa-bag-shopping', 'color' => '#ec4899'],
                ['name' => 'Transportasi', 'icon' => 'fa-car', 'color' => '#3b82f6'],
                ['name' => 'Tagihan & Utilitas', 'icon' => 'fa-receipt', 'color' => '#ef4444'],
                ['name' => 'Hiburan', 'icon' => 'fa-gamepad', 'color' => '#8b5cf6'],
                ['name' => 'Kesehatan', 'icon' => 'fa-heart-pulse', 'color' => '#10b981'],
                ['name' => 'Pendidikan', 'icon' => 'fa-graduation-cap', 'color' => '#06b6d4'],
                ['name' => 'Investasi', 'icon' => 'fa-chart-line', 'color' => '#14b8a6'],
                ['name' => 'Donasi / Amal', 'icon' => 'fa-hand-holding-heart', 'color' => '#f97316'],
                ['name' => 'Lainnya', 'icon' => 'fa-circle-question', 'color' => '#6b7280']
            ],
            'pemasukan' => [
                ['name' => 'Gaji', 'icon' => 'fa-money-bill-wave', 'color' => '#10b981'],
                ['name' => 'Freelance', 'icon' => 'fa-laptop-code', 'color' => '#3b82f6'],
                ['name' => 'Bisnis / Usaha', 'icon' => 'fa-store', 'color' => '#8b5cf6'],
                ['name' => 'Investasi & Dividen', 'icon' => 'fa-coins', 'color' => '#f59e0b'],
                ['name' => 'Hadiah & Bonus', 'icon' => 'fa-gift', 'color' => '#ec4899'],
                ['name' => 'Pengembalian Dana', 'icon' => 'fa-rotate-left', 'color' => '#06b6d4'],
                ['name' => 'Lainnya', 'icon' => 'fa-circle-question', 'color' => '#6b7280']
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }
}
