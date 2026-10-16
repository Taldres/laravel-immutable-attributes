<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $invoice_id
 * @property int $amount
 */
class TouchingLedgerEntry extends Model
{
    public $timestamps = false;

    protected $table = 'ledger_entries';

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $touches = ['invoice'];

    /**
     * @return BelongsTo<LockedInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(LockedInvoice::class, 'invoice_id');
    }
}
