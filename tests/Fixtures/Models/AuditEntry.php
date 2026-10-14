<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Tests\Fixtures\Attributes\AppendOnly;

/**
 * @property int $id
 * @property int $amount
 * @property string|null $memo
 */
#[AppendOnly]
#[Table('ledger_entries', timestamps: false)]
#[Unguarded]
class AuditEntry extends Model
{
    use GuardsImmutableAttributes;
}
