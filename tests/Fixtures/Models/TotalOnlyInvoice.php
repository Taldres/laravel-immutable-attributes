<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Leaves out the counter but not the update timestamp that every save writes.
 */
#[ImmutableModel(except: ['total'])]
#[Table('invoices')]
#[Unguarded]
class TotalOnlyInvoice extends Model
{
    use GuardsImmutableAttributes;
}
