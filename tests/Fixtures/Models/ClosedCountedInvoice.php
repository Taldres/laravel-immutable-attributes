<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Attributes\ImmutableModel;

/**
 * Guards the whole model again without leaving anything out.
 */
#[ImmutableModel]
class ClosedCountedInvoice extends CountedInvoice
{
    //
}
