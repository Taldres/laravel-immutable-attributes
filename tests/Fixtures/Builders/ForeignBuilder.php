<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableWrites;

/**
 * A builder that extends another base, such as one from a package.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class ForeignBuilder extends Builder
{
    use GuardsImmutableWrites;
}
