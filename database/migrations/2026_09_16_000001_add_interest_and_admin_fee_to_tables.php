<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'account_sub_type')) {
                $table->string('account_sub_type', 30)->default('regular')->after('type'); // 'regular' (saldo biasa), 'savings' (tabungan berbunga)
            }
            if (!Schema::hasColumn('accounts', 'has_interest')) {
                $table->boolean('has_interest')->default(false)->after('account_sub_type');
            }
            if (!Schema::hasColumn('accounts', 'interest_rate_default')) {
                $table->decimal('interest_rate_default', 5, 2)->default(2.50)->after('has_interest'); // 2.50% p.a.
            }
            if (!Schema::hasColumn('accounts', 'interest_tier_threshold')) {
                $table->decimal('interest_tier_threshold', 15, 2)->default(150000000.00)->after('interest_rate_default'); // Rp 150.000.000
            }
            if (!Schema::hasColumn('accounts', 'interest_rate_tier')) {
                $table->decimal('interest_rate_tier', 5, 2)->default(3.50)->after('interest_tier_threshold'); // 3.50% p.a.
            }
            if (!Schema::hasColumn('accounts', 'interest_period')) {
                $table->string('interest_period', 20)->default('daily')->after('interest_rate_tier'); // 'daily', 'monthly'
            }
            if (!Schema::hasColumn('accounts', 'interest_tax_threshold')) {
                $table->decimal('interest_tax_threshold', 15, 2)->default(7500000.00)->after('interest_period'); // Rp 7.500.000
            }
            if (!Schema::hasColumn('accounts', 'interest_tax_rate')) {
                $table->decimal('interest_tax_rate', 5, 2)->default(20.00)->after('interest_tax_threshold'); // 20%
            }
            if (!Schema::hasColumn('accounts', 'last_interest_accrued_date')) {
                $table->date('last_interest_accrued_date')->nullable()->after('interest_tax_rate');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'admin_fee')) {
                $table->decimal('admin_fee', 15, 2)->default(0.00)->after('tax');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'admin_fee')) {
                $table->dropColumn('admin_fee');
            }
        });

        Schema::table('accounts', function (Blueprint $table) {
            $columns = [
                'account_sub_type',
                'has_interest',
                'interest_rate_default',
                'interest_tier_threshold',
                'interest_rate_tier',
                'interest_period',
                'interest_tax_threshold',
                'interest_tax_rate',
                'last_interest_accrued_date',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
