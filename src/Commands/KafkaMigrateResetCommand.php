<?php

namespace Nassirian\LaravelKafkaMigration\Commands;

use Illuminate\Console\Command;
use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrator;

class KafkaMigrateResetCommand extends Command
{
    protected $signature = 'kafka:migrate:reset
                            {--path=* : One or more paths to the Kafka migration files}
                            {--pretend : Simulate the reset without running it}
                            {--force : Force the operation to run in production}';

    protected $description = 'Rollback all Kafka topic migrations';

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

        $paths   = $this->getMigrationsPaths();
        $pretend = (bool) $this->option('pretend');

        $reset = $this->migrator->reset($paths, $pretend);

        foreach ($this->migrator->getNotes() as $note) {
            $this->line($note);
        }

        if (empty($reset)) {
            $this->components->info('Nothing to reset.');
        } else {
            $this->components->info(count($reset) . ' Kafka topic migration(s) reset.');
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
                $this->components->warn('Kafka reset cancelled.');

                return false;
            }
        }

        return true;
    }
}
