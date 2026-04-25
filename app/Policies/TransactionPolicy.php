<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->owns($user, $transaction);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->owns($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->owns($user, $transaction);
    }

    /**
     * ポートフォリオの所有者と一致するか。
     * portfolio がロード済みならその値を使い、そうでなければDBに単一カラムクエリを一本だけ投げる。
     */
    private function owns(User $user, Transaction $transaction): bool
    {
        $ownerId = $transaction->relationLoaded('portfolio')
            ? $transaction->portfolio?->user_id
            : $transaction->portfolio()->value('user_id');

        return $ownerId !== null && $ownerId === $user->id;
    }
}
