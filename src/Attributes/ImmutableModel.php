<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Attributes;

use Attribute;

/**
 * Guards every attribute: once the row exists it is never updated again, not
 * even by touch() or restore(). Deleting stays possible.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ImmutableModel extends Immutable
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param  array<array-key, array<int, string>|string>  $columns
     * @return list<string>
     */
    protected function columnsFrom(array $columns): array
    {
        return ['*'];
    }
}
