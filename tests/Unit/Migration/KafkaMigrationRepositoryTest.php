<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Unit\Migration;

use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrationRepository;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;

class KafkaMigrationRepositoryTest extends TestCase
{
    protected KafkaMigrationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new KafkaMigrationRepository($this->app['db'], 'kafka_migrations');
        $this->repo->createRepository();
    }

    public function test_it_creates_the_repository_table(): void
    {
        $this->assertTrue($this->repo->repositoryExists());
    }

    public function test_it_does_not_throw_creating_repository_twice(): void
    {
        $this->repo->createRepository(); // Second call should be idempotent

        $this->assertTrue($this->repo->repositoryExists());
    }

    public function test_repository_exists_returns_false_before_creation(): void
    {
        $fresh = new KafkaMigrationRepository($this->app['db'], 'non_existent_kafka_table');

        $this->assertFalse($fresh->repositoryExists());
    }

    public function test_it_logs_a_migration(): void
    {
        $this->repo->log('2024_01_01_000000_create_orders_topic', 1);

        $ran = $this->repo->getRan();

        $this->assertContains('2024_01_01_000000_create_orders_topic', $ran);
    }

    public function test_it_returns_all_ran_migrations_in_order(): void
    {
        $this->repo->log('2024_01_01_000001_create_b_topic', 1);
        $this->repo->log('2024_01_01_000000_create_a_topic', 1);

        $ran = $this->repo->getRan();

        $this->assertSame([
            '2024_01_01_000000_create_a_topic',
            '2024_01_01_000001_create_b_topic',
        ], $ran);
    }

    public function test_it_deletes_a_migration_log(): void
    {
        $this->repo->log('2024_01_01_000000_create_orders_topic', 1);
        $this->repo->delete('2024_01_01_000000_create_orders_topic');

        $this->assertEmpty($this->repo->getRan());
    }

    public function test_get_next_batch_number_starts_at_one(): void
    {
        $this->assertSame(1, $this->repo->getNextBatchNumber());
    }

    public function test_get_next_batch_number_increments(): void
    {
        $this->repo->log('migration_a', 1);
        $this->repo->log('migration_b', 1);

        $this->assertSame(2, $this->repo->getNextBatchNumber());
    }

    public function test_get_last_batch_number_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repo->getLastBatchNumber());
    }

    public function test_get_last_returns_empty_when_no_migrations(): void
    {
        $this->assertSame([], $this->repo->getLast());
    }

    public function test_get_last_returns_last_batch_migrations(): void
    {
        $this->repo->log('migration_a', 1);
        $this->repo->log('migration_b', 2);
        $this->repo->log('migration_c', 2);

        $last = $this->repo->getLast();

        $this->assertContains('migration_b', $last);
        $this->assertContains('migration_c', $last);
        $this->assertNotContains('migration_a', $last);
    }

    public function test_get_migrations_by_batch_groups_correctly(): void
    {
        $this->repo->log('a', 1);
        $this->repo->log('b', 1);
        $this->repo->log('c', 2);

        $byBatch = $this->repo->getMigrationsByBatch();

        $this->assertCount(2, $byBatch);
        $this->assertContains('a', $byBatch[1]);
        $this->assertContains('b', $byBatch[1]);
        $this->assertContains('c', $byBatch[2]);
    }
}
