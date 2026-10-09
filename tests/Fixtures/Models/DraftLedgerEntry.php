<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * @property int $id
 * @property int $amount
 * @property string|null $memo
 */
#[Immutable([])]
class DraftLedgerEntry extends Model
{
    use GuardsImmutableAttributes;

    public $timestamps = false;

    protected $table = 'ledger_entries';

    protected $guarded = [];
}
