<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Guarded as a whole, but a deleted row may be restored.
 *
 * @property int $id
 * @property int $amount
 * @property string|null $memo
 */
#[ImmutableModel(except: ['deleted_at'])]
class RestorableLedgerEntry extends Model
{
    use GuardsImmutableAttributes;
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'ledger_entries';

    protected $guarded = [];
}
