<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\InvoiceBuilder;

/**
 * Names a custom builder that extends ImmutableBuilder.
 */
class BuiltInvoice extends Invoice
{
    use GuardsImmutableQueries;

    protected static string $builder = InvoiceBuilder::class;
}
