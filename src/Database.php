<?php

namespace SimpleFinance;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $configFile = __DIR__ . '/../config.php';
            if (!file_exists($configFile)) {
                $configFile = __DIR__ . '/../config.example.php';
            }

            if (!file_exists($configFile)) {
                throw new RuntimeException("Không tìm thấy file cấu hình config.php hoặc config.example.php");
            }

            $config = require $configFile;
            $db = $config['db'] ?? [];

            $host     = $db['host'] ?? '127.0.0.1';
            $port     = $db['port'] ?? 3306;
            $dbname   = $db['dbname'] ?? 'simplefinance';
            $username = $db['username'] ?? 'root';
            $password = $db['password'] ?? '';
            $charset  = $db['charset'] ?? 'utf8mb4';
            $options  = $db['options'] ?? [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,
            ];

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                throw new RuntimeException("Lỗi kết nối cơ sở dữ liệu MySQL: " . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }

        return self::$instance;
    }

    /**
     * Cho phép gán một instance PDO thủ công (phục vụ testing hoặc mock)
     */
    public static function setConnection(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
