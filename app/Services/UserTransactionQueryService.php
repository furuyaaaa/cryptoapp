<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * ログインユーザーに紐づく取引一覧クエリ（Web / API 共通）。
 */
final class UserTransactionQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function filtered(Request $request, array $filters): Builder
    {
        $user = $request->user();

        $query = Transaction::query()
            ->whereHas('portfolio', fn ($q) => $q->where('user_id', $user->id))
            ->with(['portfolio:id,name', 'asset:id,symbol,name,icon_url', 'exchange:id,name'])
            ->orderByDesc('executed_at')
            ->orderByDesc('id');

        if (! empty($filters['portfolio_id'])) {
            $query->where('portfolio_id', $filters['portfolio_id']);
        }
        if (! empty($filters['asset_id'])) {
            $query->where('asset_id', $filters['asset_id']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['from'])) {
            $query->where('executed_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('executed_at', '<=', $filters['to'].' 23:59:59');
        }
        if (! empty($filters['q'])) {
            $like = LikePattern::operator();
            $pattern = LikePattern::contains((string) $filters['q']);
            $query->where(function ($sub) use ($like, $pattern) {
                $sub->where('note', $like, $pattern)
                    ->orWhereHas('asset', fn ($a) => $a->where('symbol', $like, $pattern)->orWhere('name', $like, $pattern));
            });
        }

        return $query;
    }
}
