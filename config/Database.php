<?php
/**
 * Database Connection Manager
 * Uses PDO for secure, prepared statement support
 * Works on both XAMPP and Hostinger
 */

require_once __DIR__ . '/Environment.php';

class Database {
    private static ?PDO $connection = null;
    private static array $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];

    /**
     * Get database connection (singleton pattern)
     */
    public static function connect(): PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        try {
            $host = Environment::get('DB_HOST', 'localhost');
            $port = Environment::get('DB_PORT', '3306');
            $name = Environment::get('DB_NAME');
            $user = Environment::get('DB_USER');
            $pass = Environment::get('DB_PASS', '');

            if (!$name) {
                throw new Exception("Database name not configured in .env");
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            
            self::$connection = new PDO($dsn, $user, $pass, self::$options);
            
            return self::$connection;
        } catch (PDOException $e) {
            if (Environment::isDebug()) {
                die('Database Connection Error: ' . $e->getMessage());
            } else {
                die('Unable to connect to database. Please contact administrator.');
            }
        }
    }

    /**
     * Execute prepared statement safely
     */
    public static function prepare(string $sql): PDOStatement {
        return self::connect()->prepare($sql);
    }

    /**
     * Execute query and return results
     */
    public static function query(string $sql, array $params = []): array {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute query and return single row
     */
    public static function queryOne(string $sql, array $params = []) {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Insert record
     */
    public static function insert(string $table, array $data): int {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = self::prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) self::connect()->lastInsertId();
    }

    /**
     * Update record
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = implode(',', array_map(fn($key) => "{$key}=?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        
        $stmt = self::prepare($sql);
        $params = array_merge(array_values($data), $whereParams);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    /**
     * Delete record
     */
    public static function delete(string $table, string $where, array $params = []): int {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Execute transaction
     */
    public static function beginTransaction(): void {
        self::connect()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): void {
        self::connect()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): void {
        self::connect()->rollBack();
    }

    /**
     * Check if table exists
     */
    public static function tableExists(string $tableName): bool {
        $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
        $result = self::queryOne($sql, [
            Environment::get('DB_NAME'),
            $tableName
        ]);
        return $result !== null;
    }

    /**
     * Get table column info
     */
    public static function getTableColumns(string $tableName): array {
        $sql = "SHOW COLUMNS FROM {$tableName}";
        return self::query($sql);
    }
}
