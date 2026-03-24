<?php

namespace Nassirian\LaravelKafkaMigration;

use Illuminate\Contracts\Container\Container;
use Nassirian\LaravelKafkaMigration\Contracts\KafkaDriverInterface;
use Nassirian\LaravelKafkaMigration\Drivers\HttpDriver;
use Nassirian\LaravelKafkaMigration\Drivers\JobcloudDriver;
use Nassirian\LaravelKafkaMigration\Drivers\LongLangDriver;
use Nassirian\LaravelKafkaMigration\Drivers\MockDriver;
use Nassirian\LaravelKafkaMigration\Drivers\RdKafkaDriver;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaConnectionException;

class KafkaManager
{
    protected Container $app;

    /** @var array<string, KafkaDriverInterface> */
    protected array $drivers = [];

    /** @var array<string, callable> */
    protected array $customDrivers = [];

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Get the configured driver instance (or the named driver).
     */
    public function driver(?string $name = null): KafkaDriverInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    protected function getDefaultDriver(): string
    {
        return (string) $this->app['config']->get('kafka-migration.driver', 'longlang');
    }

    protected function createDriver(string $name): KafkaDriverInterface
    {
        // Check for custom driver factory first
        if (isset($this->customDrivers[$name])) {
            return ($this->customDrivers[$name])($this->app);
        }

        $config = $this->getDriverConfig($name);

        return match ($name) {
            'rdkafka'  => new RdKafkaDriver($config),
            'longlang' => new LongLangDriver($config),
            'http'     => new HttpDriver($config),
            'mock'     => new MockDriver($config),
            'jobcloud' => new JobcloudDriver($config),
            default    => throw new KafkaConnectionException("Unsupported Kafka driver: [{$name}]"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDriverConfig(string $name): array
    {
        return (array) $this->app['config']->get("kafka-migration.drivers.{$name}", []);
    }

    /**
     * Register a custom driver factory.
     *
     * @param callable(Container): KafkaDriverInterface $callback
     */
    public function extend(string $name, callable $callback): void
    {
        $this->customDrivers[$name] = $callback;
    }

    /**
     * Forget a cached driver instance (forces reconnection on next use).
     */
    public function purge(?string $name = null): void
    {
        $name = $name ?? $this->getDefaultDriver();

        if (isset($this->drivers[$name])) {
            $this->drivers[$name]->disconnect();
            unset($this->drivers[$name]);
        }
    }

    /**
     * Dynamically forward calls to the default driver.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->driver()->{$method}(...$arguments);
    }
}
