<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Fails when it boots: #[Immutable] names a column with a space after it.
 */
#[Immutable('memo ')]
#[Table('ledger_entries')]
class PaddedLedgerEntry extends Model
{
    use GuardsImmutableAttributes;
}
