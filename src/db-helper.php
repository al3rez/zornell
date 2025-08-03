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
        $config = require dirname(__DIR__) . '/config/database.php';
        $db_path = $config['database_path'];
        
        // Ensure directories exist
        if (!file_exists(dirname($db_path))) {
            mkdir(dirname($db_path), $config['directory_permissions'], true);
        }
        
        $this->db = new SQLite3($db_path);
        
        // Apply performance optimizations from config
        foreach ($config['pragmas'] as $pragma) {
            $this->db->exec($pragma);
        }
        
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