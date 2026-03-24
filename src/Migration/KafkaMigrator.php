<?php

namespace Nassirian\LaravelKafkaMigration\Migration;

use Illuminate\Filesystem\Filesystem;
use Nassirian\LaravelKafkaMigration\Contracts\KafkaDriverInterface;
use Nassirian\LaravelKafkaMigration\Contracts\KafkaMigrationRepositoryInterface;
use Nassirian\LaravelKafkaMigration\Exceptions\KafkaMigrationException;

class KafkaMigrator
{
    protected KafkaMigrationRepositoryInterface $repository;
    protected KafkaDriverInterface $driver;
    protected Filesystem $files;

    /** @var string[] */
    protected array $notes = [];

    public function __construct(
        KafkaMigrationRepositoryInterface $repository,
        KafkaDriverInterface $driver,
        Filesystem $files
    ) {
        $this->repository = $repository;
        $this->driver     = $driver;
        $this->files      = $files;
    }

    // ------------------------------------------------------------------
    // Migrate up
    // ------------------------------------------------------------------

    /**
     * Run all pending migrations.
     *
     * @param string[] $paths
     * @return array<int, array{name: string, migration: KafkaMigration}>
     */
    public function run(array $paths = [], bool $pretend = false): array
    {
        $files   = $this->getMigrationFiles($paths);
        $ran     = $this->repository->getRan();
        $pending = array_diff(array_keys($files), $ran);
        sort($pending);

        if (empty($pending)) {
            $this->note('<info>Nothing to migrate.</info>');

            return [];
        }

        $batch     = $this->repository->getNextBatchNumber();
        $migrated  = [];

        foreach ($pending as $file) {
            $migration = $this->resolveMigration($files[$file]);
            $this->runMigration($migration, $file, $batch, $pretend, 'up');
            $migrated[] = ['name' => $file, 'migration' => $migration];
        }

        return $migrated;
    }

