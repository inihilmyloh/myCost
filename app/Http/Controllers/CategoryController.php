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
                ['name' => 'Makanan & Minuman', 'icon' => 'fa-utensils', 'color' => '#10b981'],
                ['name' => 'Belanja', 'icon' => 'fa-bag-shopping', 'color' => '#06b6d4'],
                ['name' => 'Transportasi', 'icon' => 'fa-car', 'color' => '#3b82f6'],
                ['name' => 'Tagihan & Utilitas', 'icon' => 'fa-receipt', 'color' => '#ef4444'],
                ['name' => 'Hiburan', 'icon' => 'fa-gamepad', 'color' => '#f59e0b'],
                ['name' => 'Kesehatan', 'icon' => 'fa-heart-pulse', 'color' => '#14b8a6'],
                ['name' => 'Pendidikan', 'icon' => 'fa-graduation-cap', 'color' => '#0284c7'],
                ['name' => 'Investasi', 'icon' => 'fa-chart-line', 'color' => '#10b981'],
                ['name' => 'Donasi / Amal', 'icon' => 'fa-hand-holding-heart', 'color' => '#f97316'],
                ['name' => 'Lainnya', 'icon' => 'fa-circle-question', 'color' => '#64748b']
            ],
            'pemasukan' => [
                ['name' => 'Gaji', 'icon' => 'fa-money-bill-wave', 'color' => '#10b981'],
                ['name' => 'Freelance', 'icon' => 'fa-laptop-code', 'color' => '#3b82f6'],
                ['name' => 'Bisnis / Usaha', 'icon' => 'fa-store', 'color' => '#06b6d4'],
                ['name' => 'Investasi & Dividen', 'icon' => 'fa-coins', 'color' => '#f59e0b'],
                ['name' => 'Hadiah & Bonus', 'icon' => 'fa-gift', 'color' => '#14b8a6'],
                ['name' => 'Pengembalian Dana', 'icon' => 'fa-rotate-left', 'color' => '#0284c7'],
                ['name' => 'Lainnya', 'icon' => 'fa-circle-question', 'color' => '#64748b']
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }
}
