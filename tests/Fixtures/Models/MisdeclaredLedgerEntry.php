<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Tests\Fixtures\Concerns\HasEmptyImmutableList;

/**
 * Fails when it boots: its trait declares #[Immutable] without a column.
 */
class MisdeclaredLedgerEntry extends Model
{
    use GuardsImmutableAttributes;
    use HasEmptyImmutableList;

    protected $table = 'ledger_entries';
}
