<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Attributes;

use Attribute;
use Taldres\ImmutableAttributes\Exceptions\InvalidImmutableColumnsException;

/**
 * Guards every attribute: once the row exists it is never updated again, not
 * even by touch() or restore(). Deleting stays possible.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ImmutableModel extends Immutable
{
    /**
     * Takes no columns, but accepts any so that one passed by mistake reaches
     * the exception instead of being dropped, which would guard more than meant.
     *
     *
     * @throws InvalidImmutableColumnsException
     */
    public function __construct(mixed ...$columns)
    {
        if ($columns !== []) {
            throw InvalidImmutableColumnsException::modelTakesNoColumns();
        }

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
