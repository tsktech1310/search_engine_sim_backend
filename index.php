<?php
require_once 'config.php';

// Simple router
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$uri = parse_url($request_uri, PHP_URL_PATH);

// Remove base path if deployed in subdirectory
$uri = str_replace('/index.php', '', $uri);

// Route the request
if ($request_method === 'GET' || $request_method === 'HEAD') {
    
    // Root endpoint - Health check
    if ($uri === '/' || $uri === '') {
        sendJSON([
            'status' => 'ok',
            'message' => 'Business Search API (PHP)',
            'version' => '1.0.0',
            'timestamp' => date('c')
        ]);
    }
    
    // Search endpoint
    elseif (strpos($uri, '/api/search') !== false) {
        require 'search.php';
    }
    
    // Stats endpoint
    elseif (strpos($uri, '/api/stats') !== false) {
        require 'stats.php';
    }
    
    // Random companies endpoint
    elseif (strpos($uri, '/api/random') !== false) {
        require 'random.php';
    }
    
    // 404 - Not found
    else {
        sendError('Route not found', 404);
    }
    
} else {
    sendError('Method not allowed', 405);
}
?>
