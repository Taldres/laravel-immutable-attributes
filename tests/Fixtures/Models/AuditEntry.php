<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Tests\Fixtures\Attributes\AppendOnly;

/**
 * @property int $id
 * @property int $amount
 * @property string|null $memo
 */
#[AppendOnly]
class AuditEntry extends Model
{
    use GuardsImmutableAttributes;

    public $timestamps = false;

    protected $table = 'ledger_entries';

    protected $guarded = [];
}
