<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_quote_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 16);
            $table->string('quote_currency', 16);
            $table->date('rate_date');
            $table->decimal('rate', 24, 8);
            $table->string('source', 64);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['base_currency', 'quote_currency', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_quote_rates');
    }
};
