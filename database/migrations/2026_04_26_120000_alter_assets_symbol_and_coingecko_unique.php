<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropUnique(['symbol']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->unique('coingecko_id');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropUnique(['coingecko_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->unique('symbol');
        });
    }
};
