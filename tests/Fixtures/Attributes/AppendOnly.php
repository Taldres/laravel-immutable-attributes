<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Attributes;

use Attribute;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;

#[Attribute(Attribute::TARGET_CLASS)]
class AppendOnly extends ImmutableModel
{
    //
}
