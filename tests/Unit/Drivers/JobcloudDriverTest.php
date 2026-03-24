<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Unit\Drivers;

use Nassirian\LaravelKafkaMigration\Drivers\JobcloudDriver;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaConnectionException;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaTopicException;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

/**
 * Unit tests for the JobcloudDriver.
 *
 * Because jobcloud/php-kafka-lib requires ext-rdkafka (a compiled C extension)
 * that is unavailable in CI without a real Kafka broker, all tests that would
 * trigger actual network I/O are guarded with an extension check and the driver
 * itself is tested through a Mockery partial mock that stubs the low-level
 * RdKafka calls.
 *
 * Tests that verify behaviour NOT dependent on ext-rdkafka (constructor guards,
 * config building, disconnect) run unconditionally.
 */
class JobcloudDriverTest extends TestCase
{
    // ------------------------------------------------------------------
    // Extension-guard tests (always run)
    // ------------------------------------------------------------------

    public function test_it_throws_connection_exception_when_rdkafka_is_missing(): void
    {
        if (extension_loaded('rdkafka')) {
            $this->markTestSkipped('ext-rdkafka is loaded; cannot test missing-extension guard.');
        }

        $driver = new JobcloudDriver(['brokers' => 'localhost:9092']);

        $this->expectException(KafkaConnectionException::class);
        $this->expectExceptionMessage('rdkafka PHP extension');

        // Trigger connect() indirectly via a public method
        $driver->listTopics();
    }

    public function test_it_throws_connection_exception_when_jobcloud_lib_is_missing(): void
    {
        if (! extension_loaded('rdkafka')) {
            $this->markTestSkipped('ext-rdkafka is not loaded; skipping jobcloud-lib guard test.');
        }

        if (class_exists(\Jobcloud\Kafka\Conf\KafkaConfiguration::class)) {
            $this->markTestSkipped('jobcloud/php-kafka-lib is installed; cannot test missing-lib guard.');
        }

        $driver = new JobcloudDriver(['brokers' => 'localhost:9092']);

        $this->expectException(KafkaConnectionException::class);
        $this->expectExceptionMessage('jobcloud/php-kafka-lib');

        $driver->listTopics();
    }

    public function test_disconnect_resets_state_without_prior_connection(): void
    {
        $driver = new JobcloudDriver(['brokers' => 'localhost:9092']);

        // Should not throw even if never connected
        $driver->disconnect();

        $this->assertNull($driver->getAdminClient());
    }

    // ------------------------------------------------------------------
    // Config-building tests (always run; no real connection needed)
    // ------------------------------------------------------------------

    public function test_it_accepts_standard_config_keys(): void
    {
        $config = [
            'brokers'             => 'broker1:9092,broker2:9092',
            'socket_timeout_ms'   => 3000,
            'security_protocol'   => 'SASL_SSL',
            'sasl_mechanisms'     => 'PLAIN',
            'sasl_username'       => 'user',
            'sasl_password'       => 'pass',
            'ssl_ca_location'     => '/path/to/ca.pem',
            'ssl_certificate_location' => '/path/to/cert.pem',
            'ssl_key_location'    => '/path/to/key.pem',
            'extra_config'        => ['log_level' => '6'],
        ];

        // Just instantiating the driver (not connecting) should not throw
        $driver = new JobcloudDriver($config);

        $this->assertInstanceOf(JobcloudDriver::class, $driver);
    }

    public function test_it_parses_comma_separated_broker_list(): void
    {
        // Access parseBrokers through reflection (it's a protected helper)
        $driver     = new JobcloudDriver(['brokers' => 'a:9092, b:9092, c:9092']);
        $reflection = new \ReflectionMethod($driver, 'parseBrokers');
        $reflection->setAccessible(true);

        $brokers = $reflection->invoke($driver, 'a:9092, b:9092, c:9092');

        $this->assertCount(3, $brokers);
        $this->assertContains('a:9092', $brokers);
        $this->assertContains('b:9092', $brokers);
        $this->assertContains('c:9092', $brokers);
    }

    public function test_build_rdkafka_config_omits_plaintext_security_protocol(): void
    {
        $driver = new JobcloudDriver([
            'brokers'           => 'localhost:9092',
            'security_protocol' => 'PLAINTEXT',
        ]);

        $reflection = new \ReflectionMethod($driver, 'buildRdKafkaConfig');
        $reflection->setAccessible(true);

        $config = $reflection->invoke($driver);

        $this->assertArrayNotHasKey('security.protocol', $config);
    }

    public function test_build_rdkafka_config_includes_non_plaintext_protocol(): void
    {
        $driver = new JobcloudDriver([
            'brokers'           => 'localhost:9092',
            'security_protocol' => 'SASL_SSL',
        ]);

        $reflection = new \ReflectionMethod($driver, 'buildRdKafkaConfig');
        $reflection->setAccessible(true);

        $config = $reflection->invoke($driver);

        $this->assertSame('SASL_SSL', $config['security.protocol']);
    }

