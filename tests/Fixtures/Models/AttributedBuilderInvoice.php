<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\PlainBuilder;

/**
 * Names a builder that does not guard its writes with #[UseEloquentBuilder].
 */
#[UseEloquentBuilder(PlainBuilder::class)]
class AttributedBuilderInvoice extends Invoice
{
    use GuardsImmutableQueries;
}
