<?php

namespace Nassirian\LaravelKafkaMigration\Drivers;

use Nassirian\LaravelKafkaMigration\Exceptions\KafkaTopicException;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

/**
 * In-memory mock driver for testing and local development.
 * Does NOT connect to a real Kafka broker.
 */
class MockDriver extends AbstractKafkaDriver
{
    /** @var array<string, array<string, mixed>> */
    protected array $topics = [];

    protected function connect(): void
    {
        // Load persisted state from disk if store_path is configured
        $storePath = $this->config['store_path'] ?? null;
        if ($storePath && file_exists($storePath . '/topics.json')) {
            $data = json_decode(file_get_contents($storePath . '/topics.json'), true);
            $this->topics = is_array($data) ? $data : [];
        }
    }

    public function createTopic(TopicDefinition $topic): void
    {
        $this->ensureConnected();

        $this->topics[$topic->getName()] = $topic->toArray();
        $this->persist();
    }

    public function deleteTopic(string $topicName): void
    {
        $this->ensureConnected();

        unset($this->topics[$topicName]);
        $this->persist();
    }

    public function topicExists(string $topicName): bool
    {
        $this->ensureConnected();

        return isset($this->topics[$topicName]);
    }

    /**
     * @return string[]
     */
    public function listTopics(): array
    {
        $this->ensureConnected();

        return array_keys($this->topics);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTopicMetadata(string $topicName): array
    {
        $this->ensureConnected();

        return $this->topics[$topicName] ?? [];
    }

    /**
     * @param array<string, mixed> $configs
     */
    public function alterTopicConfig(string $topicName, array $configs): void
    {
        $this->ensureConnected();

        if (! isset($this->topics[$topicName])) {
            throw new KafkaTopicException("Topic '$topicName' does not exist.");
        }

        foreach ($configs as $key => $value) {
            $this->topics[$topicName]['configs'][$key] = (string) $value;
        }

        $this->persist();
    }

    public function disconnect(): void
    {
        $this->topics    = [];
        $this->connected = false;
    }

    // ------------------------------------------------------------------
    // Test helpers
    // ------------------------------------------------------------------

    /**
     * Return the in-memory topics store (useful in assertions).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTopics(): array
    {
        return $this->topics;
    }

    /**
     * Seed the in-memory store directly (useful in tests).
     *
     * @param array<string, array<string, mixed>> $topics
     */
    public function setTopics(array $topics): void
    {
        $this->topics    = $topics;
        $this->connected = true;
    }

    /**
     * Reset the mock store.
     */
    public function reset(): void
    {
        $this->topics    = [];
        $this->connected = false;
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    protected function persist(): void
    {
        $storePath = $this->config['store_path'] ?? null;
        if (! $storePath) {
            return;
        }

        if (! is_dir($storePath)) {
            mkdir($storePath, 0755, true);
        }

        file_put_contents($storePath . '/topics.json', json_encode($this->topics, JSON_PRETTY_PRINT));
    }
}
