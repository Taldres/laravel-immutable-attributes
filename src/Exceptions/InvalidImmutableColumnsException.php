<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Exceptions;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Thrown when the attributes to guard are misdeclared: #[Immutable] without a
 * column, with "*" or with anything that is not a plain column name,
 * #[ImmutableModel] with columns or with an except that is not a list of plain
 * column names, or a getImmutableAttributes() or getMutableAttributes()
 * override that returns something other than column names.
 *
 * #[Immutable] lists the attributes it guards; #[ImmutableModel] guards the
 * whole model. A declaration is checked when the model boots, so the mistake
 * surfaces before the first update; an override is checked when it is used.
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

    public static function modelTakesNoColumns(): self
    {
        return new self('#[ImmutableModel] takes no columns, it guards the whole model. Use #[Immutable] to guard single columns, or except: [...] to leave some out.');
    }

    public static function unknownArgument(string $name): self
    {
        return new self("#[ImmutableModel] has no argument named \"{$name}\". It takes except: [...] to leave columns out.");
    }

    public static function exceptInvalid(mixed $value): self
    {
        $given = is_string($value) ? 'an empty string' : get_debug_type($value);

        return new self("#[ImmutableModel] takes column names as non-empty strings in except, {$given} given.");
    }

    public static function exceptWildcard(): self
    {
        return new self('#[ImmutableModel] cannot leave out "*". Drop the attribute to leave the whole model unguarded.');
    }

    public static function exceptJsonPath(string $column): self
    {
        $attribute = Str::before($column, '->');

        return new self("#[ImmutableModel] cannot leave out the JSON path \"{$column}\". The guard compares whole columns; leave out '{$attribute}' instead.");
    }

    public static function invalid(mixed $value): self
    {
        $given = is_string($value) ? 'an empty string' : get_debug_type($value);

        return new self("#[Immutable] takes column names as non-empty strings, {$given} given.");
    }

    public static function jsonPath(string $column): self
    {
        $attribute = Str::before($column, '->');

        return new self("#[Immutable] cannot guard the JSON path \"{$column}\". Guard the whole column with #[Immutable('{$attribute}')] instead.");
    }

    /**
     * @param  class-string  $model
     */
    public static function returnedBy(string $model, string $method, mixed $value): self
    {
        $given = is_string($value) ? 'an empty string' : get_debug_type($value);

        return new self("{$model}::{$method}() must return column names as non-empty strings, {$given} given.");
    }

    /**
     * Names the class that declares the attribute, which may be a trait or a parent of the model.
     */
    public function declaredOn(string $class): self
    {
        return new self("{$this->getMessage()} Declared on [{$class}].", previous: $this);
    }
}
