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

it('covers the whole model as #[ImmutableModel]', function () {
    expect((new ImmutableModel)->columns)->toBe(['*']);
});
