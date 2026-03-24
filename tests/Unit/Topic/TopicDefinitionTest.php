<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Unit\Topic;

use InvalidArgumentException;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

class TopicDefinitionTest extends TestCase
{
    public function test_it_creates_topic_with_name(): void
    {
        $topic = new TopicDefinition('my-topic');

        $this->assertSame('my-topic', $topic->getName());
    }

    public function test_it_has_default_partitions_of_one(): void
    {
        $topic = new TopicDefinition('my-topic');

        $this->assertSame(1, $topic->getPartitions());
    }

    public function test_it_has_default_replication_factor_of_one(): void
    {
        $topic = new TopicDefinition('my-topic');

        $this->assertSame(1, $topic->getReplicationFactor());
    }

    public function test_it_has_empty_configs_by_default(): void
    {
        $topic = new TopicDefinition('my-topic');

        $this->assertSame([], $topic->getConfigs());
    }

    public function test_it_throws_on_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Topic name cannot be empty');

        new TopicDefinition('');
    }

    public function test_it_throws_on_whitespace_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopicDefinition('   ');
    }

    public function test_it_sets_partitions_fluently(): void
    {
        $topic = (new TopicDefinition('t'))->partitions(5);

        $this->assertSame(5, $topic->getPartitions());
    }

    public function test_it_throws_on_zero_partitions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TopicDefinition('t'))->partitions(0);
    }

    public function test_it_throws_on_negative_partitions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TopicDefinition('t'))->partitions(-1);
    }

    public function test_it_sets_replication_factor_fluently(): void
    {
        $topic = (new TopicDefinition('t'))->replicationFactor(3);

        $this->assertSame(3, $topic->getReplicationFactor());
    }

    public function test_it_throws_on_zero_replication_factor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TopicDefinition('t'))->replicationFactor(0);
    }

    public function test_it_sets_retention_ms(): void
    {
        $topic = (new TopicDefinition('t'))->retentionMs(604_800_000);

        $this->assertSame('604800000', $topic->getConfigs()['retention.ms']);
    }

    public function test_it_sets_retention_bytes(): void
    {
        $topic = (new TopicDefinition('t'))->retentionBytes(1_073_741_824);

        $this->assertSame('1073741824', $topic->getConfigs()['retention.bytes']);
    }

    public function test_it_sets_cleanup_policy_delete(): void
    {
        $topic = (new TopicDefinition('t'))->cleanupPolicy('delete');

        $this->assertSame('delete', $topic->getConfigs()['cleanup.policy']);
    }

    public function test_it_sets_cleanup_policy_compact(): void
    {
        $topic = (new TopicDefinition('t'))->cleanupPolicy('compact');

        $this->assertSame('compact', $topic->getConfigs()['cleanup.policy']);
    }

    public function test_it_sets_cleanup_policy_delete_compact(): void
    {
        $topic = (new TopicDefinition('t'))->cleanupPolicy('delete,compact');

        $this->assertSame('delete,compact', $topic->getConfigs()['cleanup.policy']);
    }

    public function test_it_throws_on_invalid_cleanup_policy(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TopicDefinition('t'))->cleanupPolicy('invalid');
    }

    public function test_it_sets_min_insync_replicas(): void
    {
        $topic = (new TopicDefinition('t'))->minInsyncReplicas(2);

        $this->assertSame('2', $topic->getConfigs()['min.insync.replicas']);
    }

    public function test_it_sets_max_message_bytes(): void
    {
        $topic = (new TopicDefinition('t'))->maxMessageBytes(1_048_576);

        $this->assertSame('1048576', $topic->getConfigs()['max.message.bytes']);
    }

    public function test_it_sets_valid_compression_types(): void
    {
        $allowed = ['none', 'gzip', 'snappy', 'lz4', 'zstd', 'producer'];

        foreach ($allowed as $type) {
            $topic = (new TopicDefinition('t'))->compressionType($type);
            $this->assertSame($type, $topic->getConfigs()['compression.type']);
        }
    }

    public function test_it_throws_on_invalid_compression_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TopicDefinition('t'))->compressionType('brotli');
    }

    public function test_it_sets_segment_bytes(): void
    {
        $topic = (new TopicDefinition('t'))->segmentBytes(1_073_741_824);

        $this->assertSame('1073741824', $topic->getConfigs()['segment.bytes']);
    }

    public function test_it_sets_raw_config(): void
    {
        $topic = (new TopicDefinition('t'))->config('custom.key', 'custom-value');

        $this->assertSame('custom-value', $topic->getConfigs()['custom.key']);
    }

    public function test_it_sets_multiple_configs_at_once(): void
    {
        $topic = (new TopicDefinition('t'))->configs([
            'retention.ms'  => '86400000',
            'cleanup.policy' => 'delete',
        ]);

        $this->assertSame('86400000', $topic->getConfigs()['retention.ms']);
        $this->assertSame('delete', $topic->getConfigs()['cleanup.policy']);
    }

    public function test_it_is_chainable(): void
    {
        $topic = (new TopicDefinition('orders'))
            ->partitions(6)
            ->replicationFactor(3)
            ->retentionMs(86_400_000)
            ->cleanupPolicy('delete')
            ->compressionType('gzip');

        $this->assertSame('orders', $topic->getName());
        $this->assertSame(6, $topic->getPartitions());
        $this->assertSame(3, $topic->getReplicationFactor());
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $topic = (new TopicDefinition('payments'))
            ->partitions(4)
            ->replicationFactor(2)
            ->retentionMs(3_600_000);

        $array = $topic->toArray();

        $this->assertSame('payments', $array['name']);
        $this->assertSame(4, $array['partitions']);
        $this->assertSame(2, $array['replication_factor']);
        $this->assertSame(['retention.ms' => '3600000'], $array['configs']);
    }
}
