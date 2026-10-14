<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Decides at runtime instead of with #[Immutable].
 *
 * @property int $id
 * @property string $number
 * @property string|null $note
 */
#[Table('invoices')]
#[Unguarded]
class ConfiguredInvoice extends Model
{
    use GuardsImmutableAttributes;

    /**
     * @var list<string>
     */
    public static array $frozen = [];

    /**
     * @var list<string>
     */
    public static array $loose = [];

    /**
     * @return list<string>
     */
    public function getImmutableAttributes(): array
    {
        return static::$frozen;
    }

    /**
     * @return list<string>
     */
    public function getMutableAttributes(): array
    {
        return static::$loose;
    }
}
