<?php

use App\Support\Csv;

test('= で始まる文字列はシングルクオートでエスケープされる', function () {
    expect(Csv::sanitize('=SUM(A1:A10)'))->toBe("'=SUM(A1:A10)");
});

test('+ / - / @ / TAB / CR で始まる文字列もエスケープされる', function () {
    expect(Csv::sanitize('+1+1'))->toBe("'+1+1");
    expect(Csv::sanitize('@cmd'))->toBe("'@cmd");
    expect(Csv::sanitize("\tfoo"))->toBe("'\tfoo");
    expect(Csv::sanitize("\revil"))->toBe("'\revil");
});

test('正規の負数文字列は数値として保護されエスケープされない', function () {
    expect(Csv::sanitize('-1234.56'))->toBe('-1234.56');
    expect(Csv::sanitize('-0'))->toBe('-0');
    expect(Csv::sanitize('0.00'))->toBe('0.00');
});

test('ハイフンで始まる非数値文字列はエスケープされる', function () {
    expect(Csv::sanitize('-foo'))->toBe("'-foo");
});

test('通常の文字列・null・空文字・数値はそのまま通す', function () {
    expect(Csv::sanitize('hello'))->toBe('hello');
    expect(Csv::sanitize(''))->toBe('');
    expect(Csv::sanitize(null))->toBeNull();
    expect(Csv::sanitize(123))->toBe(123);
    expect(Csv::sanitize(-45.67))->toBe(-45.67);
    expect(Csv::sanitize(true))->toBeTrue();
});

test('sanitizeRow は各要素を個別にサニタイズする', function () {
    expect(Csv::sanitizeRow(['=A1', 'foo', '-100.5', '@evil', 42]))
        ->toBe(["'=A1", 'foo', '-100.5', "'@evil", 42]);
});
