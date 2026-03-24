<?php

namespace Nassirian\LaravelKafkaMigration\Commands;

use Illuminate\Console\Command;
use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrator;

class KafkaMigrateCommand extends Command
{
    protected $signature = 'kafka:migrate
                            {--path=* : One or more paths to the Kafka migration files}
                            {--pretend : Simulate the migrations without running them}
                            {--force : Force the operation to run in production}';

    protected $description = 'Run pending Kafka topic migrations';

    protected KafkaMigrator $migrator;

    public function __construct(KafkaMigrator $migrator)
    {
        parent::__construct();
        $this->migrator = $migrator;
    }

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        if (! $this->migrator->repositoryExists()) {
            $this->migrator->getRepository()->createRepository();
            $this->info('Kafka migrations table created.');
        }

        $paths   = $this->getMigrationsPaths();
        $pretend = (bool) $this->option('pretend');

        $migrated = $this->migrator->run($paths, $pretend);

        foreach ($this->migrator->getNotes() as $note) {
            $this->line($note);
        }

        if (empty($migrated)) {
            $this->info('Nothing to migrate.');
        } else {
            $this->info(count($migrated) . ' Kafka topic migration(s) ran successfully.');
        }

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

    protected function confirmToProceed(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if ($this->laravel->environment('production')) {
            if (! $this->confirm('You are in <fg=red>production</> — proceed?')) {
                $this->warn('Kafka migration cancelled.');

                return false;
            }
        }

        return true;
    }
}
