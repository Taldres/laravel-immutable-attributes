<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

/**
 * Overrides getImmutableAttributes() with whatever a test puts in $returned.
 */
class UnusableInvoice extends Invoice
{
    /**
     * @var array<array-key, mixed>
     */
    public static array $returned = [];

    /**
     * @return list<string>
     */
    public function getImmutableAttributes(): array
    {
        return static::$returned; // @phpstan-ignore return.type (the point of this fixture)
    }
}
