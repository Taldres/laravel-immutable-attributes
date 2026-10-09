<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Attributes\Immutable;

/**
 * Tries to release the parent's columns with an empty list.
 */
#[Immutable([])]
class ReopenedInvoice extends Invoice
{
    //
}
