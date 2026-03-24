<?php

namespace Nassirian\LaravelKafkaMigration\Drivers;

use Nassirian\LaravelKafkaMigration\Exceptions\KafkaConnectionException;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaTopicException;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;

/**
 * Driver that uses the ext-rdkafka PHP extension.
 *
 * Requires: pecl install rdkafka
 */
class RdKafkaDriver extends AbstractKafkaDriver
{
    /** @var \RdKafka\Producer|null */
    protected ?Producer $producer = null;

    /** @var \RdKafka\KafkaConsumer|null */
    protected ?KafkaConsumer $consumer = null;

    /**
     * @throws KafkaConnectionException
     */
    protected function connect(): void
    {
        if (! extension_loaded('rdkafka')) {
            throw new KafkaConnectionException(
                'The rdkafka PHP extension is not loaded. ' .
                'Install it with: pecl install rdkafka'
            );
        }

        try {
            $conf = $this->buildConf();
            $this->producer = new Producer($conf);
        } catch (\Throwable $e) {
            throw new KafkaConnectionException(
                'Failed to connect to Kafka via rdkafka: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    protected function buildConf(): Conf
    {
        $conf = new Conf();

        $conf->set('metadata.broker.list', $this->config['metadata_broker_list'] ?? 'localhost:9092');
        $conf->set('socket.timeout.ms', (string) ($this->config['socket_timeout_ms'] ?? 5000));

        if (! empty($this->config['security_protocol']) && $this->config['security_protocol'] !== 'PLAINTEXT') {
            $conf->set('security.protocol', $this->config['security_protocol']);
        }

        if (! empty($this->config['sasl_mechanisms'])) {
            $conf->set('sasl.mechanisms', $this->config['sasl_mechanisms']);
            $conf->set('sasl.username', $this->config['sasl_username'] ?? '');
            $conf->set('sasl.password', $this->config['sasl_password'] ?? '');
        }

        if (! empty($this->config['ssl_ca_location'])) {
            $conf->set('ssl.ca.location', $this->config['ssl_ca_location']);
        }
        if (! empty($this->config['ssl_certificate_location'])) {
            $conf->set('ssl.certificate.location', $this->config['ssl_certificate_location']);
        }
        if (! empty($this->config['ssl_key_location'])) {
            $conf->set('ssl.key.location', $this->config['ssl_key_location']);
        }

        if (isset($this->config['log_level'])) {
            $conf->set('log_level', (string) $this->config['log_level']);
        }

        return $conf;
    }

    /**
     * Create a topic via the NewTopic admin API.
     *
     * @throws KafkaTopicException
     */
    public function createTopic(TopicDefinition $topic): void
    {
        $this->ensureConnected();

        try {
            $adminClient = \RdKafka\AdminClient::fromConf($this->buildConf());

            $newTopic = new \RdKafka\NewTopic(
                $topic->getName(),
                $topic->getPartitions(),
                $topic->getReplicationFactor()
            );

            foreach ($topic->getConfigs() as $key => $value) {
                $newTopic->setConfig($key, $value);
            }

            $result = $adminClient->createTopics([$newTopic]);

            foreach ($result as $topicResult) {
                if ($topicResult->error !== RD_KAFKA_RESP_ERR_NO_ERROR &&
                    $topicResult->error !== RD_KAFKA_RESP_ERR_TOPIC_ALREADY_EXISTS) {
                    throw new KafkaTopicException(
                        "Failed to create topic '{$topic->getName()}': " .
                        rd_kafka_err2str($topicResult->error)
                    );
                }
            }
        } catch (KafkaTopicException $e) {
            throw $e;
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
            $adminClient = \RdKafka\AdminClient::fromConf($this->buildConf());

            $result = $adminClient->deleteTopics([new \RdKafka\DeleteTopic($topicName)]);

            foreach ($result as $topicResult) {
                if ($topicResult->error !== RD_KAFKA_RESP_ERR_NO_ERROR &&
                    $topicResult->error !== RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART) {
                    throw new KafkaTopicException(
                        "Failed to delete topic '$topicName': " .
                        rd_kafka_err2str($topicResult->error)
                    );
                }
            }
        } catch (KafkaTopicException $e) {
            throw $e;
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
            $metadata = $this->producer->getMetadata(true, null, $this->config['socket_timeout_ms'] ?? 5000);
            $topics = [];
            foreach ($metadata->getTopics() as $topic) {
                $name = $topic->getTopic();
                // Filter internal topics
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
            $topic    = $this->producer->newTopic($topicName);
            $metadata = $this->producer->getMetadata(false, $topic, $this->config['socket_timeout_ms'] ?? 5000);

            $result = [];
            foreach ($metadata->getTopics() as $topicMeta) {
                if ($topicMeta->getTopic() === $topicName) {
                    $result['name']       = $topicMeta->getTopic();
                    $result['partitions'] = count($topicMeta->getPartitions());
                    break;
                }
            }

            return $result;
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
            $adminClient = \RdKafka\AdminClient::fromConf($this->buildConf());

            $resource = new \RdKafka\ConfigResource(\RdKafka\ConfigResource::RESTYPE_TOPIC, $topicName);
            foreach ($configs as $key => $value) {
                $resource->setConfig($key, (string) $value);
            }

            $adminClient->alterConfigs([$resource]);
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
        $this->producer  = null;
        $this->consumer  = null;
        $this->connected = false;
    }
}
