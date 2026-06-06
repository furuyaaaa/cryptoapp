<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_connections', function (Blueprint $table) {
            $table->timestamp('sync_start_at')->nullable()->after('product_code');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_connections', function (Blueprint $table) {
            $table->dropColumn('sync_start_at');
        });
    }
};
