<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A custom builder that does not guard its writes.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class PlainBuilder extends Builder
{
    //
}
