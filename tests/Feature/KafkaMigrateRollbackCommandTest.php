<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Feature;

use Nassirian\LaravelKafkaMigration\Contracts\KafkaMigrationRepositoryInterface;
use Nassirian\LaravelKafkaMigration\KafkaManager;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;

class KafkaMigrateRollbackCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(KafkaMigrationRepositoryInterface::class)->createRepository();

        // Migrate first so there's something to rollback
        $this->artisan('kafka:migrate', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ]);
    }

    public function test_rollback_removes_topics(): void
    {
        $this->artisan('kafka:migrate:rollback', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])->assertSuccessful();

        /** @var KafkaManager $manager */
        $manager = $this->app->make(KafkaManager::class);

        $this->assertFalse($manager->topicExists('orders'));
        $this->assertFalse($manager->topicExists('user-events'));
    }

    public function test_rollback_removes_migration_logs(): void
    {
        $this->artisan('kafka:migrate:rollback', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])->assertSuccessful();

        /** @var KafkaMigrationRepositoryInterface $repo */
        $repo = $this->app->make(KafkaMigrationRepositoryInterface::class);

        $this->assertEmpty($repo->getRan());
    }

    public function test_rollback_with_step_option(): void
    {
        $this->artisan('kafka:migrate:rollback', [
            '--path'  => [$this->getMigrationsPath()],
            '--step'  => 1,
            '--force' => true,
        ])->assertSuccessful();

        /** @var KafkaMigrationRepositoryInterface $repo */
        $repo = $this->app->make(KafkaMigrationRepositoryInterface::class);

        // One migration still ran
        $this->assertCount(1, $repo->getRan());
    }

    public function test_rollback_pretend_does_not_delete_topics(): void
    {
        $this->artisan('kafka:migrate:rollback', [
            '--path'    => [$this->getMigrationsPath()],
            '--pretend' => true,
            '--force'   => true,
        ])->assertSuccessful();

        /** @var KafkaManager $manager */
        $manager = $this->app->make(KafkaManager::class);

        $this->assertTrue($manager->topicExists('orders'));
        $this->assertTrue($manager->topicExists('user-events'));
    }

    public function test_rollback_shows_nothing_when_no_migrations(): void
    {
        // Roll back all first
        $this->artisan('kafka:migrate:rollback', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ]);

        // Roll back again — nothing left
        $this->artisan('kafka:migrate:rollback', [
            '--path'  => [$this->getMigrationsPath()],
            '--force' => true,
        ])->assertSuccessful();

        // Repo should still be empty
        $repo = $this->app->make(KafkaMigrationRepositoryInterface::class);
        $this->assertEmpty($repo->getRan());
    }
}
