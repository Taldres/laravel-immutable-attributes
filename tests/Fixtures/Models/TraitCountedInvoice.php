<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Tests\Fixtures\Concerns\LeavesOutCounter;

/**
 * Leaves out less than the trait it uses, so only what both leave out stays mutable.
 */
#[ImmutableModel(except: ['note', 'updated_at'])]
class TraitCountedInvoice extends Model
{
    use GuardsImmutableAttributes;
    use LeavesOutCounter;

    protected $table = 'invoices';

    protected $guarded = [];
}
