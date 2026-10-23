<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\ForeignBuilder;

/**
 * Shares the guarding builder without opting in with GuardsImmutableQueries.
 */
class SharedBuilderInvoice extends Invoice
{
    protected static string $builder = ForeignBuilder::class;
}
