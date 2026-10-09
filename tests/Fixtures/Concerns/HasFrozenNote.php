<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Concerns;

use Taldres\ImmutableAttributes\Attributes\Immutable;

#[Immutable('note')]
trait HasFrozenNote
{
    //
}
