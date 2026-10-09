<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Attributes;

use Attribute;

/**
 * Marks model attributes that may be set on insert but never change afterwards.
 *
 * Without arguments or with "*" the whole model is immutable once it exists;
 * an empty list guards nothing.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Immutable
{
    /**
     * @var list<string>
     */
    public readonly array $columns;

    /**
     * @param  array<int, string>|string  ...$columns
     */
    public function __construct(array|string ...$columns)
    {
        if ($columns === []) {
            $this->columns = ['*'];

            return;
        }

        $flattened = [];

        foreach ($columns as $column) {
            array_push($flattened, ...(array) $column);
        }

        $this->columns = array_values(array_unique($flattened));
    }
}
