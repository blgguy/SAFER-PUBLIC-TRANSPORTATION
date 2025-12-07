<?php
// File: src/core/DatabaseConnection.php
// Description: Singleton class for managing PDO database connections, transactions, and logging.

namespace SafeTransport\Core;

use PDO;
use PDOStatement;
use PDOException;

class DatabaseConnection
{
    /** @var DatabaseConnection The single instance of the class. */
    private static $instance = null;

    /** @var PDO The PDO connection object. */
    private $pdo;

    /** @var array Database configuration settings. */
    private $config;

    /** @var array Array to store executed queries for debugging. */
    private $queryLog = [];

    /** @var bool Toggle query logging. */
    private $enableQueryLog = true;

    // Prevent direct instantiation
    private function __construct()
    {
        // Load configurations from the file created in Prompt 1.2
        $this->config = require __DIR__ . '/../../config/database.php';
        
        // Check environment variable for enabling query log
        $this->enableQueryLog = (bool)getenv('DB_QUERY_LOG') ?: $this->enableQueryLog;

        $this->connect();
    }

    // Prevent cloning the instance
    private function __clone() {}

    /**
     * Gets the single instance of the DatabaseConnection class.
     * @return DatabaseConnection
     */
    public static function getInstance(): DatabaseConnection
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establishes the PDO connection. Handles initial connection error.
     */
    private function connect(): void
    {
        try {
            // Include persistence if pooling is enabled in config (simulated pooling)
            if ($this->config['pooling_enabled']) {
                $this->config['options'][PDO::ATTR_PERSISTENT] = true;
            } else {
                // Ensure persistence is not set if explicitly disabled
                unset($this->config['options'][PDO::ATTR_PERSISTENT]);
            }
            
            $this->pdo = new PDO(
                $this->config['dsn'], 
                $this->config['user'], 
                $this->config['pass'], 
                $this->config['options']
            );
            
            // Set error mode again just in case, though it's in options
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            // CRITICAL ERROR: Log and terminate if connection fails
            $error = "Database Connection Failed: " . $e->getMessage();
            error_log($error);
            // In a real application, you'd show a user-friendly error page here.
            die($error); 
        }
    }

    /**
     * Executes a prepared SQL statement.
     * @param string $sql The SQL query string.
     * @param array $params The parameters to bind to the statement.
     * @return PDOStatement
     * @throws PDOException if the query fails.
     */
    private function executeStatement(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $startTime = microtime(true);
            $stmt->execute($params);
            $endTime = microtime(true);

            // Log the query if enabled
            if ($this->enableQueryLog) {
                $this->logQuery($sql, $params, ($endTime - $startTime));
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            // Attempt automatic reconnection and retry on certain connection errors (e.g., MySQL gone away)
            if (strpos($e->getMessage(), 'SQLSTATE[HY000]') !== false || strpos($e->getMessage(), 'server has gone away') !== false) {
                error_log("DB Connection Lost. Attempting reconnect...");
                $this->connect(); // Reconnect
                
                // Retry the operation
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            }
            
            // Standard error handling
            $error = "DB Query Failed: " . $e->getMessage() . "\nSQL: " . $sql;
            error_log($error);
            
            // Re-throw exception for service layer to handle business logic
            throw $e;
        }
    }

    /**
     * Logs the executed query for debugging.
     * @param string $sql
     * @param array $params
     * @param float $time
     */
    private function logQuery(string $sql, array $params, float $time): void
    {
        $this->queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => round($time * 1000, 2) . 'ms',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // --------------------------------------------------------------------------
    // Public CRUD and Transaction Methods
    // --------------------------------------------------------------------------

    /**
     * Executes a SELECT query and returns the results.
     * @param string $sql
     * @param array $params
     * @param bool $singleResult If true, returns only the first row.
     * @return array|null
     */
    public function query(string $sql, array $params = [], bool $singleResult = false): ?array
    {
        $stmt = $this->executeStatement($sql, $params);
        $result = $singleResult ? $stmt->fetch() : $stmt->fetchAll();
        
        // PDO::FETCH_ASSOC returns false if no row is found for fetch().
        // Return null for consistency if no results.
        return $result ?: null;
    }

    /**
     * Inserts a record into the database.
     * @param string $table
     * @param array $data Associative array of column => value.
     * @return int The ID of the last inserted row.
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values = array_values($data);

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $this->executeStatement($sql, $values);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Updates records in the database.
     * @param string $table
     * @param array $data Associative array of column => value to set.
     * @param string $whereClause The WHERE condition (e.g., 'user_id = ?').
     * @param array $whereParams Parameters for the WHERE clause.
     * @return int The number of rows affected.
     */
    public function update(string $table, array $data, string $whereClause, array $whereParams): int
    {
        $setClauses = [];
        foreach (array_keys($data) as $column) {
            $setClauses[] = "{$column} = ?";
        }
        $setClause = implode(', ', $setClauses);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";
        $params = array_merge(array_values($data), $whereParams);

        $stmt = $this->executeStatement($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Deletes records from the database.
     * @param string $table
     * @param string $whereClause The WHERE condition (e.g., 'report_id = ?').
     * @param array $whereParams Parameters for the WHERE clause.
     * @return int The number of rows affected.
     */
    public function delete(string $table, string $whereClause, array $whereParams): int
    {
        $sql = "DELETE FROM {$table} WHERE {$whereClause}";
        $stmt = $this->executeStatement($sql, $whereParams);
        return $stmt->rowCount();
    }

    /**
     * Starts a transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commits a transaction.
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rolls back a transaction.
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * Retrieves the executed query log.
     * @return array
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }
}