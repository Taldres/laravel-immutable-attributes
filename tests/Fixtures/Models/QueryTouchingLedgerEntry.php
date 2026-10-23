<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Touches parents whose model the test picks.
 *
 * @property int $id
 * @property int|null $invoice_id
 * @property int $amount
 */
#[Table('ledger_entries', timestamps: false)]
#[Unguarded]
#[Touches('invoice')]
class QueryTouchingLedgerEntry extends Model
{
    /**
     * @var class-string<Model>
     */
    public static string $parent = QueryLockedInvoice::class;

    /**
     * @return BelongsTo<Model, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(static::$parent, 'invoice_id');
    }

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(static::$parent, 'invoice_ledger_entry', 'ledger_entry_id', 'invoice_id');
    }
}
