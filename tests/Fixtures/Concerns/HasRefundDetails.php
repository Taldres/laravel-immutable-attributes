<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Concerns;

/**
 * Declares nothing itself; its columns come from the trait it uses.
 */
trait HasRefundDetails
{
    use HasFrozenNote;
}
