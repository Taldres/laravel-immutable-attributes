<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when #[Immutable] is declared without a column or with "*".
 *
 * #[Immutable] lists the attributes it guards; #[ImmutableModel] guards the
 * whole model. The check runs when the model boots, so the mistake surfaces
 * before the first update.
 */
class InvalidImmutableColumnsException extends InvalidArgumentException
{
    public static function missing(): self
    {
        return new self('#[Immutable] needs at least one column. Use #[ImmutableModel] to guard the whole model.');
    }

    public static function wildcard(): self
    {
        return new self('#[Immutable] does not take "*". Use #[ImmutableModel] to guard the whole model.');
    }

    /**
     * Names the class that declares the attribute, which may be a trait or a parent of the model.
     */
    public function declaredOn(string $class): self
    {
        return new self("{$this->getMessage()} Declared on [{$class}].", previous: $this);
    }
}
