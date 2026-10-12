<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException;
use Taldres\ImmutableAttributes\Exceptions\InvalidImmutableColumnsException;

/**
 * @phpstan-require-extends Model
 */
trait GuardsImmutableAttributes
{
    /**
     * @var array<class-string, list<string>>
     */
    protected static array $resolvedImmutableAttributes = [];

    /**
     * The columns an increment or decrement is about to write to the database.
     *
     * @var list<string>
     */
    protected array $pendingIncrementColumns = [];

    public static function bootGuardsImmutableAttributes(): void
    {
        // Resolve now, so a misconfigured #[Immutable] fails when the model
        // boots rather than on its first update.
        static::$resolvedImmutableAttributes[static::class] ??= static::resolveImmutableAttributes();

        // Model increment() and decrement() write through their own query and
        // never reach getDirtyForUpdate(), but they do fire "updating".
        static::updating(static function (self $model): void {
            $model->guardImmutableAttributes([...array_keys($model->getDirty()), ...$model->pendingIncrementColumns]);
        });
    }

    /**
     * The attributes that may not change once the model exists; "*" covers all of them.
     *
     * @return list<string>
     */
    public function getImmutableAttributes(): array
    {
        return static::$resolvedImmutableAttributes[static::class] ??= static::resolveImmutableAttributes();
    }

    public function isImmutableAttribute(string $key): bool
    {
        return $this->immutableAttributeWrittenBy($key) !== null;
    }

    /**
     * The query receives the raw extra values, while the model holds them after
     * casts and setters, so the dirty check alone can miss a change.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     * @param  string  $method
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method): int|false
    {
        $outer = $this->pendingIncrementColumns;
        $this->pendingIncrementColumns = $this->columnsWrittenByIncrement([$column => $amount], $extra);

        try {
            return parent::incrementOrDecrement($column, $amount, $extra, $method);
        } finally {
            $this->pendingIncrementColumns = $outer;
        }
    }

    /**
     * Model::incrementEach() arrived in Laravel 13.3; the override is never
     * called on earlier versions.
     *
     * @todo Laravel 13.3: Once composer.json requires ^13.3, drop the note
     * above, the incrementEach() skips in the tests and the Laravel 13.3 notes
     * in the README and Boost skill.
     *
     * @param  array<string, float|int>  $columns
     * @param  array<string, mixed>  $extra
     */
    protected function incrementOrDecrementEach(array $columns, array $extra, string $method): int|false
    {
        $outer = $this->pendingIncrementColumns;
        $this->pendingIncrementColumns = $this->columnsWrittenByIncrement($columns, $extra);

        try {
            return parent::incrementOrDecrementEach($columns, $extra, $method);
        } finally {
            $this->pendingIncrementColumns = $outer;
        }
    }

    /**
     * An amount of zero leaves its column alone, while an amount that is not a
     * number, such as a value object for a cast, counts as a change. The Eloquent
     * builder adds the update timestamp to every increment it runs.
     *
     * @param  array<array-key, mixed>  $amounts
     * @param  array<array-key, mixed>  $extra
     * @return list<string>
     */
    private function columnsWrittenByIncrement(array $amounts, array $extra): array
    {
        $columns = [];

        foreach ($amounts as $column => $amount) {
            if (! is_numeric($amount) || (float) $amount !== 0.0) {
                $columns[] = (string) $column;
            }
        }

        foreach (array_keys($extra) as $column) {
            $columns[] = (string) $column;
        }

        if ($this->usesTimestamps() && ($updatedAt = $this->getUpdatedAtColumn()) !== null) {
            $columns[] = $updatedAt;
        }

        return $columns;
    }

    /**
     * Runs after every "updating" listener and also on saves without events, so
     * it catches what the listener registered at boot cannot see.
     *
     * @return array<string, mixed>
     */
    protected function getDirtyForUpdate(): array
    {
        $dirty = parent::getDirtyForUpdate();

        $this->guardImmutableAttributes(array_keys($dirty));

        return $dirty;
    }

    /**
     * @param  list<string>  $keys
     *
     * @throws ImmutableAttributeException
     */
    protected function guardImmutableAttributes(array $keys): void
    {
        $changed = [];

        foreach ($keys as $key) {
            // Asking the public method keeps a model's override of it in charge.
            if ($this->isImmutableAttribute($key)) {
                $attribute = $this->immutableAttributeWrittenBy($key) ?? self::columnWrittenBy($key);
                $changed[$attribute] = $attribute;
            }
        }

        if ($changed !== []) {
            throw ImmutableAttributeException::on($this, array_values($changed));
        }
    }

    /**
     * The immutable attribute a key writes, as declared, or the written column
     * when the whole model is guarded. A key counts as the column it writes:
     * "options->currency" writes "options", and the SQLite and PostgreSQL
     * grammars drop any qualifier, as in "invoices.number". Case counts only on
     * PostgreSQL, where Laravel quotes identifiers and "NUMBER" is a different
     * column, so the connection is asked only when nothing but case differs.
     */
    private function immutableAttributeWrittenBy(string $key): ?string
    {
        $column = self::columnWrittenBy($key);
        $immutable = $this->getImmutableAttributes();
        $differentCase = null;

        array_walk($immutable, $this->assertColumnName(...));

        foreach ($immutable as $attribute) {
            $declared = self::columnWrittenBy($attribute);

            if ($declared === $column) {
                return $attribute;
            }

            if (strcasecmp($declared, $column) === 0) {
                $differentCase ??= $attribute;
            }
        }

        if ($differentCase !== null && $this->getConnection()->getDriverName() !== 'pgsql') {
            return $differentCase;
        }

        return in_array('*', $immutable, true) ? $column : null;
    }

    /**
     * An override of getImmutableAttributes() is the application's own code, so
     * what it returns is checked the way a declaration is.
     *
     * @throws InvalidImmutableColumnsException
     */
    private function assertColumnName(mixed $attribute): void
    {
        if (! is_string($attribute) || trim($attribute) === '') {
            throw InvalidImmutableColumnsException::returnedBy(static::class, $attribute);
        }
    }

    private static function columnWrittenBy(string $key): string
    {
        return Str::afterLast(Str::before($key, '->'), '.');
    }

    /**
     * @return list<string>
     */
    protected static function resolveImmutableAttributes(): array
    {
        return array_values(array_unique(self::immutableColumnsDeclaredOn(new ReflectionClass(static::class))));
    }

    /**
     * Collects #[Immutable] and its subclasses, such as #[ImmutableModel], from
     * the class, the traits it uses, and its parents.
     *
     * @template TClass of object
     *
     * @param  ReflectionClass<TClass>  $class
     * @return list<string>
     *
     * @throws InvalidImmutableColumnsException
     */
    private static function immutableColumnsDeclaredOn(ReflectionClass $class): array
    {
        $columns = [];

        foreach ($class->getAttributes(Immutable::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            try {
                array_push($columns, ...$attribute->newInstance()->columns);
            } catch (InvalidImmutableColumnsException $e) {
                throw $e->declaredOn($class->getName());
            }
        }

        foreach ($class->getTraits() as $trait) {
            array_push($columns, ...self::immutableColumnsDeclaredOn($trait));
        }

        if ($parent = $class->getParentClass()) {
            array_push($columns, ...self::immutableColumnsDeclaredOn($parent));
        }

        return $columns;
    }
}
