<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Attributes\ImmutableModel;

/**
 * Leaves out less than its parent, so only what both leave out stays mutable.
 */
#[ImmutableModel(except: ['note', 'paid'])]
class NarrowerCountedInvoice extends CountedInvoice
{
    //
}
