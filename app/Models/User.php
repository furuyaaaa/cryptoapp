<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * NOTE: `is_admin` はマスアサインメント対象から意図的に外している。
     * 管理者権限の変更は {@see self::promoteToAdmin()} / {@see self::demoteFromAdmin()}
     * 経由でのみ行うこと。HTTP 入力から直接書き換えられないようにするため。
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            // 2FA シークレットと復旧コードは DB レベルでアプリ暗号化する。
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * 2FA が有効化済み (確認入力まで完了) か。
     */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * 2FA のセットアップ中（シークレットは払い出し済みだが confirm 未完了）か。
     */
    public function hasPendingTwoFactor(): bool
    {
        return ! is_null($this->two_factor_secret) && is_null($this->two_factor_confirmed_at);
    }

    /**
     * 管理者権限を付与する。マスアサインメント保護を迂回するため
     * 管理系のコマンドや明示的な Policy 経由からのみ呼ぶこと。
     */
    public function promoteToAdmin(): bool
    {
        return $this->forceFill(['is_admin' => true])->save();
    }

    public function demoteFromAdmin(): bool
    {
        return $this->forceFill(['is_admin' => false])->save();
    }
}
