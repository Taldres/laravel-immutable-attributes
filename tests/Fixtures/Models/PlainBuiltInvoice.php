<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\PlainBuilder;

/**
 * Opts in, but names a builder that does not guard its writes.
 */
class PlainBuiltInvoice extends Invoice
{
    use GuardsImmutableQueries;

    protected static string $builder = PlainBuilder::class;
}
