<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

/**
 * Leaves out "café" on a table that SQLite lets have "CAFÉ" as well.
 */
#[ImmutableModel(except: ['café'])]
#[Table('accented_rows', timestamps: false)]
#[Unguarded]
class AccentedRow extends Model
{
    use GuardsImmutableAttributes;
}
