<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
     * An amount of zero still writes its column: the database casts the stored
     * value to a number, so "001" in a text column comes back as "1". The
     * Eloquent builder adds the update timestamp to every increment it runs.
     *
     * @param  array<array-key, mixed>  $amounts
     * @param  array<array-key, mixed>  $extra
     * @return list<string>
     */
    private function columnsWrittenByIncrement(array $amounts, array $extra): array
    {
        $columns = [];

        foreach ([...array_keys($amounts), ...array_keys($extra)] as $column) {
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
        $changed = $this->immutableAttributesAmong($keys);

        if ($changed !== []) {
            throw ImmutableAttributeException::on($this, $changed);
        }
    }

    /**
     * The immutable attributes the keys write, each named once as declared.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function immutableAttributesAmong(array $keys): array
    {
        $written = [];

        foreach ($keys as $key) {
            // Asking the public method keeps a model's override of it in charge.
            if ($this->isImmutableAttribute($key)) {
                $attribute = $this->immutableAttributeWrittenBy($key) ?? self::columnWrittenBy($key);
                $written[$attribute] = $attribute;
            }
        }

        return array_values($written);
    }

    /**
     * The immutable attribute a key writes, as declared, or the written column
     * when "*" guards the model and the column is not left out. A key counts as
     * the column it writes: "options->currency" writes "options", and the SQLite
     * and PostgreSQL grammars drop any qualifier, as in "invoices.number".
     *
     * Databases disagree on whether "NUMBER" and "number" are one column, so
     * the guard errs towards throwing: an immutable attribute matches in any
     * case, while a column left out must be named exactly.
     */
    private function immutableAttributeWrittenBy(string $key): ?string
    {
        $column = self::columnWrittenBy($key);
        $immutable = $this->getImmutableAttributes();

        array_walk($immutable, fn (mixed $attribute) => $this->assertColumnName($attribute, 'getImmutableAttributes'));

        $folded = mb_strtolower($column, 'UTF-8');
        $declared = Arr::first($immutable, fn (string $listed): bool => mb_strtolower(self::columnWrittenBy($listed), 'UTF-8') === $folded);

        if ($declared !== null) {
            return $declared;
        }

        if (! in_array('*', $immutable, true)) {
            return null;
        }

        $mutable = $this->getMutableAttributes();

        array_walk($mutable, fn (mixed $attribute) => $this->assertColumnName($attribute, 'getMutableAttributes'));

        $leftOut = Arr::first($mutable, fn (string $listed): bool => self::columnWrittenBy($listed) === $column);

        return $leftOut === null ? $column : null;
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
        $trimmed = is_string($attribute) ? Str::trim($attribute) : null;

        if ($trimmed === null || $trimmed === '' || $trimmed !== $attribute) {
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
        return array_values(
            self::attributesDeclaredOn(static::class)
                ->flatMap(fn (Immutable $attribute): array => $attribute->columns)
                ->unique()
                ->all(),
        );
    }

    /**
     * What every #[ImmutableModel] leaves out, so that a child model or a trait
     * can guard more than its parent but never less.
     *
     * @return list<string>
     */
    protected static function resolveMutableAttributes(): array
    {
        $lists = self::attributesDeclaredOn(static::class)
            ->filter(fn (Immutable $attribute): bool => $attribute instanceof ImmutableModel)
            ->map(fn (ImmutableModel $attribute): array => $attribute->except)
            ->all();

        return $lists === [] ? [] : array_values(array_intersect(...$lists));
    }

    /**
     * Collects #[Immutable] and its subclasses, such as #[ImmutableModel], from
     * the class, its parents, and the traits any of them use.
     *
     * @param  class-string  $class
     * @return Collection<int, Immutable>
     *
     * @throws InvalidImmutableColumnsException
     */
    private static function attributesDeclaredOn(string $class): Collection
    {
        return (new Collection([$class, ...class_parents($class), ...class_uses_recursive($class)]))
            ->flatMap(fn (string $declaring): array => self::attributesOn($declaring));
    }

    /**
     * @return list<Immutable>
     *
     * @throws InvalidImmutableColumnsException
     */
    private static function attributesOn(string $class): array
    {
        // class_uses_recursive() returns the traits as plain strings, not class-strings.
        $reflection = new ReflectionClass($class); // @phpstan-ignore argument.type

        return array_map(
            function (ReflectionAttribute $attribute) use ($class): Immutable {
                try {
                    return $attribute->newInstance();
                } catch (InvalidImmutableColumnsException $e) {
                    throw $e->declaredOn($class);
                }
            },
            $reflection->getAttributes(Immutable::class, ReflectionAttribute::IS_INSTANCEOF),
        );
    }
}
