<?php

namespace App\Policies;

use App\Models\Portfolio;
use App\Models\User;

class PortfolioPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Portfolio $portfolio): bool
    {
        return $this->owns($user, $portfolio);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Portfolio $portfolio): bool
    {
        return $this->owns($user, $portfolio);
    }

    public function delete(User $user, Portfolio $portfolio): bool
    {
        return $this->owns($user, $portfolio);
    }

    private function owns(User $user, Portfolio $portfolio): bool
    {
        return $portfolio->user_id === $user->id;
    }
}
