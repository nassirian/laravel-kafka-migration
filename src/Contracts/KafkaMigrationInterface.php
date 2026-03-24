<?php

namespace Nassirian\LaravelKafkaMigration\Contracts;

interface KafkaMigrationInterface
{
    /**
     * Run the migration (create topics).
     */
    public function up(): void;

    /**
     * Reverse the migration (delete topics).
     */
    public function down(): void;
}
