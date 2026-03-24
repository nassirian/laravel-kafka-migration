<?php

namespace Nassirian\LaravelKafkaMigration\Topic;

use InvalidArgumentException;

class TopicDefinition
{
    protected string $name;
    protected int $partitions = 1;
    protected int $replicationFactor = 1;
    /** @var array<string, string> */
    protected array $configs = [];

    public function __construct(string $name)
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Topic name cannot be empty.');
        }
        $this->name = $name;
    }

    /**
     * Set the number of partitions.
     */
    public function partitions(int $count): static
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Partition count must be at least 1.');
        }
        $this->partitions = $count;

        return $this;
    }

    /**
     * Set the replication factor.
     */
    public function replicationFactor(int $factor): static
    {
        if ($factor < 1) {
            throw new InvalidArgumentException('Replication factor must be at least 1.');
        }
        $this->replicationFactor = $factor;

        return $this;
    }

    /**
     * Set the message retention time in milliseconds.
     */
    public function retentionMs(int $ms): static
    {
        return $this->config('retention.ms', (string) $ms);
    }

    /**
     * Set the message retention in bytes.
     */
    public function retentionBytes(int $bytes): static
    {
        return $this->config('retention.bytes', (string) $bytes);
    }

    /**
     * Set the cleanup policy (delete|compact|delete,compact).
     */
    public function cleanupPolicy(string $policy): static
    {
        $allowed = ['delete', 'compact', 'delete,compact'];
        if (! in_array($policy, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cleanup policy must be one of: " . implode(', ', $allowed)
            );
        }

        return $this->config('cleanup.policy', $policy);
    }

    /**
     * Set the minimum number of in-sync replicas.
     */
    public function minInsyncReplicas(int $count): static
    {
        return $this->config('min.insync.replicas', (string) $count);
    }

    /**
     * Set the maximum message size in bytes.
     */
    public function maxMessageBytes(int $bytes): static
    {
        return $this->config('max.message.bytes', (string) $bytes);
    }

    /**
     * Set the compression type (none|gzip|snappy|lz4|zstd|producer).
     */
    public function compressionType(string $type): static
    {
        $allowed = ['none', 'gzip', 'snappy', 'lz4', 'zstd', 'producer'];
        if (! in_array($type, $allowed, true)) {
            throw new InvalidArgumentException(
                "Compression type must be one of: " . implode(', ', $allowed)
            );
        }

        return $this->config('compression.type', $type);
    }

    /**
     * Set the segment size in bytes.
     */
    public function segmentBytes(int $bytes): static
    {
        return $this->config('segment.bytes', (string) $bytes);
    }

    /**
     * Set a raw Kafka topic configuration key/value pair.
     */
    public function config(string $key, string $value): static
    {
        $this->configs[$key] = $value;

        return $this;
    }

    /**
     * Set multiple raw config values at once.
     *
     * @param array<string, string> $configs
     */
    public function configs(array $configs): static
    {
        foreach ($configs as $key => $value) {
            $this->config($key, $value);
        }

        return $this;
    }

    // ------------------------------------------------------------------
    // Getters
    // ------------------------------------------------------------------

    public function getName(): string
    {
        return $this->name;
    }

    public function getPartitions(): int
    {
        return $this->partitions;
    }

    public function getReplicationFactor(): int
    {
        return $this->replicationFactor;
    }

    /**
     * @return array<string, string>
     */
    public function getConfigs(): array
    {
        return $this->configs;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'              => $this->name,
            'partitions'        => $this->partitions,
            'replication_factor' => $this->replicationFactor,
            'configs'           => $this->configs,
        ];
    }
}
