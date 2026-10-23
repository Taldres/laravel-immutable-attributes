<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\InvoiceBuilder;

/**
 * Defines its own newEloquentBuilder(), which replaces the trait's.
 */
class SelfBuildingInvoice extends Invoice
{
    use GuardsImmutableQueries;

    /**
     * @param  QueryBuilder  $query
     * @return Builder<static>
     */
    public function newEloquentBuilder($query): Builder
    {
        /** @var InvoiceBuilder<static> */
        return new InvoiceBuilder($query);
    }
}
