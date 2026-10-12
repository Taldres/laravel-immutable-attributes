<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Concerns;

use Taldres\ImmutableAttributes\Attributes\ImmutableModel;

#[ImmutableModel(except: ['total', 'note', 'updated_at'])]
trait LeavesOutCounter
{
    //
}
