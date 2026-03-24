<?php

namespace Nassirian\LaravelKafkaMigration\Drivers;

use Nassirian\LaravelKafkaMigration\Contracts\KafkaDriverInterface;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaConnectionException;

abstract class AbstractKafkaDriver implements KafkaDriverInterface
{
    /** @var array<string, mixed> */
    protected array $config;

    protected bool $connected = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Ensure the driver is connected, connecting if necessary.
     */
    protected function ensureConnected(): void
    {
        if (! $this->connected) {
            $this->connect();
            $this->connected = true;
        }
    }

    /**
     * Establish the underlying connection.
     *
     * @throws KafkaConnectionException
     */
    abstract protected function connect(): void;

    /**
     * Parse a comma-separated brokers string into an array.
     *
     * @return string[]
     */
    protected function parseBrokers(string $brokers): array
    {
        return array_filter(array_map('trim', explode(',', $brokers)));
    }
}
