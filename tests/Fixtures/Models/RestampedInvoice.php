<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

/**
 * Writes its update timestamp to a configurable column, or to none.
 */
class RestampedInvoice extends ConfiguredInvoice
{
    public static ?string $updatedAtColumn = 'updated_at';

    public function getUpdatedAtColumn(): ?string
    {
        return static::$updatedAtColumn;
    }
}
