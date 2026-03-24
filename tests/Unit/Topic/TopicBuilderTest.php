<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Unit\Topic;

use Nassirian\LaravelKafkaMigration\Tests\TestCase;
use Nassirian\LaravelKafkaMigration\Topic\TopicBuilder;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

class TopicBuilderTest extends TestCase
{
    public function test_it_creates_a_topic_definition_instance(): void
    {
        $topic = TopicBuilder::create('my-topic');

        $this->assertInstanceOf(TopicDefinition::class, $topic);
    }

    public function test_it_sets_the_topic_name(): void
    {
        $topic = TopicBuilder::create('my-topic');

        $this->assertSame('my-topic', $topic->getName());
    }

    public function test_created_topic_is_fully_chainable(): void
    {
        $topic = TopicBuilder::create('events')
            ->partitions(12)
            ->replicationFactor(3)
            ->retentionMs(86_400_000);

        $this->assertSame(12, $topic->getPartitions());
        $this->assertSame(3, $topic->getReplicationFactor());
        $this->assertSame('86400000', $topic->getConfigs()['retention.ms']);
    }
}
