<?php

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require dirname(__DIR__, 2) . '/config.php';
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['db_host'],
            $config['db_name'],
            $config['db_charset']
        );

        self::$connection = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $timezone = (string) ($config['app_timezone'] ?? 'America/Sao_Paulo');
        date_default_timezone_set($timezone);

        $offset = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('P');
        self::$connection->exec("SET time_zone = " . self::$connection->quote($offset));

        return self::$connection;
    }
}
