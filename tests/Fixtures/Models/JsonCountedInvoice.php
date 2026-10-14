<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Guarded as a whole, but the JSON column may change.
 *
 * @property array<string, mixed>|null $options
 */
#[ImmutableModel(except: ['options', 'updated_at'])]
#[Table('invoices')]
#[Unguarded]
class JsonCountedInvoice extends Model
{
    use GuardsImmutableAttributes;

    protected function casts(): array
    {
        return ['options' => 'array'];
    }
}
