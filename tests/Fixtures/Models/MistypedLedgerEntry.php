<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Fails when it boots: #[Immutable] gets a value that is not a column name,
 * which PHPStan rejects as well, so the line says it is on purpose.
 */
#[Immutable(123)] // @phpstan-ignore argument.type
class MistypedLedgerEntry extends Model
{
    use GuardsImmutableAttributes;

    protected $table = 'ledger_entries';
}
