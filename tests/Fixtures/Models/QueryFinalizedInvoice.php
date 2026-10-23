<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableQueries;

/**
 * Guards the whole row once its stored note reads "final".
 *
 * @property int $id
 * @property string $number
 * @property string|null $note
 */
class QueryFinalizedInvoice extends Model
{
    use GuardsImmutableAttributes;
    use GuardsImmutableQueries;

    protected $table = 'invoices';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function getImmutableAttributes(): array
    {
        return $this->getOriginal('note') === 'final' ? ['*'] : ['number'];
    }
}
