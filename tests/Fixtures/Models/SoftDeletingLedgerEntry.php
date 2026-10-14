<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * @property int $id
 * @property int $amount
 * @property string|null $memo
 */
#[ImmutableModel]
#[Table('ledger_entries', timestamps: false)]
#[Unguarded]
class SoftDeletingLedgerEntry extends Model
{
    use GuardsImmutableAttributes;
    use SoftDeletes;
}
