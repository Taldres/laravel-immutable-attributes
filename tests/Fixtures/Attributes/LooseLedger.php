<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Attributes;

use Attribute;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;

/**
 * A preset that guards a ledger but lets the memo change.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class LooseLedger extends ImmutableModel
{
    public function __construct()
    {
        parent::__construct(except: ['memo']);
    }
}
