<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Fails when it boots: except cannot hold "*".
 */
#[ImmutableModel(except: ['*'])]
#[Table('invoices')]
#[Unguarded]
class MisdeclaredExceptInvoice extends Model
{
    use GuardsImmutableAttributes;
}
