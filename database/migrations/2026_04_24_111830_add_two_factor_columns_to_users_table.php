<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // TOTP シークレットはアプリ側で暗号化して保存する。
            $table->text('two_factor_secret')->nullable()->after('password');
            // 復旧コード (JSON エンコードされた配列) も同様に暗号化。
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            // 確認入力が通った時点でセットする。enable 中 (未確認) を区別するため nullable。
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
