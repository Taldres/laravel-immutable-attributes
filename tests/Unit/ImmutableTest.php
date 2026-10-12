<?php

declare(strict_types=1);

use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Exceptions\InvalidImmutableColumnsException;

it('takes columns as arguments', function () {
    expect((new Immutable('number', 'customer_id'))->columns)->toBe(['number', 'customer_id']);
});

it('takes columns as an array', function () {
    expect((new Immutable(['number', 'customer_id']))->columns)->toBe(['number', 'customer_id']);
});

it('drops duplicate columns', function () {
    expect((new Immutable('number', ['number', 'total']))->columns)->toBe(['number', 'total']);
});

it('needs at least one column', function (Closure $make) {
    expect($make)->toThrow(InvalidImmutableColumnsException::class, 'needs at least one column. Use #[ImmutableModel] to guard the whole model.');
})->with([
    'no arguments' => fn () => new Immutable,
    'an empty list' => fn () => new Immutable([]),
]);

it('does not take a wildcard', function (Closure $make) {
    expect($make)->toThrow(InvalidImmutableColumnsException::class, 'does not take "*". Use #[ImmutableModel] to guard the whole model.');
})->with([
    'alone' => fn () => new Immutable('*'),
    'next to columns' => fn () => new Immutable('number', ['*']),
]);

it('takes column names as non-empty strings', function (Closure $make, string $given) {
    expect($make)->toThrow(InvalidImmutableColumnsException::class, "takes column names as non-empty strings, {$given} given.");
})->with([
    'an empty string' => [fn () => new Immutable(''), 'an empty string'],
    'an empty string in a list' => [fn () => new Immutable(['number', '']), 'an empty string'],
    'only whitespace' => [fn () => new Immutable(' '), 'an empty string'],
    'an integer' => [fn () => new Immutable([123]), 'int'],
    'an integer as an argument' => [fn () => new Immutable(123), 'int'],
    'null as an argument' => [fn () => new Immutable(null), 'null'],
    'a boolean next to columns' => [fn () => new Immutable('number', true), 'bool'],
    'a nested list' => [fn () => new Immutable([['number']]), 'array'],
]);

it('takes column names without whitespace around them', function (Closure $make, string $given) {
    expect($make)->toThrow(
        InvalidImmutableColumnsException::class,
        "takes column names without whitespace around them, {$given} given. Use \"number\" instead.",
    );
})->with([
    'a space in front' => [fn () => new Immutable(' number'), '" number"'],
    'a space behind' => [fn () => new Immutable('number '), '"number "'],
    'a tab' => [fn () => new Immutable("\tnumber"), '"\tnumber"'],
    'a non-breaking space' => [fn () => new Immutable("number\u{00A0}"), '"number\u00a0"'],
    'in a list' => [fn () => new Immutable(['total', ' number']), '" number"'],
]);

it('keeps a space inside a column name', function () {
    expect((new Immutable('num ber'))->columns)->toBe(['num ber']);
});

it('does not take JSON paths', function () {
    expect(fn () => new Immutable('options->currency'))->toThrow(
        InvalidImmutableColumnsException::class,
        'cannot guard the JSON path "options->currency". Guard the whole column with #[Immutable(\'options\')] instead.',
    );
});

it('takes no columns as #[ImmutableModel]', function (Closure $make) {
    expect($make)->toThrow(
        InvalidImmutableColumnsException::class,
        '#[ImmutableModel] takes no columns, it guards the whole model. Use #[Immutable] to guard single columns, or except: [...] to leave some out.',
    );
})->with([
    'a column' => fn () => new ImmutableModel('number'),
    'a list' => fn () => new ImmutableModel(['number']),
    'a column next to except' => fn () => new ImmutableModel('number', except: ['total']),
]);

it('leaves columns out with except', function (Closure $make, array $except) {
    expect($make()->except)->toBe($except);
})->with([
    'nothing by default' => [fn () => new ImmutableModel, []],
    'a list' => [fn () => new ImmutableModel(except: ['total', 'note']), ['total', 'note']],
    'one name' => [fn () => new ImmutableModel(except: 'total'), ['total']],
    'duplicates once' => [fn () => new ImmutableModel(except: ['total', 'total']), ['total']],
]);

it('still guards the whole model when it leaves columns out', function () {
    expect((new ImmutableModel(except: ['total']))->columns)->toBe(['*']);
});

it('knows no argument but except', function () {
    expect(fn () => new ImmutableModel(excpet: ['total']))->toThrow(
        InvalidImmutableColumnsException::class,
        '#[ImmutableModel] has no argument named "excpet". It takes except: [...] to leave columns out.',
    );
});

it('takes column names as non-empty strings in except', function (Closure $make, string $given) {
    expect($make)->toThrow(
        InvalidImmutableColumnsException::class,
        "#[ImmutableModel] takes column names as non-empty strings in except, {$given} given.",
    );
})->with([
    'an empty string' => [fn () => new ImmutableModel(except: ['total', '']), 'an empty string'],
    'an integer' => [fn () => new ImmutableModel(except: [123]), 'int'],
    'null' => [fn () => new ImmutableModel(except: null), 'null'],
    'a nested list' => [fn () => new ImmutableModel(except: [['total']]), 'array'],
]);

it('takes column names without whitespace around them in except', function (string $name, string $given) {
    expect(fn () => new ImmutableModel(except: ['total', $name]))->toThrow(
        InvalidImmutableColumnsException::class,
        "#[ImmutableModel] takes column names without whitespace around them in except, {$given} given. Use \"note\" instead.",
    );
})->with([
    'a space in front' => [' note', '" note"'],
    'a space behind' => ['note ', '"note "'],
    'a non-breaking space' => ["note\u{00A0}", '"note\u00a0"'],
]);

it('cannot leave out the wildcard', function () {
    expect(fn () => new ImmutableModel(except: ['*']))->toThrow(
        InvalidImmutableColumnsException::class,
        '#[ImmutableModel] cannot leave out "*". Drop the attribute to leave the whole model unguarded.',
    );
});

it('cannot leave out a JSON path', function () {
    expect(fn () => new ImmutableModel(except: ['options->currency']))->toThrow(
        InvalidImmutableColumnsException::class,
        'cannot leave out the JSON path "options->currency". The guard compares whole columns; leave out \'options\' instead.',
    );
});

it('covers the whole model as #[ImmutableModel]', function () {
    expect((new ImmutableModel)->columns)->toBe(['*']);
});
