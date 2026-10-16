<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Guarded as a whole, but a counter, a note and the update timestamp may change.
 */
#[ImmutableModel(except: ['total', 'note', 'updated_at'])]
class CountedInvoice extends Model
{
    use GuardsImmutableAttributes;

    protected $table = 'invoices';

    protected $guarded = [];
}
