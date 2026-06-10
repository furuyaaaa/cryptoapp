<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_connections', function (Blueprint $table) {
            $table->text('api_passphrase')->nullable()->after('api_secret');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_connections', function (Blueprint $table) {
            $table->dropColumn('api_passphrase');
        });
    }
};
