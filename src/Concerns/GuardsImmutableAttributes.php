<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
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
     * @var array<class-string, list<string>>
     */
    protected static array $resolvedMutableAttributes = [];

    public static function bootGuardsImmutableAttributes(): void
    {
        // Resolve now, so a misconfigured #[Immutable] fails when the model
        // boots rather than on its first update.
        static::$resolvedImmutableAttributes[static::class] ??= static::resolveImmutableAttributes();
        static::$resolvedMutableAttributes[static::class] ??= static::resolveMutableAttributes();

        // Runs before the listeners that were registered later, so a rejected
        // save does not reach their side effects. getDirtyForUpdate() covers
        // what this cannot see.
        static::updating(static function (self $model): void {
            $model->guardImmutableAttributes(array_keys($model->getDirty()));
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

    /**
     * The attributes that stay mutable although "*" guards the model, the way
     * Laravel's getFillable() lists what a guarded "*" lets through. An
     * attribute that getImmutableAttributes() names is guarded all the same.
     *
     * @return list<string>
     */
    public function getMutableAttributes(): array
    {
        return static::$resolvedMutableAttributes[static::class] ??= static::resolveMutableAttributes();
    }

    public function isImmutableAttribute(string $key): bool
    {
        return $this->immutableAttributeWrittenBy($key) !== null;
    }

    /**
     * The query receives the raw extra values, while the model holds them after
     * casts and setters, so the dirty check alone can miss a change. The check
     * runs here and not on "updating", which the Quietly variants and
     * Model::withoutEvents() skip. A model that does not exist yet is left
     * alone: Laravel runs its increment as a query on the whole table.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     * @param  string  $method
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method): int|false
    {
        if ($this->exists) {
            $this->guardImmutableAttributes([
                ...array_keys($this->getDirty()),
                ...$this->columnsWrittenByIncrement([$column => $amount], $extra),
            ]);
        }

        return parent::incrementOrDecrement($column, $amount, $extra, $method);
    }

    /**
     * @param  array<string, float|int>  $columns
     * @param  array<string, mixed>  $extra
     */
    protected function incrementOrDecrementEach(array $columns, array $extra, string $method): int|false
    {
        if ($this->exists) {
            $this->guardImmutableAttributes([
                ...array_keys($this->getDirty()),
                ...$this->columnsWrittenByIncrement($columns, $extra),
            ]);
        }

        return parent::incrementOrDecrementEach($columns, $extra, $method);
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
     * when "*" guards the model and the column is not left out. A key counts as
     * the column it writes: "options->currency" writes "options", and the SQLite
     * and PostgreSQL grammars drop any qualifier, as in "invoices.number".
     */
    private function immutableAttributeWrittenBy(string $key): ?string
    {
        $column = self::columnWrittenBy($key);
        $immutable = $this->getImmutableAttributes();

        array_walk($immutable, fn (mixed $attribute) => $this->assertColumnName($attribute, 'getImmutableAttributes'));

        if (($declared = $this->listedColumn($immutable, $column)) !== null) {
            return $declared;
        }

        if (! in_array('*', $immutable, true)) {
            return null;
        }

        $mutable = $this->getMutableAttributes();

        array_walk($mutable, fn (mixed $attribute) => $this->assertColumnName($attribute, 'getMutableAttributes'));

        return $this->listedColumn($mutable, $column) === null ? $column : null;
    }

    /**
     * The entry of the list that names the column, as listed. Case counts only
     * on PostgreSQL, where Laravel quotes identifiers and "NUMBER" is a
     * different column, so the connection is asked only when nothing but case
     * differs. Case is folded for the whole of Unicode: MySQL and MariaDB do so
     * for "CAFÉ" and "café", while they keep "cafe" apart.
     *
     * @param  list<string>  $columns
     */
    private function listedColumn(array $columns, string $column): ?string
    {
        $differentCase = null;

        foreach ($columns as $listed) {
            $name = self::columnWrittenBy($listed);

            if ($name === $column) {
                return $listed;
            }

            if (mb_strtolower($name, 'UTF-8') === mb_strtolower($column, 'UTF-8')) {
                $differentCase ??= $listed;
            }
        }

        if ($differentCase !== null && $this->getConnection()->getDriverName() !== 'pgsql') {
            return $differentCase;
        }

        return null;
    }

    /**
     * An override of getImmutableAttributes() or getMutableAttributes() is the
     * application's own code, so what it returns is checked the way a
     * declaration is.
     *
     * @throws InvalidImmutableColumnsException
     */
    private function assertColumnName(mixed $attribute, string $method): void
    {
        if (! is_string($attribute) || trim($attribute) === '') {
            throw InvalidImmutableColumnsException::returnedBy(static::class, $method, $attribute);
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
        $columns = [];

        foreach (self::attributesDeclaredOn(new ReflectionClass(static::class)) as $attribute) {
            array_push($columns, ...$attribute->columns);
        }

        return array_values(array_unique($columns));
    }

    /**
     * What every #[ImmutableModel] leaves out, so that a child model or a trait
     * can guard more than its parent but never less.
     *
     * @return list<string>
     */
    protected static function resolveMutableAttributes(): array
    {
        $lists = [];

        foreach (self::attributesDeclaredOn(new ReflectionClass(static::class)) as $attribute) {
            if ($attribute instanceof ImmutableModel) {
                $lists[] = $attribute->except;
            }
        }

        return $lists === [] ? [] : array_values(array_intersect(...$lists));
    }

    /**
     * Collects #[Immutable] and its subclasses, such as #[ImmutableModel], from
     * the class, the traits it uses, and its parents.
     *
     * @template TClass of object
     *
     * @param  ReflectionClass<TClass>  $class
     * @return list<Immutable>
     *
     * @throws InvalidImmutableColumnsException
     */
    private static function attributesDeclaredOn(ReflectionClass $class): array
    {
        $attributes = [];

        foreach ($class->getAttributes(Immutable::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            try {
                $attributes[] = $attribute->newInstance();
            } catch (InvalidImmutableColumnsException $e) {
                throw $e->declaredOn($class->getName());
            }
        }

        foreach ($class->getTraits() as $trait) {
            array_push($attributes, ...self::attributesDeclaredOn($trait));
        }

        if ($parent = $class->getParentClass()) {
            array_push($attributes, ...self::attributesDeclaredOn($parent));
        }

        return $attributes;
    }
}
