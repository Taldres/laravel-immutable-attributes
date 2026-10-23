<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;

/**
 * A whole-model guard on a table with timestamps and soft deletes.
 *
 * @property int $id
 * @property string $number
 * @property int $total
 * @property string|null $note
 */
#[ImmutableModel]
#[Table('invoices')]
#[Unguarded]
class QueryLockedInvoice extends Model
{
    use GuardsImmutableAttributes;
    use GuardsImmutableQueries;
    use SoftDeletes;
}
