<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Attributes;

use Attribute;
use Illuminate\Support\Arr;
use Taldres\ImmutableAttributes\Exceptions\InvalidImmutableColumnsException;

/**
 * Guards every attribute: once the row exists it is never updated again, not
 * even by touch() or restore(). Deleting stays possible.
 *
 * Columns can be left out with the named argument except, as in
 * #[ImmutableModel(except: ['views'])]. A column that #[Immutable] guards stays
 * guarded.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ImmutableModel extends Immutable
{
    /**
     * The columns left out of the guard.
     *
     * @var list<string>
     */
    public readonly array $except;

    /**
     * Takes no positional argument, but accepts any so that a column passed by
     * mistake reaches the exception. Read as "except", it would invert what
     * #[Immutable('number')] means and guard everything but that column.
     *
     * @throws InvalidImmutableColumnsException
     */
    public function __construct(mixed ...$arguments)
    {
        foreach (array_keys($arguments) as $name) {
            if (is_int($name)) {
                throw InvalidImmutableColumnsException::modelTakesNoColumns();
            }

            if ($name !== 'except') {
                throw InvalidImmutableColumnsException::unknownArgument($name);
            }
        }

        // Not ??: an explicit except: null is a mistake, not an absent argument.
        $this->except = $this->exceptFrom(array_key_exists('except', $arguments) ? $arguments['except'] : []);

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

    /**
     * @return list<string>
     *
     * @throws InvalidImmutableColumnsException
     */
    private function exceptFrom(mixed $except): array
    {
        $names = Arr::flatten([$except], 1);

        foreach ($names as $name) {
            if (! is_string($name) || trim($name) === '') {
                throw InvalidImmutableColumnsException::exceptInvalid($name);
            }

            if ($name === '*') {
                throw InvalidImmutableColumnsException::exceptWildcard();
            }

            if (str_contains($name, '->')) {
                throw InvalidImmutableColumnsException::exceptJsonPath($name);
            }
        }

        return array_values(array_unique($names));
    }
}
