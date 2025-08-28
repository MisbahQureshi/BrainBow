<?php
declare(strict_types=1);

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO)
        return $pdo;

    // Pull from .env (already loaded in public/index.php)
    $host = $_ENV['DB_HOST'];
    $port = (string) ($_ENV['DB_PORT']);
    $name = $_ENV['DB_NAME'];
    $user = $_ENV['DB_USER'];
    $pass = $_ENV['DB_PASS'];
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Older libmysql: "Unknown character set" → retry with utf8
        $msg = $e->getMessage();
        if ($charset === 'utf8mb4' && stripos($msg, 'Unknown character set') !== false) {
            $dsnUtf8 = "mysql:host=$host;port=$port;dbname=$name;charset=utf8";
            $pdo = new PDO($dsnUtf8, $user, $pass, $options);
            $pdo->exec("SET NAMES utf8");
            return $pdo;
        }

        // Friendly error (dev)
        http_response_code(500);
        exit('Database connection failed: ' . htmlspecialchars($msg));
    }
}
