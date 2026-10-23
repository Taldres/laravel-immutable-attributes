<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;

/**
 * Leaves a counter out of the whole-model guard, but not the update timestamp.
 *
 * @property int $id
 * @property int $total
 */
#[ImmutableModel(except: ['total'])]
#[Table('invoices')]
#[Unguarded]
class QueryTallyInvoice extends Model
{
    use GuardsImmutableAttributes;
    use GuardsImmutableQueries;
}
