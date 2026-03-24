<?php

namespace Nassirian\LaravelKafkaMigration\Contracts;

use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

interface KafkaDriverInterface
{
    /**
     * Create a Kafka topic.
     */
    public function createTopic(TopicDefinition $topic): void;

    /**
     * Delete a Kafka topic.
     */
    public function deleteTopic(string $topicName): void;

    /**
     * Check if a topic exists.
     */
    public function topicExists(string $topicName): bool;

    /**
     * List all existing topics.
     *
     * @return string[]
     */
    public function listTopics(): array;

    /**
     * Get topic metadata / configuration.
     *
     * @return array<string, mixed>
     */
    public function getTopicMetadata(string $topicName): array;

    /**
     * Update an existing topic's configuration.
     *
     * @param array<string, mixed> $configs
     */
    public function alterTopicConfig(string $topicName, array $configs): void;

    /**
     * Close / disconnect the underlying client.
     */
    public function disconnect(): void;
}
