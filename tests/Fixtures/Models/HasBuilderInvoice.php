<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\HasBuilder;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\InvoiceBuilder;

/**
 * Uses Laravel's HasBuilder, whose newEloquentBuilder() collides with the trait's.
 */
class HasBuilderInvoice extends Invoice
{
    /** @use HasBuilder<InvoiceBuilder<static>> */
    use GuardsImmutableQueries, HasBuilder {
        GuardsImmutableQueries::newEloquentBuilder insteadof HasBuilder;
    }

    protected static string $builder = InvoiceBuilder::class;
}
