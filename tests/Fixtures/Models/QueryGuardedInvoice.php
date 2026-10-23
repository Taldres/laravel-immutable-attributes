<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;

/**
 * Opts in on a child, while the parent carries GuardsImmutableAttributes.
 */
class QueryGuardedInvoice extends Invoice
{
    use GuardsImmutableQueries;
}
