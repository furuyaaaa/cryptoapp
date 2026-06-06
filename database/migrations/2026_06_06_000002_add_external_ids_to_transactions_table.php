<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('external_source')->nullable()->after('note');
            $table->string('external_id')->nullable()->after('external_source');
            $table->timestamp('synced_at')->nullable()->after('external_id');

            $table->unique(['exchange_id', 'external_source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['exchange_id', 'external_source', 'external_id']);
            $table->dropColumn(['external_source', 'external_id', 'synced_at']);
        });
    }
};
