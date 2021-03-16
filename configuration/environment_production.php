<?php

$db_host = $dotenv->pop('DB_HOST');
$db_port = intval($dotenv->pop('DB_PORT', '5432'));
$db_name = $dotenv->pop('DB_NAME', 'flusio_production');

$subscriptions_host = $dotenv->pop('APP_SUBSCRIPTIONS_HOST');

return [
    'app_name' => 'flusio',

    'secret_key' => $dotenv->pop('APP_SECRET_KEY'),

    'session_lifetime' => 30,

    'url_options' => [
        'protocol' => 'https',
        'host' => $dotenv->pop('APP_HOST'),
        'path' => $dotenv->pop('APP_PATH', '/'),
        'port' => intval($dotenv->pop('APP_PORT', '443')),
    ],

    'application' => [
        'support_email' => $dotenv->pop('APP_SUPPORT_EMAIL'),
        'brand' => $dotenv->pop('APP_BRAND', 'flusio'),
        'version' => trim(@file_get_contents($app_path . '/VERSION.txt')),
        'cache_path' => $dotenv->pop('APP_CACHE_PATH', $app_path . '/cache'),
        'media_path' => $dotenv->pop('APP_MEDIA_PATH', $app_path . '/public/media'),
        'demo' => filter_var($dotenv->pop('APP_DEMO', false), FILTER_VALIDATE_BOOLEAN),
        'registrations_opened' => filter_var($dotenv->pop('APP_OPEN_REGISTRATIONS', true), FILTER_VALIDATE_BOOLEAN),
        'subscriptions_enabled' => $subscriptions_host !== null,
        'subscriptions_host' => $subscriptions_host,
        'subscriptions_private_key' => $dotenv->pop('APP_SUBSCRIPTIONS_PRIVATE_KEY'),
        'job_adapter' => 'database',
        'pocket_consumer_key' => $dotenv->pop('APP_POCKET_CONSUMER_KEY'),
        'cli_locale' => $dotenv->pop('CLI_LOCALE'),
    ],

    'database' => [
        'dsn' => "pgsql:host={$db_host};port={$db_port};dbname={$db_name}",
        'username' => $dotenv->pop('DB_USERNAME'),
        'password' => $dotenv->pop('DB_PASSWORD'),
    ],

    'mailer' => [
        'type' => $dotenv->pop('APP_MAILER'),
        'from' => $dotenv->pop('SMTP_FROM'),
        'smtp' => [
            'domain' => $dotenv->pop('SMTP_DOMAIN'),
            'host' => $dotenv->pop('SMTP_HOST'),
            'port' => intval($dotenv->pop('SMTP_PORT')),
            'auth' => filter_var($dotenv->pop('SMTP_AUTH'), FILTER_VALIDATE_BOOLEAN),
            'auth_type' => $dotenv->pop('SMTP_AUTH_TYPE', ''),
            'username' => $dotenv->pop('SMTP_USERNAME'),
            'password' => $dotenv->pop('SMTP_PASSWORD'),
            'secure' => $dotenv->pop('SMTP_SECURE', ''),
        ],
    ],
];
