<?php

namespace Nassirian\LaravelKafkaMigration\Migration;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Nassirian\LaravelKafkaMigration\Contracts\KafkaMigrationRepositoryInterface;

class KafkaMigrationRepository implements KafkaMigrationRepositoryInterface
{
    protected ConnectionResolverInterface $resolver;
    protected string $table;
    protected ?string $connection = null;

    public function __construct(ConnectionResolverInterface $resolver, string $table)
    {
        $this->resolver = $resolver;
        $this->table    = $table;
    }

    /**
     * Set the database connection to use.
     */
    public function setConnection(?string $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @return string[]
     */
    public function getRan(): array
    {
        return $this->table()
            ->orderBy('batch')
            ->orderBy('migration')
            ->pluck('migration')
            ->toArray();
    }

    /**
     * @return array<int, string[]>
     */
    public function getMigrationsByBatch(): array
    {
        $rows   = $this->table()->orderBy('batch')->orderBy('migration')->get();
        $result = [];

        foreach ($rows as $row) {
            $result[$row->batch][] = $row->migration;
        }

        return $result;
    }

    /**
     * @return string[]
     */
    public function getLast(): array
    {
        $lastBatch = $this->getLastBatchNumber();

        if ($lastBatch === 0) {
            return [];
        }

        return $this->table()
            ->where('batch', $lastBatch)
            ->orderByDesc('migration')
            ->pluck('migration')
            ->toArray();
    }

    public function log(string $file, int $batch): void
    {
        $record = ['migration' => $file, 'batch' => $batch];
        $this->table()->insert($record);
    }

    public function delete(string $file): void
    {
        $this->table()->where('migration', $file)->delete();
    }

    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    public function getLastBatchNumber(): int
    {
        return (int) $this->table()->max('batch');
    }

    public function createRepository(): void
    {
        $schema = $this->getConnection()->getSchemaBuilder();

        if ($schema->hasTable($this->table)) {
            return;
        }

        $schema->create($this->table, function ($table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function repositoryExists(): bool
    {
        return $this->getConnection()->getSchemaBuilder()->hasTable($this->table);
    }

    protected function table(): \Illuminate\Database\Query\Builder
    {
        return $this->getConnection()->table($this->table)->useWritePdo();
    }

    protected function getConnection(): ConnectionInterface
    {
        return $this->resolver->connection($this->connection);
    }
}