    /**
     * Run a specific migration up.
     */
    protected function runMigration(
        KafkaMigration $migration,
        string $name,
        int $batch,
        bool $pretend,
        string $direction
    ): void {
        $this->note("<comment>Migrating:</comment> {$name}");

        if ($pretend) {
            $this->note("<info>Pretend:</info> Would call {$direction}() on {$name}");

            return;
        }

        $migration->setDriver($this->driver);

        try {
            $migration->{$direction}();
        } catch (\Throwable $e) {
            throw new KafkaMigrationException(
                "Migration '{$name}' failed in {$direction}(): " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        if ($direction === 'up') {
            $this->repository->log($name, $batch);
        } else {
            $this->repository->delete($name);
        }

        $this->note("<info>Migrated:</info>  {$name}");
    }

    // ------------------------------------------------------------------
    // Rollback
    // ------------------------------------------------------------------

    /**
     * Rollback the last batch of migrations.
     *
     * @param string[] $paths
     * @return array<int, array{name: string, migration: KafkaMigration}>
     */
    public function rollback(array $paths = [], bool $pretend = false, int $step = 0): array
    {
        $files       = $this->getMigrationFiles($paths);
        $toRollback  = $this->repository->getLast();

        if ($step > 0) {
            $ran = $this->repository->getRan();
            $toRollback = array_slice(array_reverse($ran), 0, $step);
        }

        if (empty($toRollback)) {
            $this->note('<info>Nothing to rollback.</info>');

            return [];
        }

        $rolledBack = [];

        foreach (array_reverse($toRollback) as $name) {
            if (! isset($files[$name])) {
                $this->note("<comment>Migration not found, skipping:</comment> {$name}");
                continue;
            }

            $migration = $this->resolveMigration($files[$name]);
            $this->runMigration($migration, $name, 0, $pretend, 'down');
            $rolledBack[] = ['name' => $name, 'migration' => $migration];
        }

        return $rolledBack;
    }

    /**
     * Reset all migrations.
     *
     * @param string[] $paths
     * @return array<int, array{name: string, migration: KafkaMigration}>
     */
    public function reset(array $paths = [], bool $pretend = false): array
    {
        $files  = $this->getMigrationFiles($paths);
        $ran    = array_reverse($this->repository->getRan());

        if (empty($ran)) {
            $this->note('<info>Nothing to reset.</info>');

            return [];
        }

        $reset = [];

        foreach ($ran as $name) {
            if (! isset($files[$name])) {
                $this->note("<comment>Migration not found, skipping:</comment> {$name}");
                continue;
            }

            $migration = $this->resolveMigration($files[$name]);
            $this->runMigration($migration, $name, 0, $pretend, 'down');
            $reset[] = ['name' => $name, 'migration' => $migration];
        }

        return $reset;
    }

    // ------------------------------------------------------------------
    // File discovery
    // ------------------------------------------------------------------

    /**
     * Get migration files from the given paths, keyed by migration name.
     *
     * @param string[] $paths
     * @return array<string, string>
     */
    public function getMigrationFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (! $this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->glob("{$path}/*.php") as $file) {
                $name         = $this->getMigrationName($file);
                $files[$name] = $file;
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Extract the migration name from a file path.
     */
    public function getMigrationName(string $path): string
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Instantiate a migration from its file path.
     *
     * Supports both:
     *  - Anonymous class files:  `return new class extends KafkaMigration { ... };`
     *  - Named class files:      `class CreateOrdersTopic extends KafkaMigration { ... }`
     *
     * IMPORTANT: we intentionally use `getRequire` (plain `require`) rather than
     * `requireOnce`. PHPUnit runs every test in the same process, so `require_once`
     * would only evaluate each fixture file once and would return `true` on all
     * subsequent calls — breaking anonymous-class detection for every test after
     * the first. `require` re-evaluates the file every time, so the anonymous class
     * instance is returned fresh on each call.
     *
     * Named-class migrations are safe too: `class_exists()` is checked after the
     * require, so re-declaring an already-loaded named class would be a PHP fatal
     * anyway — callers should ensure class names are unique (which the timestamp
     * prefix in filenames guarantees for real migrations).
     */
    protected function resolveMigration(string $file): KafkaMigration
    {
        // `getRequire` uses plain `require`, which always evaluates the file and
        // returns the value of its last expression. For anonymous-class stubs that
        // end with `return new class ...;` this gives us the instance directly.
        // For named-class files it returns 1 (or true).
        $returned = $this->files->getRequire($file);

        // Case 1 — anonymous class: the file returned an instance directly.
        if ($returned instanceof KafkaMigration) {
            return $returned;
        }

        // Case 2 — named class: derive class name from the filename.
        $class = $this->classFromFile($file);

        if (! class_exists($class)) {
            throw new KafkaMigrationException(
                "Migration file '{$file}' must either return an anonymous class instance " .
                "or define a class named '{$class}'."
            );
        }

        $instance = new $class();

        if (! $instance instanceof KafkaMigration) {
            throw new KafkaMigrationException(
                "Class '{$class}' must extend " . KafkaMigration::class
            );
        }

        return $instance;
    }

    /**
     * Convert a migration filename to a PascalCase class name.
     * e.g. 2024_01_01_000000_create_orders_topic → CreateOrdersTopic
     */
    protected function classFromFile(string $file): string
    {
        $name = $this->getMigrationName($file);
        // Strip timestamp prefix (YYYY_MM_DD_HHMMSS_)
        $name = (string) (preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name) ?? $name);

        return str_replace('_', '', ucwords($name, '_'));
    }

    // ------------------------------------------------------------------
    // Status
    // ------------------------------------------------------------------

    /**
     * Get the status of all migrations.
     *
     * @param string[] $paths
     * @return array<int, array{name: string, ran: bool, batch: int|null}>
     */
    public function status(array $paths = []): array
    {
        $files  = $this->getMigrationFiles($paths);
        $ran    = $this->repository->getRan();
        $batches = $this->repository->getMigrationsByBatch();

        // Build a quick lookup: migration name => batch number
        $batchByName = [];
        foreach ($batches as $batch => $migrations) {
            foreach ($migrations as $migration) {
                $batchByName[$migration] = $batch;
            }
        }

        $status = [];

        foreach (array_keys($files) as $name) {
            $didRun = in_array($name, $ran, true);
            $status[] = [
                'name'  => $name,
                'ran'   => $didRun,
                'batch' => $didRun ? ($batchByName[$name] ?? null) : null,
            ];
        }

        return $status;
    }

    // ------------------------------------------------------------------
    // Notes
    // ------------------------------------------------------------------

    public function note(string $message): void
    {
        $this->notes[] = $message;
    }

    /**
     * @return string[]
     */
    public function getNotes(): array
    {
        return $this->notes;
    }

    public function clearNotes(): void
    {
        $this->notes = [];
    }

    public function getRepository(): KafkaMigrationRepositoryInterface
    {
        return $this->repository;
    }

    public function repositoryExists(): bool
    {
        return $this->repository->repositoryExists();
    }
}
