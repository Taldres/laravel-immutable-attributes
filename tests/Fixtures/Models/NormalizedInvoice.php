<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Stores attributes in a different shape than the one written to the query: a
 * setter that normalizes and a cast that collapses distinct stored values.
 *
 * @property int $id
 * @property string $number
 * @property bool $paid
 * @property int $total
 */
#[Immutable('number', 'paid')]
#[Table('invoices')]
#[Unguarded]
class NormalizedInvoice extends Model
{
    use GuardsImmutableAttributes;

    protected function casts(): array
    {
        return ['paid' => 'boolean'];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function number(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtoupper(trim($value)));
    }
}
