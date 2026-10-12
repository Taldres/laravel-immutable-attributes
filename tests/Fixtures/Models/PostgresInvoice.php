<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests\Fixtures\Models;

use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use PDO;

/**
 * Reports the pgsql driver without a database server, so only how names are
 * matched can be tested with it, not a save.
 */
class PostgresInvoice extends Invoice
{
    public function getConnection(): Connection
    {
        return new PostgresConnection(static fn () => new PDO('sqlite::memory:'), 'fixture', '', ['driver' => 'pgsql']);
    }
}
