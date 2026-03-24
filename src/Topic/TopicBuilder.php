<?php

namespace Nassirian\LaravelKafkaMigration\Topic;

class TopicBuilder
{
    /**
     * Create a new topic definition.
     */
    public static function create(string $name): TopicDefinition
    {
        return new TopicDefinition($name);
    }
}
