<?php
/**
 * Database — thin PDO wrapper with prepared statements and helpers.
 * Singleton instance keeps a single connection per request.
 */
declare(strict_types=1);

namespace Mori;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $host    = Config::get('DB_HOST', '127.0.0.1');
        $port    = Config::get('DB_PORT', '3306');
        $dbname  = Config::get('DB_NAME', 'mori_capital');
        $user    = Config::get('DB_USER', 'mori');
        $pass    = Config::get('DB_PASS', '');
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed', 0, $e);
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [], int $col = 0): mixed
    {
        $val = $this->query($sql, $params)->fetchColumn($col);
        return $val === false ? null : $val;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ":{$c}", $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', $placeholders)
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        if (empty($where)) {
            throw new RuntimeException('Refusing to UPDATE without WHERE clause');
        }
        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[] = "`{$k}` = :set_{$k}";
            $params["set_{$k}"] = $v;
        }
        $wh = [];
        foreach ($where as $k => $v) {
            $wh[] = "`{$k}` = :w_{$k}";
            $params["w_{$k}"] = $v;
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $set),
            implode(' AND ', $wh)
        );
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        if (empty($where)) {
            throw new RuntimeException('Refusing to DELETE without WHERE clause');
        }
        $wh = [];
        $params = [];
        foreach ($where as $k => $v) {
            $wh[] = "`{$k}` = :w_{$k}";
            $params["w_{$k}"] = $v;
        }
        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $table,
            implode(' AND ', $wh)
        );
        return $this->query($sql, $params)->rowCount();
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
