<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Unit;

use Nassirian\LaravelKafkaMigration\Contracts\KafkaDriverInterface;
use Nassirian\LaravelKafkaMigration\Drivers\MockDriver;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaConnectionException;
use Nassirian\LaravelKafkaMigration\KafkaManager;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

class KafkaManagerTest extends TestCase
{
    protected KafkaManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(KafkaManager::class);
    }

    public function test_it_resolves_the_mock_driver(): void
    {
        $driver = $this->manager->driver('mock');

        $this->assertInstanceOf(MockDriver::class, $driver);
    }

    public function test_it_returns_the_same_driver_instance_on_subsequent_calls(): void
    {
        $a = $this->manager->driver('mock');
        $b = $this->manager->driver('mock');

        $this->assertSame($a, $b);
    }

    public function test_it_throws_on_unknown_driver(): void
    {
        $this->expectException(KafkaConnectionException::class);
        $this->expectExceptionMessage('Unsupported Kafka driver: [unknown]');

        $this->manager->driver('unknown');
    }

    public function test_it_allows_extending_with_a_custom_driver(): void
    {
        $custom = new MockDriver([]);

        $this->manager->extend('custom', fn () => $custom);

        $driver = $this->manager->driver('custom');

        $this->assertSame($custom, $driver);
    }

    public function test_it_purges_and_forgets_a_cached_driver(): void
    {
        $first = $this->manager->driver('mock');
        $this->manager->purge('mock');
        $second = $this->manager->driver('mock');

        $this->assertNotSame($first, $second);
    }

    public function test_it_forwards_calls_to_the_default_driver(): void
    {
        // The default driver is mock (set in TestCase::getEnvironmentSetUp)
        $topic = (new TopicDefinition('test-topic'))->partitions(2);

        $this->manager->createTopic($topic);

        $this->assertTrue($this->manager->topicExists('test-topic'));
    }

    public function test_it_resolves_the_default_driver_from_config(): void
    {
        $driver = $this->manager->driver();

        $this->assertInstanceOf(KafkaDriverInterface::class, $driver);
    }
}
