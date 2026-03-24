# Laravel Kafka Migration

[![Tests](https://github.com/nassirian/laravel-kafka-migration/actions/workflows/tests.yml/badge.svg)](https://github.com/nassirian/laravel-kafka-migration/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/nassirian/laravel-kafka-migration.svg)](https://packagist.org/packages/nassirian/laravel-kafka-migration)
[![PHP Version](https://img.shields.io/packagist/php-v/nassirian/laravel-kafka-migration.svg)](https://packagist.org/packages/nassirian/laravel-kafka-migration)
[![License](https://img.shields.io/packagist/l/nassirian/laravel-kafka-migration.svg)](https://packagist.org/packages/nassirian/laravel-kafka-migration)

Manage Kafka topics like Laravel migrations — create, track, and rollback Kafka topics using familiar artisan commands.

## Requirements

- PHP 8.1+
- Laravel 9, 10, 11, or 12

## Installation

```bash
composer require nassirian/laravel-kafka-migration
```

Publish the config:

```bash
php artisan vendor:publish --tag=kafka-migration-config
```

## Configuration

Set your Kafka connection in `.env`:

```env
KAFKA_MIGRATION_DRIVER=longlang   # rdkafka | longlang | http | mock
KAFKA_BROKERS=localhost:9092
```

### Available Drivers

| Driver | Description | Requires |
|---|---|---|
| `longlang` | Pure PHP Kafka client (recommended) | `composer require longlang/phpkafka` |
| `rdkafka` | High-performance via PHP extension | `pecl install rdkafka` |
| `jobcloud` | Fluent rdkafka wrapper by Jobcloud | `composer require jobcloud/php-kafka-lib` + `ext-rdkafka` |
| `http` | Confluent REST Proxy API | ext-curl, REST Proxy running |
| `mock` | In-memory (for testing/development) | Nothing |

## Usage

### Create a topic migration

```bash
php artisan make:kafka-topic orders
php artisan make:kafka-topic user-events
php artisan make:kafka-topic payment.processed
```

This creates a timestamped file in `database/kafka-migrations/`:

```php
// database/kafka-migrations/2024_01_15_123456_create_orders_topic.php

use Nassirian\LaravelKafkaMigration\Migration\KafkaMigration;

return new class extends KafkaMigration
{
    public function up(): void
    {
        $this->createTopic(
            $this->topic('orders')
                ->partitions(3)
                ->replicationFactor(1)
                ->retentionMs(604_800_000)   // 7 days
                ->cleanupPolicy('delete')
                ->compressionType('snappy')
        );
    }

    public function down(): void
    {
        $this->deleteTopic('orders');
    }
};
```

### Topic builder options

```php
$this->topic('my-topic')
    ->partitions(6)                     // number of partitions
    ->replicationFactor(3)              // replication factor
    ->retentionMs(604_800_000)          // retention in milliseconds
    ->retentionBytes(1_073_741_824)     // retention in bytes
    ->cleanupPolicy('delete')           // delete | compact | delete,compact
    ->compressionType('snappy')         // none | gzip | snappy | lz4 | zstd | producer
    ->minInsyncReplicas(2)              // min ISR
    ->maxMessageBytes(1_048_576)        // max message size
    ->segmentBytes(1_073_741_824)       // segment size
    ->config('custom.key', 'value');    // any raw Kafka config
```

### Run migrations

```bash
php artisan kafka:migrate
```

### Check status

```bash
php artisan kafka:migrate:status
```

### Rollback last batch

```bash
php artisan kafka:migrate:rollback
php artisan kafka:migrate:rollback --step=2   # rollback 2 migrations
```

### Reset all migrations

```bash
php artisan kafka:migrate:reset
```

### Pretend mode (dry run)

```bash
php artisan kafka:migrate --pretend
php artisan kafka:migrate:rollback --pretend
```

## Using the Facade

```php
use Nassirian\LaravelKafkaMigration\Facades\KafkaManager;

KafkaManager::topicExists('orders');       // bool
KafkaManager::listTopics();                // string[]
KafkaManager::getTopicMetadata('orders');  // array

// Switch drivers at runtime
KafkaManager::driver('rdkafka')->listTopics();
```

## Jobcloud Driver Details

The `jobcloud` driver uses [`jobcloud/php-kafka-lib`](https://github.com/jobcloud/php-kafka-lib) as its configuration layer. That library provides a clean, opinionated fluent builder around `ext-rdkafka` — it handles broker normalisation, SASL, and SSL setup — and the package then hands off the resulting `KafkaConfiguration` (which extends `RdKafka\Conf`) directly to `RdKafka\AdminClient` for all topic operations.

```env
KAFKA_MIGRATION_DRIVER=jobcloud
KAFKA_BROKERS=broker1:9092,broker2:9092

# Optional SASL
KAFKA_SECURITY_PROTOCOL=SASL_SSL
KAFKA_SASL_MECHANISMS=PLAIN
KAFKA_SASL_USERNAME=your-user
KAFKA_SASL_PASSWORD=your-pass

# Optional SSL
KAFKA_SSL_CA=/path/to/ca.pem
KAFKA_SSL_CERT=/path/to/cert.pem
KAFKA_SSL_KEY=/path/to/key.pem
```

Extra rdkafka settings can be passed via `extra_config` in `config/kafka-migration.php`:

```php
'jobcloud' => [
    // ...
    'extra_config' => [
        'log_level'               => '6',
        'fetch.message.max.bytes' => '1048576',
    ],
],
```

Integration tests that require a live broker are tagged with `@group integration` and can be run separately:

```bash
composer test-integration
```

## Registering a Custom Driver

```php
// In a service provider
use Nassirian\LaravelKafkaMigration\KafkaManager;

$this->app->make(KafkaManager::class)->extend('my-driver', function ($app) {
    return new MyCustomDriver($app['config']['kafka-migration.drivers.my-driver']);
});
```

## Testing

Use the `mock` driver in your tests so no real Kafka connection is needed:

```php
// config/kafka-migration.php  or  .env.testing
KAFKA_MIGRATION_DRIVER=mock
```

Run the test suite:

```bash
composer test
```

or

```bash
./vendor/bin/phpunit
```

## License

MIT
