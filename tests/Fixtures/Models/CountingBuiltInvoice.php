<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\CountingBuilder;

class CountingBuiltInvoice extends Invoice
{
    use GuardsImmutableQueries;

    protected static string $builder = CountingBuilder::class;
}
