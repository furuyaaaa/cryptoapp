<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || ! $user->isAdmin(), 403, '管理者権限が必要です。');

        return $next($request);
    }
}
