<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Attributes;

use Attribute;
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
     * @param  array<int, string>|string  ...$columns
     *
     * @throws InvalidImmutableColumnsException
     */
    public function __construct(array|string ...$columns)
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

        foreach ($columns as $column) {
            foreach ((array) $column as $name) {
                if (! is_string($name) || trim($name) === '') {
                    throw InvalidImmutableColumnsException::invalid($name);
                }

                $flattened[] = $name;
            }
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
}
