<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Attributes\Immutable;

/**
 * Guards the counter with #[Immutable] although its parent leaves it out.
 */
#[Immutable('total')]
class PinnedCountedInvoice extends CountedInvoice
{
    //
}
