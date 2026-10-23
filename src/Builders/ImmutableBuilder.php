<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableWrites;

/**
 * The builder GuardsImmutableQueries gives a model that uses Laravel's own.
 * Extend it for a custom builder of such a model.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class ImmutableBuilder extends Builder
{
    use GuardsImmutableWrites;
}
