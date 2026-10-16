<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Tests\Fixtures\Attributes\LooseLedger;

/**
 * Takes its guard, and the column it leaves out, from a preset.
 */
#[LooseLedger]
class PresetLedgerEntry extends Model
{
    use GuardsImmutableAttributes;

    protected $table = 'ledger_entries';

    protected $guarded = [];

    public $timestamps = false;
}
