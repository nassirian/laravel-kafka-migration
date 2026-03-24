<?php

namespace Nassirian\LaravelKafkaMigration\Tests;

use Nassirian\LaravelKafkaMigration\KafkaMigrationServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            KafkaMigrationServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use SQLite in-memory for the repository
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Use the mock driver for all tests.
        // Disable disk persistence (store_path = null) so that each test starts
        // with a clean in-memory store and one test's topics can never bleed into
        // another test via a leftover topics.json file on disk.
        $app['config']->set('kafka-migration.driver', 'mock');
        $app['config']->set('kafka-migration.drivers.mock.store_path', null);
        $app['config']->set('kafka-migration.migrations_table', 'kafka_migrations');
        $app['config']->set('kafka-migration.migrations_path', $this->getMigrationsPath());
    }

    protected function getMigrationsPath(): string
    {
        return __DIR__ . '/fixtures/kafka-migrations';
    }
}