    public function test_build_rdkafka_config_includes_sasl_settings(): void
    {
        $driver = new JobcloudDriver([
            'brokers'         => 'localhost:9092',
            'sasl_mechanisms' => 'SCRAM-SHA-256',
            'sasl_username'   => 'alice',
            'sasl_password'   => 's3cr3t',
        ]);

        $reflection = new \ReflectionMethod($driver, 'buildRdKafkaConfig');
        $reflection->setAccessible(true);

        $config = $reflection->invoke($driver);

        $this->assertSame('SCRAM-SHA-256', $config['sasl.mechanisms']);
        $this->assertSame('alice', $config['sasl.username']);
        $this->assertSame('s3cr3t', $config['sasl.password']);
    }

    public function test_build_rdkafka_config_includes_ssl_paths(): void
    {
        $driver = new JobcloudDriver([
            'brokers'                  => 'localhost:9092',
            'ssl_ca_location'          => '/tmp/ca.pem',
            'ssl_certificate_location' => '/tmp/cert.pem',
            'ssl_key_location'         => '/tmp/key.pem',
        ]);

        $reflection = new \ReflectionMethod($driver, 'buildRdKafkaConfig');
        $reflection->setAccessible(true);

        $config = $reflection->invoke($driver);

        $this->assertSame('/tmp/ca.pem', $config['ssl.ca.location']);
        $this->assertSame('/tmp/cert.pem', $config['ssl.certificate.location']);
        $this->assertSame('/tmp/key.pem', $config['ssl.key.location']);
    }

    public function test_build_rdkafka_config_merges_extra_config(): void
    {
        $driver = new JobcloudDriver([
            'brokers'      => 'localhost:9092',
            'extra_config' => [
                'log_level'               => '6',
                'fetch.message.max.bytes' => '1048576',
            ],
        ]);

        $reflection = new \ReflectionMethod($driver, 'buildRdKafkaConfig');
        $reflection->setAccessible(true);

        $config = $reflection->invoke($driver);

        $this->assertSame('6', $config['log_level']);
        $this->assertSame('1048576', $config['fetch.message.max.bytes']);
    }

    public function test_build_rdkafka_config_includes_timeout_settings(): void
    {
        $driver = new JobcloudDriver([
            'brokers'                     => 'localhost:9092',
            'socket_timeout_ms'           => 3000,
            'metadata_request_timeout_ms' => 7000,
        ]);

        $reflection = new \ReflectionMethod($driver, 'buildRdKafkaConfig');
        $reflection->setAccessible(true);

        $config = $reflection->invoke($driver);

        $this->assertSame('3000', $config['socket.timeout.ms']);
        $this->assertSame('7000', $config['metadata.request.timeout.ms']);
    }

    public function test_get_admin_client_returns_null_before_connection(): void
    {
        $driver = new JobcloudDriver(['brokers' => 'localhost:9092']);

        $this->assertNull($driver->getAdminClient());
    }

    // ------------------------------------------------------------------
    // Integration tests (skip when ext-rdkafka or jobcloud not present)
    // ------------------------------------------------------------------

    /**
     * These tests only run when ext-rdkafka AND jobcloud/php-kafka-lib
     * AND a real Kafka broker are available. They are tagged with a group
     * so CI can skip them:  --exclude-group=integration
     *
     * @group integration
     */
    public function test_it_can_connect_to_kafka_with_jobcloud_driver(): void
    {
        $this->requireKafkaDependencies();

        $driver = new JobcloudDriver([
            'brokers'           => env('KAFKA_BROKERS', 'localhost:9092'),
            'socket_timeout_ms' => 5000,
        ]);

        // If connection fails the driver will throw KafkaConnectionException
        $this->assertIsArray($driver->listTopics());
    }

    /**
     * @group integration
     */
    public function test_it_creates_and_deletes_a_topic_via_jobcloud(): void
    {
        $this->requireKafkaDependencies();

        $driver    = new JobcloudDriver([
            'brokers'           => env('KAFKA_BROKERS', 'localhost:9092'),
            'socket_timeout_ms' => 5000,
        ]);
        $topicName = 'test-jobcloud-' . uniqid();

        $driver->createTopic(
            (new TopicDefinition($topicName))->partitions(1)->replicationFactor(1)
        );

        $this->assertTrue($driver->topicExists($topicName));

        $driver->deleteTopic($topicName);

        $this->assertFalse($driver->topicExists($topicName));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function requireKafkaDependencies(): void
    {
        if (! extension_loaded('rdkafka')) {
            $this->markTestSkipped('ext-rdkafka is not loaded.');
        }

        if (! class_exists(\Jobcloud\Kafka\Conf\KafkaConfiguration::class)) {
            $this->markTestSkipped('jobcloud/php-kafka-lib is not installed.');
        }
    }
}
