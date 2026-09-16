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
            if (!Schema::hasColumn('accounts', 'monthly_admin_fee')) {
                $table->decimal('monthly_admin_fee', 15, 2)->default(0.00)->after('interest_tiers');
            }
            if (!Schema::hasColumn('accounts', 'admin_fee_date')) {
                $table->unsignedTinyInteger('admin_fee_date')->default(25)->after('monthly_admin_fee');
            }
            if (!Schema::hasColumn('accounts', 'last_admin_fee_deducted_date')) {
                $table->date('last_admin_fee_deducted_date')->nullable()->after('admin_fee_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['monthly_admin_fee', 'admin_fee_date', 'last_admin_fee_deducted_date']);
        });
    }
};
