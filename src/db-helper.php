<?php
/**
 * Database Helper for optimized SQLite3 operations
 * Implements connection reuse and prepared statement caching
 */

class DatabaseHelper {
    private static $instance = null;
    private $db = null;
    private $preparedStatements = [];
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        $db_path = dirname(__DIR__) . '/backend/data/zornell.db';
        
        // Ensure directories exist
        if (!file_exists(dirname($db_path))) {
            mkdir(dirname($db_path), 0755, true);
        }
        
        $this->db = new SQLite3($db_path);
        
        // Performance optimizations
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('PRAGMA journal_mode = WAL'); // Better concurrency
        $this->db->exec('PRAGMA synchronous = NORMAL'); // Faster writes
        $this->db->exec('PRAGMA cache_size = 10000'); // Larger cache
        $this->db->exec('PRAGMA temp_store = MEMORY'); // Use memory for temp tables
        $this->db->exec('PRAGMA mmap_size = 30000000000'); // Use memory-mapped I/O
        
        // Create tables if not exist
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        $this->db->exec($schema);
    }
    
    public function getDb() {
        return $this->db;
    }
    
    public function prepare($sql) {
        // Cache prepared statements for reuse
        $hash = md5($sql);
        if (!isset($this->preparedStatements[$hash])) {
            $this->preparedStatements[$hash] = $this->db->prepare($sql);
        }
        return $this->preparedStatements[$hash];
    }
    
    public function __destruct() {
        // Close all prepared statements
        foreach ($this->preparedStatements as $stmt) {
            $stmt->close();
        }
        
        if ($this->db) {
            $this->db->close();
        }
    }
}