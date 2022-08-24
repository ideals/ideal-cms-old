<?php

return [
    // Параметры подключения к БД
    'db' => [
        'host' => getenv('DB_HOST') ?: '[[DB_HOST]]',
        'login' => getenv('DB_LOGIN') ?: '[[DB_LOGIN]]',
        'password' => getenv('DB_PASSWORD') ?: '[[DB_PASS]]',
        'name' => getenv('DB_NAME') ?: '[[DB_NAME]]',
        'charset' => 'UTF-8',
        'prefix' => '[[DB_PREFIX]]'
    ],
];
