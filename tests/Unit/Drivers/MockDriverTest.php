<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Unit\Drivers;

use Nassirian\LaravelKafkaMigration\Drivers\MockDriver;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaTopicException;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

class MockDriverTest extends TestCase
{
    protected MockDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new MockDriver([]);
    }

    public function test_it_creates_a_topic(): void
    {
        $topic = (new TopicDefinition('orders'))->partitions(3)->replicationFactor(1);

        $this->driver->createTopic($topic);

        $this->assertTrue($this->driver->topicExists('orders'));
    }

    public function test_it_lists_created_topics(): void
    {
        $this->driver->createTopic(new TopicDefinition('topic-a'));
        $this->driver->createTopic(new TopicDefinition('topic-b'));

        $topics = $this->driver->listTopics();

        $this->assertContains('topic-a', $topics);
        $this->assertContains('topic-b', $topics);
        $this->assertCount(2, $topics);
    }

    public function test_it_returns_false_for_non_existing_topic(): void
    {
        $this->assertFalse($this->driver->topicExists('ghost-topic'));
    }

    public function test_it_deletes_a_topic(): void
    {
        $this->driver->createTopic(new TopicDefinition('orders'));
        $this->driver->deleteTopic('orders');

        $this->assertFalse($this->driver->topicExists('orders'));
    }

    public function test_it_returns_metadata_for_existing_topic(): void
    {
        $topic = (new TopicDefinition('payments'))
            ->partitions(5)
            ->replicationFactor(2);

        $this->driver->createTopic($topic);

        $meta = $this->driver->getTopicMetadata('payments');

        $this->assertSame('payments', $meta['name']);
        $this->assertSame(5, $meta['partitions']);
        $this->assertSame(2, $meta['replication_factor']);
    }

    public function test_it_returns_empty_metadata_for_non_existing_topic(): void
    {
        $meta = $this->driver->getTopicMetadata('ghost');

        $this->assertSame([], $meta);
    }

    public function test_it_alters_topic_config(): void
    {
        $this->driver->createTopic(new TopicDefinition('orders'));
        $this->driver->alterTopicConfig('orders', ['retention.ms' => '86400000']);

        $meta = $this->driver->getTopicMetadata('orders');

        $this->assertSame('86400000', $meta['configs']['retention.ms']);
    }

    public function test_it_throws_when_altering_non_existing_topic(): void
    {
        $this->expectException(KafkaTopicException::class);

        $this->driver->alterTopicConfig('ghost', ['retention.ms' => '86400000']);
    }

    public function test_it_disconnects_and_clears_state(): void
    {
        $this->driver->createTopic(new TopicDefinition('orders'));
        $this->driver->disconnect();

        // After disconnect, the internal store is cleared
        $this->assertEmpty($this->driver->getTopics());
    }

    public function test_reset_clears_all_topics(): void
    {
        $this->driver->createTopic(new TopicDefinition('a'));
        $this->driver->createTopic(new TopicDefinition('b'));

        $this->driver->reset();

        $this->assertEmpty($this->driver->getTopics());
    }

    public function test_set_topics_seeds_the_store(): void
    {
        $this->driver->setTopics([
            'orders' => ['name' => 'orders', 'partitions' => 3, 'replication_factor' => 1, 'configs' => []],
        ]);

        $this->assertTrue($this->driver->topicExists('orders'));
    }

    public function test_create_multiple_topics_independently(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->driver->createTopic(new TopicDefinition("topic-{$i}"));
        }

        $this->assertCount(5, $this->driver->listTopics());
    }

    public function test_it_stores_topic_configs_on_creation(): void
    {
        $topic = (new TopicDefinition('events'))
            ->retentionMs(3_600_000)
            ->cleanupPolicy('compact');

        $this->driver->createTopic($topic);

        $meta = $this->driver->getTopicMetadata('events');

        $this->assertSame('3600000', $meta['configs']['retention.ms']);
        $this->assertSame('compact', $meta['configs']['cleanup.policy']);
    }
}
