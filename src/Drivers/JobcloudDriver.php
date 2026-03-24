<?php

namespace Nassirian\LaravelKafkaMigration\Drivers;

use Jobcloud\Kafka\Conf\KafkaConfiguration;
use Jobcloud\Kafka\Producer\KafkaProducerBuilder;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaConnectionException;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaTopicException;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;
use RdKafka\AdminClient;
use RdKafka\NewTopic;

/**
 * Driver that leverages jobcloud/php-kafka-lib for configuration
 * and ext-rdkafka's AdminClient for topic management operations.
 *
 * jobcloud/php-kafka-lib is a fluent, opinionated wrapper around ext-rdkafka.
 * It normalises broker lists, SASL/SSL settings, and rdkafka configuration
 * through its KafkaConfiguration class (which extends RdKafka\Conf), making
 * it straightforward to hand off to RdKafka\AdminClient for admin operations.
 *
 * Requires:
 *  - ext-rdkafka ^4.0|^5.0|^6.0
 *  - composer require jobcloud/php-kafka-lib
 */
class JobcloudDriver extends AbstractKafkaDriver
{
    protected ?AdminClient $adminClient = null;

    /**
     * @throws KafkaConnectionException
     */
    protected function connect(): void
    {
        if (! extension_loaded('rdkafka')) {
            throw new KafkaConnectionException(
                'The rdkafka PHP extension is required for the jobcloud driver. ' .
                'Install it with: pecl install rdkafka'
            );
        }

        if (! class_exists(KafkaProducerBuilder::class)) {
            throw new KafkaConnectionException(
                'jobcloud/php-kafka-lib is not installed. ' .
                'Run: composer require jobcloud/php-kafka-lib'
            );
        }

        try {
            $conf = $this->buildKafkaConfiguration();

            // RdKafka\AdminClient accepts a RdKafka\Conf (KafkaConfiguration extends it)
            $this->adminClient = AdminClient::fromConf($conf);
        } catch (KafkaConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new KafkaConnectionException(
                'Failed to initialise Kafka admin client via jobcloud/php-kafka-lib: ' .
                $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Build a KafkaConfiguration using jobcloud's builder pattern.
     *
     * KafkaConfiguration extends RdKafka\Conf and can therefore be passed
     * directly to RdKafka\AdminClient::fromConf().
     */
    protected function buildKafkaConfiguration(): KafkaConfiguration
    {
        $brokers       = $this->parseBrokers($this->config['brokers'] ?? 'localhost:9092');
        $extraConfig   = $this->buildRdKafkaConfig();

        // KafkaConfiguration expects (string[] $brokers, TopicSubscription[] $subscriptions, array $config, string $type)
        // For admin purposes we pass an empty subscriptions list.
        return new KafkaConfiguration(
            $brokers,
            [],          // No topic subscriptions needed for admin-only usage
            $extraConfig,
            ''           // type — empty string for admin use
        );
    }

    /**
     * Translate our config array into the flat rdkafka config map that
     * KafkaConfiguration's constructor expects.
     *
     * @return array<string, string>
     */
    protected function buildRdKafkaConfig(): array
    {
        $map = [];

        // Socket / connection timeouts
        if (isset($this->config['socket_timeout_ms'])) {
            $map['socket.timeout.ms'] = (string) $this->config['socket_timeout_ms'];
        }

        if (isset($this->config['metadata_request_timeout_ms'])) {
            $map['metadata.request.timeout.ms'] = (string) $this->config['metadata_request_timeout_ms'];
        }

        // Security protocol
        $protocol = $this->config['security_protocol'] ?? 'PLAINTEXT';
        if ($protocol !== 'PLAINTEXT') {
            $map['security.protocol'] = $protocol;
        }

        // SASL
        if (! empty($this->config['sasl_mechanisms'])) {
            $map['sasl.mechanisms'] = $this->config['sasl_mechanisms'];
            $map['sasl.username']   = $this->config['sasl_username'] ?? '';
            $map['sasl.password']   = $this->config['sasl_password'] ?? '';
        }

        // SSL
        if (! empty($this->config['ssl_ca_location'])) {
            $map['ssl.ca.location'] = $this->config['ssl_ca_location'];
        }
        if (! empty($this->config['ssl_certificate_location'])) {
            $map['ssl.certificate.location'] = $this->config['ssl_certificate_location'];
        }
        if (! empty($this->config['ssl_key_location'])) {
            $map['ssl.key.location'] = $this->config['ssl_key_location'];
        }

        // Merge any arbitrary extra config the user put in 'extra_config'
        foreach ((array) ($this->config['extra_config'] ?? []) as $key => $value) {
            $map[(string) $key] = (string) $value;
        }

        return $map;
    }

    // ------------------------------------------------------------------
    // KafkaDriverInterface implementation
    // ------------------------------------------------------------------

    /**
     * @throws KafkaTopicException
     */
    public function createTopic(TopicDefinition $topic): void
    {
        $this->ensureConnected();

        $newTopic = new NewTopic(
            $topic->getName(),
            $topic->getPartitions(),
            $topic->getReplicationFactor()
        );

        foreach ($topic->getConfigs() as $key => $value) {
            $newTopic->setConfig($key, $value);
        }

        try {
            $result = $this->adminClient->createTopics(
                [$newTopic],
                $this->buildCreateOptions()
            );

            foreach ($result as $topicResult) {
                if (! in_array(
                    $topicResult->error,
                    [RD_KAFKA_RESP_ERR_NO_ERROR, RD_KAFKA_RESP_ERR_TOPIC_ALREADY_EXISTS],
                    true
                )) {
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
            $result = $this->adminClient->deleteTopics(
                [new \RdKafka\DeleteTopic($topicName)],
                $this->buildDeleteOptions()
            );

            foreach ($result as $topicResult) {
                if (! in_array(
                    $topicResult->error,
                    [RD_KAFKA_RESP_ERR_NO_ERROR, RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART],
                    true
                )) {
                    throw new KafkaTopicException(
                        "Failed to delete topic '{$topicName}': " .
                        rd_kafka_err2str($topicResult->error)
                    );
                }
            }
        } catch (KafkaTopicException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new KafkaTopicException(
                "Failed to delete topic '{$topicName}': " . $e->getMessage(),
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
     * @throws KafkaTopicException
     */
    public function listTopics(): array
    {
        $this->ensureConnected();

        try {
            $metadata = $this->adminClient->getMetadata(
                true,
                null,
                $this->config['socket_timeout_ms'] ?? 5000
            );

            $topics = [];
            foreach ($metadata->getTopics() as $topicMeta) {
                $name = $topicMeta->getTopic();
                // Exclude internal Kafka topics
                if (! str_starts_with($name, '__')) {
                    $topics[] = $name;
                }
            }

            return $topics;
        } catch (\Throwable $e) {
            throw new KafkaTopicException(
                'Failed to list topics: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @return array<string, mixed>
     * @throws KafkaTopicException
     */
    public function getTopicMetadata(string $topicName): array
    {
        $this->ensureConnected();

        try {
            // Use a temporary producer topic handle to fetch per-topic metadata
            $conf     = $this->buildKafkaConfiguration();
            $producer = new \RdKafka\Producer($conf);
            $topic    = $producer->newTopic($topicName);
            $metadata = $producer->getMetadata(
                false,
                $topic,
                $this->config['socket_timeout_ms'] ?? 5000
            );

            $result = [];
            foreach ($metadata->getTopics() as $topicMeta) {
                if ($topicMeta->getTopic() === $topicName) {
                    $result = [
                        'name'       => $topicMeta->getTopic(),
                        'partitions' => count($topicMeta->getPartitions()),
                        'error'      => $topicMeta->getErr(),
                    ];
                    break;
                }
            }

            return $result;
        } catch (\Throwable $e) {
            throw new KafkaTopicException(
                "Failed to get metadata for topic '{$topicName}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @param array<string, mixed> $configs
     * @throws KafkaTopicException
     */
    public function alterTopicConfig(string $topicName, array $configs): void
    {
        $this->ensureConnected();

        try {
            $resource = new \RdKafka\ConfigResource(
                \RdKafka\ConfigResource::RESTYPE_TOPIC,
                $topicName
            );

            foreach ($configs as $key => $value) {
                $resource->setConfig((string) $key, (string) $value);
            }

            $options = new \RdKafka\AdminOptions($this->adminClient);
            $options->setOperationTimeout($this->config['socket_timeout_ms'] ?? 5000);

            $this->adminClient->alterConfigs([$resource], $options);
        } catch (\Throwable $e) {
            throw new KafkaTopicException(
                "Failed to alter config for topic '{$topicName}': " . $e->getMessage(),
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

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build AdminOptions for createTopics operations.
     */
    protected function buildCreateOptions(): \RdKafka\AdminOptions
    {
        $options = new \RdKafka\AdminOptions($this->adminClient);
        $options->setRequestTimeout($this->config['socket_timeout_ms'] ?? 5000);
        $options->setOperationTimeout($this->config['socket_timeout_ms'] ?? 5000);

        return $options;
    }

    /**
     * Build AdminOptions for deleteTopics operations.
     */
    protected function buildDeleteOptions(): \RdKafka\AdminOptions
    {
        $options = new \RdKafka\AdminOptions($this->adminClient);
        $options->setRequestTimeout($this->config['socket_timeout_ms'] ?? 5000);
        $options->setOperationTimeout($this->config['socket_timeout_ms'] ?? 5000);

        return $options;
    }

    /**
     * Expose the underlying AdminClient (useful for advanced use-cases / testing).
     */
    public function getAdminClient(): ?AdminClient
    {
        return $this->adminClient;
    }
}
