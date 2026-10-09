<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Tests\Fixtures\Concerns\HasFrozenNote;

#[Immutable(['total'])]
class CreditNote extends Invoice
{
    use HasFrozenNote;
}
