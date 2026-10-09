<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Attributes;

use Attribute;
use Taldres\ImmutableAttributes\Attributes\Immutable;

#[Attribute(Attribute::TARGET_CLASS)]
class AppendOnly extends Immutable
{
    public function __construct()
    {
        parent::__construct('*');
    }
}
