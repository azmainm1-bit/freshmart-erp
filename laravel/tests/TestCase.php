<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Hard safety guard (docs/ARCHITECTURE.md): tests may use RefreshDatabase
     * and other destructive operations, so refuse to even boot the test
     * suite unless it is unmistakably pointed at the test database. This
     * protects dev/staging/production data from an accidental
     * misconfiguration (e.g. a stale .env.testing or wrong DB_DATABASE).
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! str_ends_with((string) $database, '_test') && $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against database '{$database}' — it does not look like a test ".
                'database (expected a name ending in "_test"). Check DB_DATABASE in phpunit.xml / .env.testing.'
            );
        }

        return $app;
    }
}
