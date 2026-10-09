<?php

declare(strict_types=1);

use Taldres\ImmutableAttributes\Attributes\Immutable;

it('takes columns as arguments', function () {
    expect((new Immutable('number', 'customer_id'))->columns)->toBe(['number', 'customer_id']);
});

it('takes columns as an array', function () {
    expect((new Immutable(['number', 'customer_id']))->columns)->toBe(['number', 'customer_id']);
});

it('drops duplicate columns', function () {
    expect((new Immutable('number', ['number', 'total']))->columns)->toBe(['number', 'total']);
});

it('covers the whole model without columns', function () {
    expect((new Immutable)->columns)->toBe(['*']);
});
