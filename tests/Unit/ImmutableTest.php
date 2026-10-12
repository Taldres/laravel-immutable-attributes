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

it('does not take JSON paths', function () {
    expect(fn () => new Immutable('options->currency'))->toThrow(
        InvalidImmutableColumnsException::class,
        'cannot guard the JSON path "options->currency". Guard the whole column with #[Immutable(\'options\')] instead.',
    );
});

it('takes no columns as #[ImmutableModel]', function (Closure $make) {
    expect($make)->toThrow(
        InvalidImmutableColumnsException::class,
        '#[ImmutableModel] takes no columns, it guards the whole model. Use #[Immutable] to guard single columns.',
    );
})->with([
    'a column' => fn () => new ImmutableModel('number'),
    'a list' => fn () => new ImmutableModel(['number']),
]);

it('covers the whole model as #[ImmutableModel]', function () {
    expect((new ImmutableModel)->columns)->toBe(['*']);
});
