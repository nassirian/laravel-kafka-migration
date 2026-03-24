<?php

namespace Nassirian\LaravelKafkaMigration\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Available drivers: "rdkafka" | "longlang" | "http" | "mock" | "jobcloud"
 *
 * @method static \Nassirian\LaravelKafkaMigration\Contracts\KafkaDriverInterface driver(?string $name = null)
 * @method static void createTopic(\Nassirian\LaravelKafkaMigration\Topic\TopicDefinition $topic)
 * @method static void deleteTopic(string $topicName)
 * @method static bool topicExists(string $topicName)
 * @method static string[] listTopics()
 * @method static array<string, mixed> getTopicMetadata(string $topicName)
 * @method static void alterTopicConfig(string $topicName, array $configs)
 * @method static void extend(string $name, callable $callback)
 * @method static void purge(?string $name = null)
 *
 * @see \Nassirian\LaravelKafkaMigration\KafkaManager
 */
class KafkaManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Nassirian\LaravelKafkaMigration\KafkaManager::class;
    }
}
