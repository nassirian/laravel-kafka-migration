<?php

namespace Nassirian\LaravelKafkaMigration\Commands;

use Illuminate\Console\Command;
use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrator;

class KafkaMigrateRollbackCommand extends Command
{
    protected $signature = 'kafka:migrate:rollback
                            {--path=* : One or more paths to the Kafka migration files}
                            {--step=0 : Number of migrations to roll back}
                            {--pretend : Simulate the rollback without running it}
                            {--force : Force the operation to run in production}';

    protected $description = 'Rollback the last batch of Kafka topic migrations';

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
        $step    = (int) $this->option('step');

        $rolled = $this->migrator->rollback($paths, $pretend, $step);

        foreach ($this->migrator->getNotes() as $note) {
            $this->line($note);
        }

        if (empty($rolled)) {
            $this->info('Nothing to rollback.');
        } else {
            $this->info(count($rolled) . ' Kafka topic migration(s) rolled back.');
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
                $this->warn('Kafka rollback cancelled.');

                return false;
            }
        }

        return true;
    }
}
