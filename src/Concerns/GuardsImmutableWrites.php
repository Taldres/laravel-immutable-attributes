<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Concerns;

use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Rejects every write of an immutable column through an Eloquent builder whose
 * model uses GuardsImmutableQueries, before the query runs. A builder of a
 * model without it writes as before.
 *
 * Inserts and deletes stay open, soft deletes included. Writes through
 * toBase(), DB::table() and raw SQL are not checked.
 *
 * @phpstan-require-extends Builder
 */
trait GuardsImmutableWrites
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        $columns = [...self::keysOf($values), ...$this->updatedAtColumnWritten()];

        if (($deletedAt = $this->deletedAtColumnOfSoftDelete($values)) === null) {
            $this->guardWrittenColumns($columns);
        } elseif ($this->writesImmutableColumn($columns)) {
            // A soft delete passes only for the rows it deletes: a deletion
            // timestamp that is set already is immutable like any other column.
            // As a scope, the constraint groups an orWhere() before it.
            $this->callScope(fn (Builder $query) => $query->whereNull($deletedAt));
        }

        return parent::update($values);
    }

    /**
     * New rows may set immutable columns; only the columns written on a
     * conflict are checked, as Laravel picks them when $update is null.
     *
     * @param  array<array-key, array<string, mixed>|scalar|object|null>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<array-key, mixed>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        if ($values !== []) {
            $first = Arr::first($values);
            $columns = [];

            // A raw expression in place of a column name is not inspected.
            foreach ($update ?? array_keys(is_array($first) ? $first : $values) as $key => $column) {
                if (is_string($key)) {
                    $columns[] = $key;
                } elseif (is_string($column)) {
                    $columns[] = $column;
                }
            }

            $this->guardWrittenColumns([...$columns, ...$this->updatedAtColumnWritten()]);
        }

        return parent::upsert($values, $uniqueBy, $update);
    }

    /**
     * @param  array<int, string>|string|null  $column
     */
    public function touch($column = null): int|false
    {
        $this->guardWrittenColumns($column ? array_values(Arr::wrap($column)) : $this->updatedAtColumnWritten());

        return parent::touch($column);
    }

    /**
     * An amount of zero still writes its column, so it is checked as well.
     *
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     */
    public function increment($column, $amount = 1, array $extra = []): int
    {
        $this->guardWrittenColumns($this->columnsWrittenByIncrement(is_string($column) ? [$column] : [], $extra));

        return parent::increment($column, $amount, $extra);
    }

    /**
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     */
    public function decrement($column, $amount = 1, array $extra = []): int
    {
        $this->guardWrittenColumns($this->columnsWrittenByIncrement(is_string($column) ? [$column] : [], $extra));

        return parent::decrement($column, $amount, $extra);
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function incrementEach(array $columns, array $extra = []): int
    {
        $this->guardWrittenColumns($this->columnsWrittenByIncrement(self::keysOf($columns), $extra));

        return parent::incrementEach($columns, $extra);
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function decrementEach(array $columns, array $extra = []): int
    {
        $this->guardWrittenColumns($this->columnsWrittenByIncrement(self::keysOf($columns), $extra));

        return parent::decrementEach($columns, $extra);
    }

    /**
     * Laravel learns only after its own existence query whether the row is
     * updated or inserted, so the check runs inside the closure it calls.
     * An insert may set immutable columns.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|(Closure(bool): array<string, mixed>)  $values
     */
    public function updateOrInsert(array $attributes, array|callable $values = []): mixed
    {
        return parent::updateOrInsert($attributes, /** @return array<string, mixed> */ function (bool $exists) use ($values): array {
            $values = $values instanceof Closure ? $values($exists) : $values;

            if ($exists) {
                $this->guardWrittenColumns(self::keysOf($values));
            }

            return $values;
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateFrom(array $values): mixed
    {
        $this->guardWrittenColumns(self::keysOf($values));

        return parent::updateFrom($values);
    }

    /**
     * The qualified deletion timestamp when the values soft delete. Laravel
     * soft deletes through update(), from the model as from the builder's
     * delete(). Deleting stays possible, as it is for the model.
     *
     * @param  array<array-key, mixed>  $values
     */
    private function deletedAtColumnOfSoftDelete(array $values): ?string
    {
        $model = $this->getModel();

        if (! method_exists($model, 'getDeletedAtColumn') || ! is_string($deletedAt = $model->getDeletedAtColumn())) {
            return null;
        }

        $updatedAt = $this->updatedAtColumnWritten();
        $deletes = false;

        foreach ($values as $key => $value) {
            $column = Str::afterLast((string) $key, '.');

            if ($column === $deletedAt && $value !== null) {
                $deletes = true;
            } elseif (! in_array($column, $updatedAt, true)) {
                return null;
            }
        }

        // The builder qualifies with the table alias, as SoftDeletingScope does.
        return $deletes ? $this->qualifyColumn($deletedAt) : null;
    }

    /**
     * @param  list<string>  $columns
     * @param  array<array-key, mixed>  $extra
     * @return list<string>
     */
    private function columnsWrittenByIncrement(array $columns, array $extra): array
    {
        return [...$columns, ...self::keysOf($extra), ...$this->updatedAtColumnWritten()];
    }

    /**
     * The Eloquent builder adds the update timestamp to every update it runs.
     *
     * @return list<string>
     */
    private function updatedAtColumnWritten(): array
    {
        $model = $this->getModel();

        if ($model->usesTimestamps() && ($column = $model->getUpdatedAtColumn()) !== null) {
            return [$column];
        }

        return [];
    }

    /**
     * @param  list<string>  $columns
     */
    private function guardWrittenColumns(array $columns): void
    {
        $model = $this->modelToAsk();

        if ($columns !== [] && method_exists($model, 'guardImmutableColumns')) {
            $model->guardImmutableColumns($columns);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function writesImmutableColumn(array $columns): bool
    {
        $model = $this->modelToAsk();

        if (! method_exists($model, 'guardImmutableColumns') || ! method_exists($model, 'isImmutableAttribute')) {
            return false;
        }

        foreach ($columns as $column) {
            if ($model->isImmutableAttribute($column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A builder may start from a loaded model, as toQuery() does, but writes
     * rows it never loads, so a runtime list is asked on a new instance.
     */
    private function modelToAsk(): Model
    {
        return $this->getModel()->newInstance();
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private static function keysOf(array $values): array
    {
        return array_map(strval(...), array_keys($values));
    }
}
