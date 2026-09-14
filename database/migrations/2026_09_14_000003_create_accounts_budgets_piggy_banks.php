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
        // 1. Accounts Table (Dompet & Rekening Bank)
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 100);
                $table->string('type', 30)->default('cash'); // cash, bank, ewallet, investment
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->string('account_number', 50)->nullable();
                $table->string('icon', 50)->default('fa-wallet');
                $table->string('color', 20)->default('#10b981');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'type']);
            });
        }

        // 2. Budgets Table (Anggaran Bulanan per Kategori)
        if (!Schema::hasTable('budgets')) {
            Schema::create('budgets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('category', 100);
                $table->decimal('amount_limit', 15, 2)->default(0.00);
                $table->string('month_year', 7); // YYYY-MM
                $table->timestamps();

                $table->unique(['user_id', 'category', 'month_year']);
                $table->index(['user_id', 'month_year']);
            });
        }

        // 3. Piggy Banks Table (Celengan & Target Tabungan)
        if (!Schema::hasTable('piggy_banks')) {
            Schema::create('piggy_banks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
                $table->string('name', 150);
                $table->decimal('target_amount', 15, 2)->default(0.00);
                $table->decimal('current_amount', 15, 2)->default(0.00);
                $table->date('target_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }

        // 4. Update Transactions Table with Account relations & Transfer Support
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'account_id')) {
                $table->foreignId('account_id')->nullable()->after('user_id')->constrained('accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('transactions', 'destination_account_id')) {
                $table->foreignId('destination_account_id')->nullable()->after('account_id')->constrained('accounts')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'destination_account_id')) {
                $table->dropForeign(['destination_account_id']);
                $table->dropColumn('destination_account_id');
            }
            if (Schema::hasColumn('transactions', 'account_id')) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
        });

        Schema::dropIfExists('piggy_banks');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('accounts');
    }
};
