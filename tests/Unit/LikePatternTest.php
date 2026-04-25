<?php

use App\Support\LikePattern;

test('contains はワイルドカード文字 % / _ / バックスラッシュ をエスケープする', function () {
    expect(LikePattern::contains('abc'))->toBe('%abc%');
    expect(LikePattern::contains('10%off'))->toBe('%10\\%off%');
    expect(LikePattern::contains('foo_bar'))->toBe('%foo\\_bar%');
    expect(LikePattern::contains('back\\slash'))->toBe('%back\\\\slash%');
});

test('startsWith は前方一致パターンを返す', function () {
    expect(LikePattern::startsWith('BTC'))->toBe('BTC%');
    expect(LikePattern::startsWith('10%'))->toBe('10\\%%');
});

test('escape はワイルドカード単体でもエスケープする', function () {
    expect(LikePattern::escape('%'))->toBe('\\%');
    expect(LikePattern::escape('_'))->toBe('\\_');
    expect(LikePattern::escape('\\'))->toBe('\\\\');
    expect(LikePattern::escape('abc'))->toBe('abc');
});

test('空文字は %% (全件マッチ) を返すが、呼び出し側で空チェックすること', function () {
    // これは意図した挙動: 呼び出し側で trim して空かどうか判定してから使う。
    expect(LikePattern::contains(''))->toBe('%%');
});
