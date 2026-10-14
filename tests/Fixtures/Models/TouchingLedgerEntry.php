<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $invoice_id
 * @property int $amount
 */
#[Table('ledger_entries', timestamps: false)]
#[Unguarded]
#[Touches('invoice')]
class TouchingLedgerEntry extends Model
{
    /**
     * @return BelongsTo<LockedInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(LockedInvoice::class, 'invoice_id');
    }
}
