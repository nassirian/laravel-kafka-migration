<?php

namespace Nassirian\LaravelKafkaMigration\Drivers;

use Nassirian\LaravelKafkaMigration\Exceptions\KafkaConnectionException;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaTopicException;
use Nassirian\LaravelKafkaMigration\Topic\TopicDefinition;

/**
 * Driver that uses the Confluent REST Proxy HTTP API.
 *
 * Requires ext-curl or GuzzleHTTP.
 * REST Proxy must be running at the configured base_url.
 *
 * @see https://docs.confluent.io/platform/current/kafka-rest/
 */
class HttpDriver extends AbstractKafkaDriver
{
    protected string $baseUrl;

    protected function connect(): void
    {
        if (! extension_loaded('curl')) {
            throw new KafkaConnectionException(
                'The curl PHP extension is required for the HTTP Kafka driver.'
            );
        }

        $this->baseUrl = rtrim($this->config['base_url'] ?? 'http://localhost:8082', '/');

        // Verify we can reach the REST Proxy
        try {
            $this->request('GET', '/v3/clusters');
        } catch (\Throwable $e) {
            throw new KafkaConnectionException(
                'Failed to connect to Confluent REST Proxy at ' . $this->baseUrl . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function createTopic(TopicDefinition $topic): void
    {
        $this->ensureConnected();

        $clusterId = $this->getClusterId();
        $url       = "/v3/clusters/{$clusterId}/topics";

        $payload = [
            'topic_name'         => $topic->getName(),
            'partitions_count'   => $topic->getPartitions(),
            'replication_factor' => $topic->getReplicationFactor(),
        ];

        $configs = [];
        foreach ($topic->getConfigs() as $key => $value) {
            $configs[] = ['name' => $key, 'value' => $value];
        }
        if ($configs) {
            $payload['configs'] = $configs;
        }

        try {
            $response = $this->request('POST', $url, $payload);
        } catch (KafkaTopicException $e) {
            // Topic may already exist — REST Proxy returns 409
            if (str_contains($e->getMessage(), '409') || str_contains($e->getMessage(), 'already exists')) {
                return;
            }
            throw $e;
        }
    }

    public function deleteTopic(string $topicName): void
    {
        $this->ensureConnected();

        $clusterId = $this->getClusterId();
        $url       = "/v3/clusters/{$clusterId}/topics/{$topicName}";

        try {
            $this->request('DELETE', $url);
        } catch (KafkaTopicException $e) {
            // 404 means topic doesn't exist — that's acceptable for down()
            if (str_contains($e->getMessage(), '404')) {
                return;
            }
            throw $e;
        }
    }

    public function topicExists(string $topicName): bool
    {
        try {
            $clusterId = $this->getClusterId();
            $this->request('GET', "/v3/clusters/{$clusterId}/topics/{$topicName}");

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return string[]
     */
    public function listTopics(): array
    {
        $this->ensureConnected();

        $clusterId = $this->getClusterId();
        $response  = $this->request('GET', "/v3/clusters/{$clusterId}/topics");

        $topics = [];
        foreach ($response['data'] ?? [] as $item) {
            $name = $item['topic_name'] ?? '';
            if ($name && ! str_starts_with($name, '__')) {
                $topics[] = $name;
            }
        }

        return $topics;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTopicMetadata(string $topicName): array
    {
        $this->ensureConnected();

        $clusterId = $this->getClusterId();

        return $this->request('GET', "/v3/clusters/{$clusterId}/topics/{$topicName}");
    }

    /**
     * @param array<string, mixed> $configs
     */
    public function alterTopicConfig(string $topicName, array $configs): void
    {
        $this->ensureConnected();

        $clusterId = $this->getClusterId();
        $url       = "/v3/clusters/{$clusterId}/topics/{$topicName}/configs:alter";

        $data = [];
        foreach ($configs as $key => $value) {
            $data[] = ['name' => $key, 'value' => (string) $value, 'operation' => 'SET'];
        }

        $this->request('POST', $url, ['data' => $data]);
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected string $cachedClusterId = '';

    protected function getClusterId(): string
    {
        if ($this->cachedClusterId) {
            return $this->cachedClusterId;
        }

        $response = $this->request('GET', '/v3/clusters');
        $this->cachedClusterId = $response['data'][0]['cluster_id']
            ?? throw new KafkaConnectionException('Could not resolve Kafka cluster ID from REST Proxy.');

        return $this->cachedClusterId;
    }

    /**
     * Make an HTTP request to the Confluent REST Proxy.
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     * @throws KafkaTopicException
     */
    protected function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->config['timeout'] ?? 5000);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config['verify_ssl'] ?? true);

        $headers = array_merge(
            ['Content-Type: application/json', 'Accept: application/json'],
            $this->config['headers'] ?? []
        );

        if (! empty($this->config['username'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->config['username'] . ':' . ($this->config['password'] ?? ''));
        }

        if (! empty($this->config['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['api_key'];
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($body !== null) {
            $json = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $responseBody = curl_exec($ch);
        $statusCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new KafkaConnectionException('HTTP request failed: ' . $curlError);
        }

        if ($statusCode >= 400) {
            throw new KafkaTopicException(
                "HTTP {$statusCode} from Confluent REST Proxy ({$method} {$path}): " .
                ($responseBody ?: '(empty body)')
            );
        }

        if (empty($responseBody)) {
            return [];
        }

        $decoded = json_decode((string) $responseBody, true);

        return is_array($decoded) ? $decoded : [];
    }
}
