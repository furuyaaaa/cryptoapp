<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('exchange_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['buy', 'sell', 'transfer_in', 'transfer_out']);
            $table->decimal('amount', 20, 8);
            $table->decimal('price_jpy', 20, 8);
            $table->decimal('fee_jpy', 20, 8)->default(0);
            $table->timestamp('executed_at')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
