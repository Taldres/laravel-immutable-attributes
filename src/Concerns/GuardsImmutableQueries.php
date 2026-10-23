<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LogicException;
use ReflectionMethod;
use Taldres\ImmutableAttributes\Builders\ImmutableBuilder;
use Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException;

/**
 * Extends the guard of GuardsImmutableAttributes to the model's Eloquent
 * builder: a query that writes an immutable column throws before it runs.
 *
 * @phpstan-require-extends Model
 */
trait GuardsImmutableQueries
{
    public static function bootGuardsImmutableQueries(): void
    {
        // A newEloquentBuilder() of the model's own replaces the trait's
        // without notice, and with it the check that its builder is guarded.
        if ((new ReflectionMethod(static::class, 'newEloquentBuilder'))->getFileName() !== __FILE__) {
            throw new LogicException(sprintf(
                'Model [%s] defines its own newEloquentBuilder(), so %s cannot choose a guarded builder. Name the builder with the static $builder property instead.',
                static::class,
                GuardsImmutableQueries::class,
            ));
        }
    }

    /**
     * Keeps the builder the model names, provided it guards its writes, and
     * puts the guarded builder in place of Laravel's own.
     *
     * @param  QueryBuilder  $query
     * @return Builder<static>
     *
     * @throws LogicException
     */
    public function newEloquentBuilder($query): Builder
    {
        // newModelQuery() binds the builder to this model, which PHPStan reads
        // as the parent class when called through parent::.
        /** @var Builder<static> $builder */
        $builder = parent::newEloquentBuilder($query);

        if ($builder::class === Builder::class) {
            /** @var ImmutableBuilder<static> */
            return new ImmutableBuilder($query);
        }

        if (! in_array(GuardsImmutableWrites::class, class_uses_recursive($builder), true)) {
            throw new LogicException(sprintf(
                'Model [%s] uses the builder [%s], which does not guard immutable attributes. Extend %s or use %s in it.',
                static::class,
                $builder::class,
                ImmutableBuilder::class,
                GuardsImmutableWrites::class,
            ));
        }

        return $builder;
    }

    /**
     * Throws when a query writes an immutable column. The builder does not
     * load the rows it writes, so it cannot tell an unchanged value from a
     * changed one, and the exception names no key.
     *
     * @param  list<string>  $columns
     *
     * @throws ImmutableAttributeException
     */
    public function guardImmutableColumns(array $columns): void
    {
        $written = $this->immutableAttributesAmong($columns);

        if ($written !== []) {
            throw new ImmutableAttributeException(static::class, null, $written);
        }
    }

    /**
     * Provided by GuardsImmutableAttributes, which this trait needs.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    abstract protected function immutableAttributesAmong(array $keys): array;
}
