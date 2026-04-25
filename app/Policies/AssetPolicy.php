<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

/**
 * 銘柄 (Asset) マスターに対する認可。
 *
 * ルート側でも admin ミドルウェアで守られているが、コントローラー側でも
 * Policy を通すことで多層防御にする。
 * `view`（show）は一般ユーザーも許可（自分の取引詳細を見るため）。
 */
class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Asset $asset): bool
    {
        // 銘柄詳細は認証済みユーザーなら誰でも閲覧できる。
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->isAdmin();
    }
}
