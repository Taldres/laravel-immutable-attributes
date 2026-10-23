<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

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
class QueryTouchingLedgerEntry extends Model
{
    /**
     * @var class-string<Model>
     */
    public static string $parent = QueryLockedInvoice::class;

    public $timestamps = false;

    protected $table = 'ledger_entries';

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $touches = ['invoice'];

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
