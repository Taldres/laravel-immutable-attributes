<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\ConfiguredInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\RestampedInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\UnusableInvoice;

abstract class TestCase extends Orchestra
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    protected function tearDown(): void
    {
        ConfiguredInvoice::$frozen = [];
        ConfiguredInvoice::$loose = [];
        RestampedInvoice::$updatedAtColumn = 'updated_at';
        UnusableInvoice::$returned = [];

        parent::tearDown();
    }
}
