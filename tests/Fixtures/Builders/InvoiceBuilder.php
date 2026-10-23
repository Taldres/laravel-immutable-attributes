<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Builders;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Builders\ImmutableBuilder;

/**
 * @template TModel of Model
 *
 * @extends ImmutableBuilder<TModel>
 */
class InvoiceBuilder extends ImmutableBuilder
{
    /**
     * @return $this
     */
    public function unpaid(): static
    {
        return $this->where('paid', false);
    }
}
