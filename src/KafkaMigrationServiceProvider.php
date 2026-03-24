<?php

namespace Nassirian\LaravelKafkaMigration;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Nassirian\LaravelKafkaMigration\Commands\KafkaMigrateCommand;
use Nassirian\LaravelKafkaMigration\Commands\KafkaMigrateResetCommand;
use Nassirian\LaravelKafkaMigration\Commands\KafkaMigrateRollbackCommand;
use Nassirian\LaravelKafkaMigration\Commands\KafkaMigrateStatusCommand;
use Nassirian\LaravelKafkaMigration\Commands\MakeKafkaTopicCommand;
use Nassirian\LaravelKafkaMigration\Contracts\KafkaMigrationRepositoryInterface;
use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrationRepository;
use Nassirian\LaravelKafkaMigration\Migration\KafkaMigrator;

class KafkaMigrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/kafka-migration.php',
            'kafka-migration'
        );

        // KafkaManager (driver manager)
        $this->app->singleton(KafkaManager::class, function ($app) {
            return new KafkaManager($app);
        });

        // Migration repository
        $this->app->singleton(KafkaMigrationRepositoryInterface::class, function ($app) {
            $table = $app['config']->get('kafka-migration.migrations_table', 'kafka_migrations');

            return new KafkaMigrationRepository(
                $app['db'],
                $table
            );
        });

        // Migrator
        $this->app->singleton(KafkaMigrator::class, function ($app) {
            $repository = $app->make(KafkaMigrationRepositoryInterface::class);
            $driver     = $app->make(KafkaManager::class)->driver();

            return new KafkaMigrator($repository, $driver, new Filesystem());
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/kafka-migration.php' => config_path('kafka-migration.php'),
            ], 'kafka-migration-config');

            $this->publishes([
                __DIR__ . '/../stubs/kafka-topic.stub' => base_path('stubs/kafka-topic.stub'),
            ], 'kafka-migration-stubs');

            $this->commands([
                MakeKafkaTopicCommand::class,
                KafkaMigrateCommand::class,
                KafkaMigrateRollbackCommand::class,
                KafkaMigrateResetCommand::class,
                KafkaMigrateStatusCommand::class,
            ]);
        }
    }
}
