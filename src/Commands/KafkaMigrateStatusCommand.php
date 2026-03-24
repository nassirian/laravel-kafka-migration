<?php

namespace Nassirian\LaravelKafkaMigration\Commands;

use Illuminate\Console\Command;
use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrator;

class KafkaMigrateStatusCommand extends Command
{
    protected $signature = 'kafka:migrate:status
                            {--path=* : One or more paths to the Kafka migration files}';

    protected $description = 'Show the status of each Kafka topic migration';

    protected KafkaMigrator $migrator;

    public function __construct(KafkaMigrator $migrator)
    {
        parent::__construct();
        $this->migrator = $migrator;
    }

    public function handle(): int
    {
        if (! $this->migrator->repositoryExists()) {
            $this->error('Kafka migrations table not found. Run kafka:migrate first.');

            return self::FAILURE;
        }

        $paths  = $this->getMigrationsPaths();
        $status = $this->migrator->status($paths);

        if (empty($status)) {
            $this->info('No Kafka migration files found.');

            return self::SUCCESS;
        }

        $this->table(
            ['<fg=cyan>Batch</>', '<fg=cyan>Ran?</>', '<fg=cyan>Migration</>'],
            array_map(fn ($row) => [
                $row['batch'] ?? '-',
                $row['ran'] ? '<fg=green>Yes</>' : '<fg=yellow>No</>',
                $row['name'],
            ], $status)
        );

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    protected function getMigrationsPaths(): array
    {
        $paths = (array) $this->option('path');

        if (empty($paths)) {
            $paths = [
                (string) $this->laravel['config']->get(
                    'kafka-migration.migrations_path',
                    database_path('kafka-migrations')
                ),
            ];
        }

        return $paths;
    }
}
