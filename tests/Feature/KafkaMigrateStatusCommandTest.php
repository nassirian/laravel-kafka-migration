<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Feature;

use Nassirian\LaravelKafkaMigration\Contracts\KafkaMigrationRepositoryInterface;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;

class KafkaMigrateStatusCommandTest extends TestCase
{
    public function test_status_shows_error_when_repository_does_not_exist(): void
    {
        $this->artisan('kafka:migrate:status', [
            '--path' => [$this->getMigrationsPath()],
        ])->assertFailed();
    }

    public function test_status_shows_pending_migrations(): void
    {
        $this->app->make(KafkaMigrationRepositoryInterface::class)->createRepository();

        $this->artisan('kafka:migrate:status', [
            '--path' => [$this->getMigrationsPath()],
        ])->assertSuccessful();
    }

    public function test_status_shows_ran_migrations_after_migrate(): void
    {
        $this->app->make(KafkaMigrationRepositoryInterface::class)->createRepository();

        $this->artisan('kafka:migrate', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ]);

        $this->artisan('kafka:migrate:status', [
            '--path' => [$this->getMigrationsPath()],
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Yes');
    }
}
