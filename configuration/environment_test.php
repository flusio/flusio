<?php

$db_host = $dotenv->pop('DB_HOST');
$db_port = intval($dotenv->pop('DB_PORT', '5432'));
$db_name = 'flusio_test';

$temporary_directory = sys_get_temp_dir() . '/flusio/' . \flusio\utils\Random::hex(10);
$data_directory = $temporary_directory . '/data';
$cache_directory = $temporary_directory . '/cache';
$media_directory = $temporary_directory . '/media';
@mkdir($temporary_directory, 0777, true);
@mkdir($data_directory, 0777, true);
@mkdir($cache_directory, 0777, true);
@mkdir($media_directory, 0777, true);

$subscriptions_host = $dotenv->pop('APP_SUBSCRIPTIONS_HOST');

return [
    'app_name' => 'flusio',

    'secret_key' => $dotenv->pop('APP_SECRET_KEY'),

    'session_lifetime' => 30,

    'url_options' => [
        'host' => 'test.flus.io',
        'protocol' => 'https',
    ],

    'application' => [
        'support_email' => $dotenv->pop('APP_SUPPORT_EMAIL'),
        'brand' => 'flusio',
        'version' => trim(@file_get_contents($app_path . '/VERSION.txt')),
        'tmp_path' => $temporary_directory,
        'cache_path' => $cache_directory,
        'media_path' => $media_directory,
        'demo' => false,
        'registrations_opened' => true,
        'subscriptions_enabled' => false, // should be enable on a case-by-case basis
        'subscriptions_host' => $subscriptions_host,
        'subscriptions_private_key' => $dotenv->pop('APP_SUBSCRIPTIONS_PRIVATE_KEY'),
        'job_adapter' => 'test',
        'pocket_consumer_key' => $dotenv->pop('APP_POCKET_CONSUMER_KEY'),
        'cli_locale' => $dotenv->pop('CLI_LOCALE'),
    ],

    'database' => [
        'dsn' => "pgsql:host={$db_host};port={$db_port};dbname={$db_name}",
        'username' => $dotenv->pop('DB_USERNAME'),
        'password' => $dotenv->pop('DB_PASSWORD'),
    ],

    'mailer' => [
        'type' => 'test',
        'from' => 'root@localhost',
    ],

    'data_path' => $data_directory,
    'no_syslog' => !getenv('APP_SYSLOG_ENABLED'),
];
