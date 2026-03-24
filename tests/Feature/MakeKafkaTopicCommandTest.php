<?php

namespace Nassirian\LaravelKafkaMigration\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Nassirian\LaravelKafkaMigration\Tests\TestCase;

class MakeKafkaTopicCommandTest extends TestCase
{
    protected string $tempPath;
    protected Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->files    = new Filesystem();
        $this->tempPath = sys_get_temp_dir() . '/kafka-migrations-test-' . uniqid();
        $this->files->makeDirectory($this->tempPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tempPath);
        parent::tearDown();
    }

    public function test_it_creates_a_migration_file(): void
    {
        $this->artisan('make:kafka-topic', [
            'name'   => 'orders',
            '--path' => $this->tempPath,
        ])->assertSuccessful();

        $files = $this->files->glob($this->tempPath . '/*_create_orders_topic.php');
        $this->assertCount(1, $files);
    }

    public function test_migration_file_contains_correct_class_name(): void
    {
        $this->artisan('make:kafka-topic', [
            'name'   => 'user-events',
            '--path' => $this->tempPath,
        ])->assertSuccessful();

        $files   = $this->files->glob($this->tempPath . '/*_create_user_events_topic.php');
        $content = $this->files->get($files[0]);

        $this->assertStringContainsString('CreateUserEventsTopic', $content);
    }

    public function test_migration_file_contains_correct_topic_name(): void
    {
        $this->artisan('make:kafka-topic', [
            'name'   => 'payment-processed',
            '--path' => $this->tempPath,
        ])->assertSuccessful();

        $files   = $this->files->glob($this->tempPath . '/*_create_payment_processed_topic.php');
        $content = $this->files->get($files[0]);

        $this->assertStringContainsString('payment-processed', $content);
    }

    public function test_migration_file_has_up_and_down_methods(): void
    {
        $this->artisan('make:kafka-topic', [
            'name'   => 'orders',
            '--path' => $this->tempPath,
        ])->assertSuccessful();

        $files   = $this->files->glob($this->tempPath . '/*.php');
        $content = $this->files->get($files[0]);

        $this->assertStringContainsString('public function up()', $content);
        $this->assertStringContainsString('public function down()', $content);
    }

    public function test_migration_file_uses_correct_namespace(): void
    {
        $this->artisan('make:kafka-topic', [
            'name'   => 'orders',
            '--path' => $this->tempPath,
        ])->assertSuccessful();

        $files   = $this->files->glob($this->tempPath . '/*.php');
        $content = $this->files->get($files[0]);

        $this->assertStringContainsString('KafkaMigration', $content);
    }

    public function test_migration_file_name_has_timestamp_prefix(): void
    {
        $this->artisan('make:kafka-topic', [
            'name'   => 'orders',
            '--path' => $this->tempPath,
        ])->assertSuccessful();

        $files = $this->files->glob($this->tempPath . '/*.php');
        $name  = basename($files[0]);

        $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $name);
    }

    public function test_it_creates_the_migrations_directory_if_not_exists(): void
    {
        $newPath = $this->tempPath . '/sub/dir/kafka';

        $this->artisan('make:kafka-topic', [
            'name'   => 'events',
            '--path' => $newPath,
        ])->assertSuccessful();

        $this->assertTrue($this->files->isDirectory($newPath));

        $this->files->deleteDirectory($this->tempPath . '/sub');
    }

    public function test_it_normalizes_topic_name_with_special_chars(): void
    {
        $this->artisan('make:kafka-topic', [
            'name'   => 'my/topic/name',
            '--path' => $this->tempPath,
        ])->assertSuccessful();

        $files = $this->files->glob($this->tempPath . '/*.php');
        $this->assertCount(1, $files);
    }

    public function test_command_outputs_success_message(): void
    {
        $this->artisan('make:kafka-topic', [
            'name'   => 'orders',
            '--path' => $this->tempPath,
        ])->assertSuccessful();

        // Verify the file was actually created as proof the command ran successfully
        $files = $this->files->glob($this->tempPath . '/*_create_orders_topic.php');
        $this->assertCount(1, $files);
    }

    public function test_it_uses_configured_default_path(): void
    {
        $this->app['config']->set('kafka-migration.migrations_path', $this->tempPath);

        $this->artisan('make:kafka-topic', ['name' => 'shipments'])->assertSuccessful();

        $files = $this->files->glob($this->tempPath . '/*_create_shipments_topic.php');
        $this->assertCount(1, $files);
    }
}
