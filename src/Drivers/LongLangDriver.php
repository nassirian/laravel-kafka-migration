<?php

namespace Nassirian\LaravelKafkaMigration\Drivers;

use Nassirian\LaravelKafkaMigration\Exceptions\KafkaConnectionException;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaTopicException;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

/**
 * Driver that uses the longlang/phpkafka pure-PHP client.
 *
 * Requires: composer require longlang/phpkafka
 */
class LongLangDriver extends AbstractKafkaDriver
{
    /** @var \LongLang\PhpKafka\Producer\KafkaProducer|null */
    protected mixed $adminClient = null;

    /**
     * @throws KafkaConnectionException
     */
    protected function connect(): void
    {
        if (! class_exists(\LongLang\PhpKafka\Producer\KafkaProducer::class)) {
            throw new KafkaConnectionException(
                'longlang/phpkafka is not installed. ' .
                'Run: composer require longlang/phpkafka'
            );
        }

        try {
            $brokers = $this->parseBrokers($this->config['brokers'] ?? 'localhost:9092');
            $firstBroker = $brokers[0] ?? 'localhost:9092';
            [$host, $port] = $this->parseBrokerAddress($firstBroker);

            $config = new \LongLang\PhpKafka\Producer\ProducerConfig();
            $config->setConnectTimeout($this->config['timeout'] ?? 5000);
            $config->setBroker($firstBroker);

            if (! empty($this->config['username']) && ! empty($this->config['password'])) {
                $saslConfig = new \LongLang\PhpKafka\Sasl\SaslConfig();
                $saslConfig->setUsername($this->config['username']);
                $saslConfig->setPassword($this->config['password']);
                $saslConfig->setMechanism($this->config['sasl_mechanisms'] ?? 'PLAIN');
                $config->setSasl($saslConfig);
            }

            // Store the config for later admin operations
            $this->adminClient = ['host' => $host, 'port' => (int) $port, 'config' => $config, 'broker' => $firstBroker];
        } catch (\Throwable $e) {
            throw new KafkaConnectionException(
                'Failed to connect to Kafka via longlang: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Parse "host:port" into [$host, $port].
     *
     * @return array{0: string, 1: string}
     */
    protected function parseBrokerAddress(string $broker): array
    {
        $parts = explode(':', $broker);
        $host  = $parts[0] ?? 'localhost';
        $port  = $parts[1] ?? '9092';

        return [$host, $port];
    }

    /**
     * @throws KafkaTopicException
     */
    public function createTopic(TopicDefinition $topic): void
    {
        $this->ensureConnected();

        try {
            $broker = $this->adminClient['broker'];
            $adminClient = new \LongLang\PhpKafka\Admin\KafkaAdminClient(
                new \LongLang\PhpKafka\Admin\AdminClientConfig(['brokers' => $broker])
            );

            $newTopic = new \LongLang\PhpKafka\Admin\CreateTopicsRequest\Topic();
            $newTopic->setName($topic->getName());
            $newTopic->setNumPartitions($topic->getPartitions());
            $newTopic->setReplicationFactor($topic->getReplicationFactor());

            foreach ($topic->getConfigs() as $key => $value) {
                $cfg = new \LongLang\PhpKafka\Admin\CreateTopicsRequest\TopicConfig();
                $cfg->setName($key);
                $cfg->setValue($value);
                $newTopic->addConfig($cfg);
            }

            $adminClient->createTopics([$newTopic]);
            $adminClient->close();
        } catch (\Throwable $e) {
            throw new KafkaTopicException(
                "Failed to create topic '{$topic->getName()}': " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * @throws KafkaTopicException
     */
    public function deleteTopic(string $topicName): void
    {
        $this->ensureConnected();

        try {
            $broker = $this->adminClient['broker'];
            $adminClient = new \LongLang\PhpKafka\Admin\KafkaAdminClient(
                new \LongLang\PhpKafka\Admin\AdminClientConfig(['brokers' => $broker])
            );

            $adminClient->deleteTopics([$topicName]);
            $adminClient->close();
        } catch (\Throwable $e) {
            throw new KafkaTopicException(
                "Failed to delete topic '$topicName': " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public function topicExists(string $topicName): bool
    {
        return in_array($topicName, $this->listTopics(), true);
    }

    /**
     * @return string[]
     */
    public function listTopics(): array
    {
        $this->ensureConnected();

        try {
            $broker = $this->adminClient['broker'];
            $adminClient = new \LongLang\PhpKafka\Admin\KafkaAdminClient(
                new \LongLang\PhpKafka\Admin\AdminClientConfig(['brokers' => $broker])
            );

            $metadata = $adminClient->listTopics();
            $adminClient->close();

            $topics = [];
            foreach ($metadata as $topic) {
                $name = is_string($topic) ? $topic : (method_exists($topic, 'getName') ? $topic->getName() : (string) $topic);
                if (! str_starts_with($name, '__')) {
                    $topics[] = $name;
                }
            }

            return $topics;
        } catch (\Throwable $e) {
            throw new KafkaTopicException('Failed to list topics: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getTopicMetadata(string $topicName): array
    {
        $this->ensureConnected();

        try {
            $broker = $this->adminClient['broker'];
            $adminClient = new \LongLang\PhpKafka\Admin\KafkaAdminClient(
                new \LongLang\PhpKafka\Admin\AdminClientConfig(['brokers' => $broker])
            );

            $metadata = $adminClient->describeTopics([$topicName]);
            $adminClient->close();

            return is_array($metadata) ? ($metadata[$topicName] ?? []) : [];
        } catch (\Throwable $e) {
            throw new KafkaTopicException(
                "Failed to get metadata for topic '$topicName': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @param array<string, mixed> $configs
     */
    public function alterTopicConfig(string $topicName, array $configs): void
    {
        $this->ensureConnected();

        try {
            $broker = $this->adminClient['broker'];
            $adminClient = new \LongLang\PhpKafka\Admin\KafkaAdminClient(
                new \LongLang\PhpKafka\Admin\AdminClientConfig(['brokers' => $broker])
            );

            $configEntries = [];
            foreach ($configs as $key => $value) {
                $entry = new \LongLang\PhpKafka\Admin\AlterConfigsRequest\AlterConfigsResource\AlterableConfig();
                $entry->setName($key);
                $entry->setValue((string) $value);
                $configEntries[] = $entry;
            }

            $adminClient->alterConfigs($topicName, $configEntries);
            $adminClient->close();
        } catch (\Throwable $e) {
            throw new KafkaTopicException(
                "Failed to alter config for topic '$topicName': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function disconnect(): void
    {
        $this->adminClient = null;
        $this->connected   = false;
    }
}
