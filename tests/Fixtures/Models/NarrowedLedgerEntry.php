<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Fails when it boots: #[ImmutableModel] guards the whole model and takes no
 * column to narrow that down.
 */
#[ImmutableModel('memo')]
class NarrowedLedgerEntry extends Model
{
    use GuardsImmutableAttributes;

    protected $table = 'ledger_entries';
}
