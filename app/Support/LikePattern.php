<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * LIKE / ILIKE 用のパターン文字列を生成するヘルパ。
 *
 * ユーザー入力に含まれる `%`, `_`, `\` (デフォルトエスケープ文字) を
 * バックスラッシュでエスケープし、ワイルドカードが意図せず効くのを防ぐ。
 *
 * 検索入力が `%` や `_` を含む場合に、
 *   - 攻撃者が「全件マッチ」させて無差別取得するリスク
 *   - DB負荷の急増 (任意の接頭辞/接尾辞で広範囲スキャン)
 * を両方とも抑制する。
 *
 * 注意: PostgreSQL/MySQL は LIKE のデフォルトエスケープ文字が `\` のためそのまま有効。
 *       SQLite は本来 ESCAPE 句が無いと `\%` は `\%` (2文字) にマッチしてしまうが、
 *       本プロジェクトは PostgreSQL 固定運用のため SQLite は対象外。
 */
class LikePattern
{
    /**
     * 部分一致パターン ( %入力% ) を返す。
     */
    public static function contains(string $input): string
    {
        return '%'.static::escape($input).'%';
    }

    /**
     * 前方一致パターン ( 入力% ) を返す。
     */
    public static function startsWith(string $input): string
    {
        return static::escape($input).'%';
    }

    /**
     * ワイルドカード文字 (`%`, `_`) と `\` をエスケープして返す。
     */
    public static function escape(string $input): string
    {
        return addcslashes($input, '\\%_');
    }

    /**
     * 現在の DB ドライバに応じた大文字小文字無視の LIKE 演算子を返す。
     * PostgreSQL は ILIKE、それ以外は LIKE。
     */
    public static function operator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
