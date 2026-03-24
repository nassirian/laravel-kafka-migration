<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Feature;

use Nassirian\LaravelKafkaMigration\Contracts\KafkaMigrationRepositoryInterface;
use Nassirian\LaravelKafkaMigration\KafkaManager;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;

class KafkaMigrateResetCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(KafkaMigrationRepositoryInterface::class)->createRepository();

        // Run migrations first
        $this->artisan('kafka:migrate', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ]);
    }

    public function test_reset_rolls_back_all_migrations(): void
    {
        $this->artisan('kafka:migrate:reset', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])->assertSuccessful();

        /** @var KafkaManager $manager */
        $manager = $this->app->make(KafkaManager::class);

        $this->assertFalse($manager->topicExists('orders'));
        $this->assertFalse($manager->topicExists('user-events'));
    }

    public function test_reset_clears_migration_log(): void
    {
        $this->artisan('kafka:migrate:reset', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])->assertSuccessful();

        /** @var KafkaMigrationRepositoryInterface $repo */
        $repo = $this->app->make(KafkaMigrationRepositoryInterface::class);

        $this->assertEmpty($repo->getRan());
    }

    public function test_reset_shows_nothing_when_no_migrations_ran(): void
    {
        // Reset once
        $this->artisan('kafka:migrate:reset', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ]);

        // Reset again
        $this->artisan('kafka:migrate:reset', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Nothing to reset');
    }
}
