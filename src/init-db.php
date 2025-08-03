<?php
// Initialize database with schema

$db_path = dirname(__DIR__) . '/backend/data/zornell.db';
$schema_path = __DIR__ . '/schema.sql';

// Create data directory if it doesn't exist
if (!file_exists(dirname($db_path))) {
    mkdir(dirname($db_path), 0777, true);
}

try {
    // Open database
    $db = new SQLite3($db_path);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA journal_mode = WAL');
    
    // Read and execute schema
    if (file_exists($schema_path)) {
        $schema = file_get_contents($schema_path);
        $db->exec($schema);
        echo "✅ Database initialized successfully!\n";
    } else {
        echo "❌ Schema file not found at: $schema_path\n";
        exit(1);
    }
    
    // Check tables
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    echo "\n📊 Tables created:\n";
    while ($row = $result->fetchArray()) {
        echo "   - " . $row['name'] . "\n";
    }
    
    $db->close();
    echo "\n🎉 Database ready for use!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}