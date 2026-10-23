<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;

/**
 * Guarded as a whole, but a counter, a note and the update timestamp may change.
 */
class QueryCountedInvoice extends CountedInvoice
{
    use GuardsImmutableQueries;
}
