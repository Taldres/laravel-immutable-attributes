<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Attributes;

use Attribute;
use Illuminate\Support\Arr;
use Taldres\ImmutableAttributes\Exceptions\InvalidImmutableColumnsException;

/**
 * Marks model attributes that may be set on insert but never change afterwards.
 *
 * Use #[ImmutableModel] to guard every attribute of the model.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Immutable
{
    /**
     * The guarded attributes; "*" stands for all of them.
     *
     * @var list<string>
     */
    public readonly array $columns;

    /**
     * Takes any value so that a wrong one reaches the same exception as every
     * other misdeclaration, naming the class, instead of a bare TypeError.
     *
     * @param  array<int, string>|string  ...$columns
     *
     * @throws InvalidImmutableColumnsException
     */
    public function __construct(mixed ...$columns)
    {
        $this->columns = $this->columnsFrom($columns);
    }

    /**
     * @param  array<array-key, mixed>  $columns
     * @return list<string>
     *
     * @throws InvalidImmutableColumnsException
     */
    protected function columnsFrom(array $columns): array
    {
        $flattened = [];

        // One level only: a list inside a list is not a column name. Arr::wrap()
        // would not do, it turns null into an empty list and drops it.
        foreach (Arr::flatten($columns, 1) as $name) {
            if (! is_string($name) || trim($name) === '') {
                throw InvalidImmutableColumnsException::invalid($name);
            }

            if (static::isPadded($name)) {
                throw InvalidImmutableColumnsException::padded($name);
            }

            $flattened[] = $name;
        }

        if ($flattened === []) {
            throw InvalidImmutableColumnsException::missing();
        }

        if (in_array('*', $flattened, true)) {
            throw InvalidImmutableColumnsException::wildcard();
        }

        foreach ($flattened as $name) {
            if (str_contains($name, '->')) {
                throw InvalidImmutableColumnsException::jsonPath($name);
            }
        }

        return array_values(array_unique($flattened));
    }

    /**
     * A name with whitespace around it, a non-breaking space included, would be
     * guarded under that name and never match the column.
     */
    protected static function isPadded(string $name): bool
    {
        return preg_match('/^\s|\s$/u', $name) === 1;
    }
}
