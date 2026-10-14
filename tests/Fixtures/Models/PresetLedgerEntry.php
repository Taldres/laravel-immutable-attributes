<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Tests\Fixtures\Attributes\LooseLedger;

/**
 * Takes its guard, and the column it leaves out, from a preset.
 */
#[LooseLedger]
#[Table('ledger_entries', timestamps: false)]
#[Unguarded]
class PresetLedgerEntry extends Model
{
    use GuardsImmutableAttributes;
}
