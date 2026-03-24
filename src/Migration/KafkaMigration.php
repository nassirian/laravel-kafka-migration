<?php

namespace Nassirian\LaravelKafkaMigration\Migration;

use Nassirian\LaravelKafkaMigration\Contracts\KafkaDriverInterface;
use Nassirian\LaravelKafkaMigration\Contracts\KafkaMigrationInterface;
use Nassirian\LaravelKafkaMigration\Topic\TopicBuilder;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

abstract class KafkaMigration implements KafkaMigrationInterface
{
    protected KafkaDriverInterface $kafka;

    /**
     * Run the migration — create the Kafka topic(s).
     * Subclasses must implement this method.
     */
    abstract public function up(): void;

    /**
     * Reverse the migration — delete the Kafka topic(s).
     * Subclasses must implement this method.
     */
    abstract public function down(): void;

    public function setDriver(KafkaDriverInterface $driver): void
    {
        $this->kafka = $driver;
    }

    /**
     * Create a new topic definition using the fluent builder.
     */
    protected function topic(string $name): TopicDefinition
    {
        return TopicBuilder::create($name);
    }

    /**
     * Create a topic via the configured driver.
     */
    protected function createTopic(TopicDefinition $topicDefinition): void
    {
        $this->kafka->createTopic($topicDefinition);
    }

    /**
     * Delete a topic via the configured driver.
     */
    protected function deleteTopic(string $topicName): void
    {
        $this->kafka->deleteTopic($topicName);
    }
}
