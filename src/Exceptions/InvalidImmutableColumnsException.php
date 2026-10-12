<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when #[Immutable] is declared without a column, with "*", or with
 * anything that is not a plain column name.
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

    public static function invalid(mixed $value): self
    {
        $given = is_string($value) ? 'an empty string' : get_debug_type($value);

        return new self("#[Immutable] takes column names as non-empty strings, {$given} given.");
    }

    public static function jsonPath(string $column): self
    {
        $attribute = explode('->', $column)[0];

        return new self("#[Immutable] cannot guard the JSON path \"{$column}\". Guard the whole column with #[Immutable('{$attribute}')] instead.");
    }

    /**
     * Names the class that declares the attribute, which may be a trait or a parent of the model.
     */
    public function declaredOn(string $class): self
    {
        return new self("{$this->getMessage()} Declared on [{$class}].", previous: $this);
    }
}
