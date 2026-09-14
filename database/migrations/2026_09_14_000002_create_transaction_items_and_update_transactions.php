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
        // Update transactions table if columns missing
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->default(1)->after('id');
            }
            if (!Schema::hasColumn('transactions', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->nullable()->default(0)->after('amount');
            }
            if (!Schema::hasColumn('transactions', 'discount')) {
                $table->decimal('discount', 15, 2)->nullable()->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('transactions', 'tax')) {
                $table->decimal('tax', 15, 2)->nullable()->default(0)->after('discount');
            }
        });

        // Create transaction_items table
        if (!Schema::hasTable('transaction_items')) {
            Schema::create('transaction_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transaction_id');
                $table->string('item_name', 150);
                $table->decimal('qty', 10, 2)->default(1.00);
                $table->decimal('unit_price', 15, 2)->default(0.00);
                $table->decimal('discount', 15, 2)->default(0.00);
                $table->decimal('total_price', 15, 2)->default(0.00);
                $table->timestamps();

                $table->index('transaction_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
