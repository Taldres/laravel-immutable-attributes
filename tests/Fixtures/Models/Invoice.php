<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property Carbon $issued_at
 * @property int $total
 * @property bool $paid
 * @property string|null $note
 * @property array<string, mixed>|null $options
 */
#[Immutable('number', 'customer_id', 'issued_at', 'options')]
class Invoice extends Model
{
    use GuardsImmutableAttributes;

    protected $table = 'invoices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'paid' => 'boolean',
            'options' => 'array',
        ];
    }
}
