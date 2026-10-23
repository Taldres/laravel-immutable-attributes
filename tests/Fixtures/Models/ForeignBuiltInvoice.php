<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\ForeignBuilder;

/**
 * Names a custom builder that uses GuardsImmutableWrites.
 */
class ForeignBuiltInvoice extends Invoice
{
    use GuardsImmutableQueries;

    protected static string $builder = ForeignBuilder::class;
}
