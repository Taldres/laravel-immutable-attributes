<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableWrites;

/**
 * Overrides update() and keeps the guard by calling the aliased trait method.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class CountingBuilder extends Builder
{
    use GuardsImmutableWrites {
        update as guardedUpdate;
    }

    public static int $updates = 0;

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        static::$updates++;

        return $this->guardedUpdate($values);
    }
}
