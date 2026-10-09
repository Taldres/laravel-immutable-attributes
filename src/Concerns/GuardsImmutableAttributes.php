<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Concerns;

use Illuminate\Database\Eloquent\Model;
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

    public static function bootGuardsImmutableAttributes(): void
    {
        // Resolve now, so a misconfigured #[Immutable] fails when the model
        // boots rather than on its first update.
        static::$resolvedImmutableAttributes[static::class] ??= static::resolveImmutableAttributes();

        // Model increment() and decrement() write through their own query and
        // never reach getDirtyForUpdate(), but they do fire "updating".
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

    public function isImmutableAttribute(string $key): bool
    {
        $immutable = $this->getImmutableAttributes();

        return in_array('*', $immutable, true) || in_array($key, $immutable, true);
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
        $changed = array_values(array_filter($keys, $this->isImmutableAttribute(...)));

        if ($changed !== []) {
            throw ImmutableAttributeException::on($this, $changed);
        }
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
