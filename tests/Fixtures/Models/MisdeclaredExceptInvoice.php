<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Fails when it boots: except cannot hold "*".
 */
#[ImmutableModel(except: ['*'])]
class MisdeclaredExceptInvoice extends Model
{
    use GuardsImmutableAttributes;

    protected $table = 'invoices';

    protected $guarded = [];
}
