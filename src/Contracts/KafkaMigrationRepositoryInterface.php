<?php

namespace Nassirian\LaravelKafkaMigration\Contracts;

interface KafkaMigrationRepositoryInterface
{
    /**
     * Get the list of completed migrations.
     *
     * @return string[]
     */
    public function getRan(): array;

    /**
     * Get list of migrations batched by batch number.
     *
     * @return array<int, string[]>
     */
    public function getMigrationsByBatch(): array;

    /**
     * Get migrations for the last batch.
     *
     * @return string[]
     */
    public function getLast(): array;

    /**
     * Log that a migration was run.
     */
    public function log(string $file, int $batch): void;

    /**
     * Remove a migration from the log.
     */
    public function delete(string $file): void;

    /**
     * Get the next batch number.
     */
    public function getNextBatchNumber(): int;

    /**
     * Get the last batch number.
     */
    public function getLastBatchNumber(): int;

    /**
     * Create the migration repository if it doesn't exist.
     */
    public function createRepository(): void;

    /**
     * Determine if the migration repository exists.
     */
    public function repositoryExists(): bool;
}
