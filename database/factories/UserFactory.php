<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        // is_admin はマスアサインメント対象外のため、
        // state 経由では fill() に弾かれる。forceFill で直接セットする。
        // 管理者は本番同様に 2FA 必須のため、自動で 2FA 有効化までセットアップする。
        return $this
            ->afterMaking(fn (User $user) => $user->forceFill(['is_admin' => true]))
            ->afterCreating(function (User $user) {
                $user->forceFill(['is_admin' => true])->save();
                static::enableTwoFactor($user);
            });
    }

    /**
     * 2FA が有効化済みのユーザー状態を返す。
     * テストで「既に 2FA セット済み」を前提にしたいケースで使う。
     */
    public function withTwoFactor(): static
    {
        return $this->afterCreating(fn (User $user) => static::enableTwoFactor($user));
    }

    /**
     * User に対し 2FA を confirmed 状態にする内部ヘルパ。
     */
    protected static function enableTwoFactor(User $user): void
    {
        $service = app(TwoFactorAuthenticationService::class);

        $user->forceFill([
            'two_factor_secret' => $service->generateSecret(),
            'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }
}
