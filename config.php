<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers - Allow requests from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database Configuration
// Get from environment variable or use default
$DATABASE_URL = getenv('DATABASE_URL') ?: 'postgresql://postgres:[hwT4dE40fCw2u4Kv]@db.emgyusfleddbyyplypsj.supabase.co:5432/postgres';

/**
 * Parse DATABASE_URL and create PDO connection
 */
function getDatabaseConnection() {
    global $DATABASE_URL;
    
    try {
        // Parse the database URL
        $db = parse_url($DATABASE_URL);
        
        $host = $db['host'];
        $port = isset($db['port']) ? $db['port'] : 5432;
        $dbname = ltrim($db['path'], '/');
        $user = $db['user'];
        $password = isset($db['pass']) ? $db['pass'] : '';
        
        // Create DSN string
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
        
        // Create PDO instance
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        return $pdo;
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database connection failed',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

/**
 * Send JSON response
 */
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 500) {
    sendJSON(['error' => $message], $statusCode);
}
?>
