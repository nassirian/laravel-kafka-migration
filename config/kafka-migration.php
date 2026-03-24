<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Kafka Driver
    |--------------------------------------------------------------------------
    |
    | This value determines the default driver used to connect to Kafka.
    | Supported drivers: "rdkafka", "longlang", "http", "mock", "jobcloud"
    |
    */
    'driver' => env('KAFKA_MIGRATION_DRIVER', 'longlang'),

    /*
    |--------------------------------------------------------------------------
    | Kafka Brokers
    |--------------------------------------------------------------------------
    |
    | A comma-separated list of Kafka broker addresses (host:port).
    |
    */
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),

    /*
    |--------------------------------------------------------------------------
    | Migrations Path
    |--------------------------------------------------------------------------
    |
    | The path where Kafka migration files will be stored.
    |
    */
    'migrations_path' => database_path('kafka-migrations'),

    /*
    |--------------------------------------------------------------------------
    | Migrations Table
    |--------------------------------------------------------------------------
    |
    | The database table used to track which Kafka migrations have been run.
    |
    */
    'migrations_table' => env('KAFKA_MIGRATIONS_TABLE', 'kafka_migrations'),

    /*
    |--------------------------------------------------------------------------
    | Connection Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout in milliseconds for connecting to Kafka brokers.
    |
    */
    'timeout' => env('KAFKA_TIMEOUT', 5000),

    /*
    |--------------------------------------------------------------------------
    | Driver Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specific to each Kafka driver.
    |
    */
    'drivers' => [

        /*
        |----------------------------------------------------------------------
        | RdKafka Driver
        |----------------------------------------------------------------------
        | Uses the rdkafka PHP extension. High performance, recommended for
        | production. Requires ext-rdkafka to be installed.
        |
        */
        'rdkafka' => [
            'metadata_broker_list' => env('KAFKA_BROKERS', 'localhost:9092'),
            'socket_timeout_ms'    => env('KAFKA_TIMEOUT', 5000),
            'log_level'            => env('KAFKA_LOG_LEVEL', LOG_WARNING),
            'security_protocol'    => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
            'sasl_mechanisms'      => env('KAFKA_SASL_MECHANISMS', null),
            'sasl_username'        => env('KAFKA_SASL_USERNAME', null),
            'sasl_password'        => env('KAFKA_SASL_PASSWORD', null),
            'ssl_ca_location'      => env('KAFKA_SSL_CA', null),
            'ssl_certificate_location' => env('KAFKA_SSL_CERT', null),
            'ssl_key_location'     => env('KAFKA_SSL_KEY', null),
        ],

        /*
        |----------------------------------------------------------------------
        | LongLang Driver
        |----------------------------------------------------------------------
        | Pure PHP Kafka client. No extension required. Suitable for
        | environments where ext-rdkafka cannot be installed.
        |
        */
        'longlang' => [
            'brokers'  => env('KAFKA_BROKERS', 'localhost:9092'),
            'timeout'  => (int) env('KAFKA_TIMEOUT', 5000),
            'username' => env('KAFKA_USERNAME', null),
            'password' => env('KAFKA_PASSWORD', null),
            'sasl_mechanisms' => env('KAFKA_SASL_MECHANISMS', null),
            'ssl'      => [
                'enabled' => env('KAFKA_SSL_ENABLED', false),
                'ca_cert' => env('KAFKA_SSL_CA', null),
                'client_cert' => env('KAFKA_SSL_CERT', null),
                'client_key'  => env('KAFKA_SSL_KEY', null),
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | HTTP Driver (Confluent REST Proxy)
        |----------------------------------------------------------------------
        | Connects to Kafka through the Confluent REST Proxy API.
        | Useful when direct broker access is not available.
        |
        */
        'http' => [
            'base_url'   => env('KAFKA_HTTP_URL', 'http://localhost:8082'),
            'timeout'    => (int) env('KAFKA_TIMEOUT', 5000),
            'username'   => env('KAFKA_HTTP_USERNAME', null),
            'password'   => env('KAFKA_HTTP_PASSWORD', null),
            'api_key'    => env('KAFKA_HTTP_API_KEY', null),
            'verify_ssl' => env('KAFKA_HTTP_VERIFY_SSL', true),
            'headers'    => [],
        ],

        /*
        |----------------------------------------------------------------------
        | Mock Driver
        |----------------------------------------------------------------------
        | In-memory driver for local development and testing.
        | Does not connect to a real Kafka instance.
        |
        */
        'mock' => [
            'store_path' => storage_path('kafka-mock'),
        ],

        /*
        |----------------------------------------------------------------------
        | Jobcloud Driver  (jobcloud/php-kafka-lib)
        |----------------------------------------------------------------------
        | Uses jobcloud/php-kafka-lib for fluent, opinionated configuration and
        | ext-rdkafka's AdminClient for topic management.
        |
        | Requirements:
        |   - ext-rdkafka ^4.0|^5.0|^6.0
        |   - composer require jobcloud/php-kafka-lib
        |
        | jobcloud/php-kafka-lib wraps rdkafka with a clean builder pattern and
        | automatically normalises broker lists, SASL, and SSL settings into the
        | RdKafka\Conf format expected by AdminClient.
        |
        */
        'jobcloud' => [
            'brokers'             => env('KAFKA_BROKERS', 'localhost:9092'),
            'socket_timeout_ms'   => (int) env('KAFKA_TIMEOUT', 5000),
            'metadata_request_timeout_ms' => (int) env('KAFKA_METADATA_TIMEOUT', 5000),
            'security_protocol'   => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
            'sasl_mechanisms'     => env('KAFKA_SASL_MECHANISMS', null),
            'sasl_username'       => env('KAFKA_SASL_USERNAME', null),
            'sasl_password'       => env('KAFKA_SASL_PASSWORD', null),
            'ssl_ca_location'     => env('KAFKA_SSL_CA', null),
            'ssl_certificate_location' => env('KAFKA_SSL_CERT', null),
            'ssl_key_location'    => env('KAFKA_SSL_KEY', null),
            // Any additional raw rdkafka config key => value pairs
            'extra_config'        => [],
        ],

    ],

];
