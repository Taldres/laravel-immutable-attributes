<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Concerns;

use Taldres\ImmutableAttributes\Attributes\Immutable;

/**
 * A misdeclared trait: the model using it must fail when it boots.
 */
#[Immutable([])]
trait HasEmptyImmutableList
{
    //
}
