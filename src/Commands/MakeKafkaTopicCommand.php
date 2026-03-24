<?php

namespace Nassirian\LaravelKafkaMigration\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeKafkaTopicCommand extends Command
{
    protected $signature = 'make:kafka-topic
                            {name : The name of the Kafka topic (e.g. orders, user-events)}
                            {--path= : Custom path for the migration file}';

    protected $description = 'Create a new Kafka topic migration file';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $topicName = $this->argument('name');
        $topicName = $this->normalizeTopicName((string) $topicName);

        $path     = $this->getMigrationsPath();
        $fileName = $this->buildFileName($topicName);
        $fullPath = $path . DIRECTORY_SEPARATOR . $fileName;

        // Ensure directory exists
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }

        // Write the stub
        $stub    = $this->buildStub($topicName);
        $this->files->put($fullPath, $stub);

        $this->components->info("Kafka migration created: <fg=cyan>{$fileName}</>");

        return self::SUCCESS;
    }

    protected function normalizeTopicName(string $name): string
    {
        // Convert slashes to underscores, strip invalid chars
        return preg_replace('/[^a-zA-Z0-9_\-.]/', '_', str_replace('/', '_', $name));
    }

    protected function getMigrationsPath(): string
    {
        if ($this->option('path')) {
            return (string) $this->option('path');
        }

        return (string) $this->laravel['config']->get(
            'kafka-migration.migrations_path',
            database_path('kafka-migrations')
        );
    }

    protected function buildFileName(string $topicName): string
    {
        $timestamp = date('Y_m_d_His');
        $slug      = strtolower(str_replace(['-', '.'], '_', $topicName));

        return "{$timestamp}_create_{$slug}_topic.php";
    }

    protected function buildStub(string $topicName): string
    {
        // Look for a published stub first
        $publishedStub = base_path('stubs/kafka-topic.stub');
        $defaultStub   = __DIR__ . '/../../stubs/kafka-topic.stub';

        $stubPath = $this->files->exists($publishedStub) ? $publishedStub : $defaultStub;
        $stub     = $this->files->get($stubPath);

        $className = $this->topicNameToClassName($topicName);
        $stub      = str_replace('{{ className }}', $className, $stub);
        $stub      = str_replace('{{ topicName }}', $topicName, $stub);

        return $stub;
    }

    protected function topicNameToClassName(string $topicName): string
    {
        $normalized = str_replace(['-', '.', '_'], ' ', $topicName);

        return 'Create' . str_replace(' ', '', ucwords($normalized)) . 'Topic';
    }
}
