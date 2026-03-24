<?php

use Nassirian\LaravelKafkaMigration\Migration\KafkaMigration;

return new class extends KafkaMigration
{
    public function up(): void
    {
        $this->createTopic(
            $this->topic('orders')
                ->partitions(3)
                ->replicationFactor(1)
                ->retentionMs(604_800_000)
                ->cleanupPolicy('delete')
        );
    }

    public function down(): void
    {
        $this->deleteTopic('orders');
    }
};
