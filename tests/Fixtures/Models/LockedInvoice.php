<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Attributes\ImmutableModel;

/**
 * A whole-model guard on a table with timestamps.
 */
#[ImmutableModel]
class LockedInvoice extends Invoice
{
    //
}
