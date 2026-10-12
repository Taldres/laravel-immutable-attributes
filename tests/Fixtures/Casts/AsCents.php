<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\DeviatesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast that increments by its own value object, so the amount passed to
 * increment() is an object rather than a number.
 *
 * @implements CastsAttributes<Cents, Cents|int>
 */
class AsCents implements CastsAttributes, DeviatesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Cents
    {
        return Cents::from($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return Cents::from($value)->value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function increment(mixed $model, string $key, mixed $value, array $attributes): Cents
    {
        return new Cents(Cents::from($attributes[$key] ?? 0)->value + Cents::from($value)->value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function decrement(mixed $model, string $key, mixed $value, array $attributes): Cents
    {
        return new Cents(Cents::from($attributes[$key] ?? 0)->value - Cents::from($value)->value);
    }
}
