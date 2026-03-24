<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Feature;

use Nassirian\LaravelKafkaMigration\Contracts\KafkaMigrationRepositoryInterface;
use Nassirian\LaravelKafkaMigration\Drivers\MockDriver;
use Nassirian\LaravelKafkaMigration\KafkaManager;
use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrator;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;

class KafkaMigrateCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the repository exists for commands that need it
        $this->app->make(KafkaMigrationRepositoryInterface::class)->createRepository();
    }

    public function test_kafka_migrate_runs_all_pending_migrations(): void
    {
        $this->artisan('kafka:migrate', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])->assertSuccessful();

        /** @var KafkaManager $manager */
        $manager = $this->app->make(KafkaManager::class);

        $this->assertTrue($manager->topicExists('orders'));
        $this->assertTrue($manager->topicExists('user-events'));
    }

    public function test_kafka_migrate_creates_repository_if_not_exists(): void
    {
        /** @var KafkaMigrationRepositoryInterface $repo */
        $repo = $this->app->make(KafkaMigrationRepositoryInterface::class);

        // Drop and re-check
        $this->app['db']->getSchemaBuilder()->dropIfExists('kafka_migrations');
        $this->assertFalse($repo->repositoryExists());

        $this->artisan('kafka:migrate', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])->assertSuccessful();

        $this->assertTrue($repo->repositoryExists());
    }

    public function test_kafka_migrate_shows_nothing_to_migrate(): void
    {
        // Run once
        $this->artisan('kafka:migrate', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])->assertSuccessful();

        // Run again — nothing new to migrate
        $this->artisan('kafka:migrate', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])->assertSuccessful();

        // Still the same 2 migrations in the log, no more
        $repo = $this->app->make(KafkaMigrationRepositoryInterface::class);
        $this->assertCount(2, $repo->getRan());
    }

    public function test_kafka_migrate_pretend_does_not_create_topics(): void
    {
        $this->artisan('kafka:migrate', [
            '--path'    => [$this->getMigrationsPath()],
            '--pretend' => true,
            '--force'   => true,
        ])->assertSuccessful();

        /** @var KafkaManager $manager */
        $manager = $this->app->make(KafkaManager::class);

        $this->assertFalse($manager->topicExists('orders'));
        $this->assertFalse($manager->topicExists('user-events'));
    }
}
