<?php

namespace App\Support;

/**
 * CSV 書き出し向けの共通サニタイザ。
 *
 * Excel / LibreOffice Calc / Google Sheets などで開いたときに
 * セル値が数式として評価されることを防ぐ（Formula Injection / CSV Injection 対策）。
 *
 * 参考: OWASP "CSV Injection"
 *   https://owasp.org/www-community/attacks/CSV_Injection
 */
class Csv
{
    /**
     * 表計算ソフトが数式として解釈してしまう先頭文字。
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * 単一セル値をサニタイズする。
     *
     * - 空文字・null・bool・数値はそのまま返す
     * - 文字列でも、 is_numeric で正規の数値扱いなら無加工（負数"-1234.56" の誤補正を避ける）
     * - それ以外で先頭が危険文字の場合、シングルクオートを前置してエスケープする
     */
    public static function sanitize(mixed $value): mixed
    {
        if ($value === null || $value === '' || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * 行配列（$row）の各セルをサニタイズして返す。
     *
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function sanitizeRow(array $row): array
    {
        return array_map([self::class, 'sanitize'], $row);
    }
}
