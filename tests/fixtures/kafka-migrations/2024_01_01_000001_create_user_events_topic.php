<?php

use Nassirian\LaravelKafkaMigration\Migration\KafkaMigration;

return new class extends KafkaMigration
{
    public function up(): void
    {
        $this->createTopic(
            $this->topic('user-events')
                ->partitions(6)
                ->replicationFactor(2)
                ->retentionMs(86_400_000)
                ->compressionType('gzip')
        );
    }

    public function down(): void
    {
        $this->deleteTopic('user-events');
    }
};
