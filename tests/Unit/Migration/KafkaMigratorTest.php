<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Unit\Migration;

use Illuminate\Filesystem\Filesystem;
use Nassirian\LaravelKafkaMigration\Drivers\MockDriver;
use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrationRepository;
use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrator;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;

class KafkaMigratorTest extends TestCase
{
    protected KafkaMigrator $migrator;
    protected MockDriver $driver;
    protected KafkaMigrationRepository $repo;
    protected string $migrationsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver         = new MockDriver([]);
        $this->repo           = new KafkaMigrationRepository($this->app['db'], 'kafka_migrations');
        $this->repo->createRepository();
        $this->migrator       = new KafkaMigrator($this->repo, $this->driver, new Filesystem());
        $this->migrationsPath = $this->getMigrationsPath();
    }

    public function test_it_discovers_migration_files(): void
    {
        $files = $this->migrator->getMigrationFiles([$this->migrationsPath]);

        $this->assertNotEmpty($files);
        $this->assertArrayHasKey('2024_01_01_000000_create_orders_topic', $files);
        $this->assertArrayHasKey('2024_01_01_000001_create_user_events_topic', $files);
    }

    public function test_it_extracts_migration_name_from_path(): void
    {
        $name = $this->migrator->getMigrationName('/some/path/2024_01_01_000000_create_orders_topic.php');

        $this->assertSame('2024_01_01_000000_create_orders_topic', $name);
    }

    public function test_it_runs_pending_migrations(): void
    {
        $migrated = $this->migrator->run([$this->migrationsPath]);

        $this->assertCount(2, $migrated);
        $this->assertTrue($this->driver->topicExists('orders'));
        $this->assertTrue($this->driver->topicExists('user-events'));
    }

    public function test_it_does_not_rerun_already_ran_migrations(): void
    {
        $this->migrator->run([$this->migrationsPath]);
        $this->migrator->clearNotes();

        $secondRun = $this->migrator->run([$this->migrationsPath]);

        $this->assertEmpty($secondRun);
        $this->assertStringContainsString('Nothing to migrate', implode(' ', $this->migrator->getNotes()));
    }

    public function test_it_records_migrations_in_repository(): void
    {
        $this->migrator->run([$this->migrationsPath]);

        $ran = $this->repo->getRan();

        $this->assertContains('2024_01_01_000000_create_orders_topic', $ran);
        $this->assertContains('2024_01_01_000001_create_user_events_topic', $ran);
    }

    public function test_it_rolls_back_last_batch(): void
    {
        $this->migrator->run([$this->migrationsPath]);
        $rolledBack = $this->migrator->rollback([$this->migrationsPath]);

        $this->assertNotEmpty($rolledBack);

        // After rollback, topics should no longer exist
        $this->assertFalse($this->driver->topicExists('orders'));
        $this->assertFalse($this->driver->topicExists('user-events'));
    }

    public function test_rollback_removes_migration_records(): void
    {
        $this->migrator->run([$this->migrationsPath]);
        $this->migrator->rollback([$this->migrationsPath]);

        $this->assertEmpty($this->repo->getRan());
    }

    public function test_rollback_with_step_option(): void
    {
        $this->migrator->run([$this->migrationsPath]);

        // Roll back only 1 step (the most recent migration)
        $rolledBack = $this->migrator->rollback([$this->migrationsPath], false, 1);

        $this->assertCount(1, $rolledBack);
    }

    public function test_rollback_nothing_when_no_migrations_ran(): void
    {
        $rolledBack = $this->migrator->rollback([$this->migrationsPath]);

        $this->assertEmpty($rolledBack);
    }

    public function test_it_resets_all_migrations(): void
    {
        $this->migrator->run([$this->migrationsPath]);
        $reset = $this->migrator->reset([$this->migrationsPath]);

        $this->assertCount(2, $reset);
        $this->assertEmpty($this->repo->getRan());
        $this->assertFalse($this->driver->topicExists('orders'));
        $this->assertFalse($this->driver->topicExists('user-events'));
    }

    public function test_reset_nothing_when_no_migrations_ran(): void
    {
        $reset = $this->migrator->reset([$this->migrationsPath]);

        $this->assertEmpty($reset);
    }

    public function test_pretend_mode_does_not_create_topics(): void
    {
        $this->migrator->run([$this->migrationsPath], pretend: true);

        $this->assertFalse($this->driver->topicExists('orders'));
        $this->assertFalse($this->driver->topicExists('user-events'));
    }

    public function test_pretend_mode_does_not_log_to_repository(): void
    {
        $this->migrator->run([$this->migrationsPath], pretend: true);

        $this->assertEmpty($this->repo->getRan());
    }

    public function test_it_returns_migration_status(): void
    {
        $this->migrator->run([$this->migrationsPath]);
        $status = $this->migrator->status([$this->migrationsPath]);

        $this->assertNotEmpty($status);

        $names = array_column($status, 'name');
        $this->assertContains('2024_01_01_000000_create_orders_topic', $names);

        foreach ($status as $row) {
            $this->assertTrue($row['ran']);
            $this->assertNotNull($row['batch']);
        }
    }

    public function test_status_shows_pending_migrations(): void
    {
        $status = $this->migrator->status([$this->migrationsPath]);

        foreach ($status as $row) {
            $this->assertFalse($row['ran']);
            $this->assertNull($row['batch']);
        }
    }

    public function test_notes_are_added_during_migration(): void
    {
        $this->migrator->run([$this->migrationsPath]);

        $this->assertNotEmpty($this->migrator->getNotes());
    }

    public function test_clear_notes_empties_the_notes(): void
    {
        $this->migrator->run([$this->migrationsPath]);
        $this->migrator->clearNotes();

        $this->assertEmpty($this->migrator->getNotes());
    }

    public function test_repository_exists_delegates_to_repository(): void
    {
        $this->assertTrue($this->migrator->repositoryExists());
    }

    public function test_get_repository_returns_repository(): void
    {
        $this->assertSame($this->repo, $this->migrator->getRepository());
    }

    public function test_it_ignores_non_existent_migration_path(): void
    {
        $files = $this->migrator->getMigrationFiles(['/non/existent/path']);

        $this->assertEmpty($files);
    }
}
