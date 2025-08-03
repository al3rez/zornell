<?php
/**
 * Database configuration for Zornell
 * 
 * This file defines database paths based on the environment
 */

// Detect if we're in production or development
$isProduction = !file_exists(__DIR__ . '/../.git') && file_exists('/var/lib/zornell');

// Database configuration
return [
    // Main database path
    'database_path' => $isProduction 
        ? '/var/lib/zornell/database/zornell.db'
        : dirname(__DIR__) . '/backend/data/zornell.db',
    
    // Backup directory
    'backup_dir' => $isProduction
        ? '/var/lib/zornell/backups'
        : dirname(__DIR__) . '/backend/data/backups',
    
    // Log paths
    'error_log' => $isProduction
        ? '/var/lib/zornell/logs/error.log'
        : dirname(__DIR__) . '/backend/data/error.log',
    
    'api_error_log' => $isProduction
        ? '/var/lib/zornell/logs/api-errors.log'
        : dirname(__DIR__) . '/backend/data/api-errors.log',
    
    // SQLite pragmas for optimal performance
    'pragmas' => [
        'PRAGMA foreign_keys = ON',
        'PRAGMA journal_mode = WAL',
        'PRAGMA synchronous = NORMAL',
        'PRAGMA cache_size = 10000',
        'PRAGMA temp_store = MEMORY',
        'PRAGMA mmap_size = 30000000000'
    ],
    
    // Permissions
    'directory_permissions' => 0750,
    'file_permissions' => 0640,
];