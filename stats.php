<?php
// This file is included from index.php, so config.php is already loaded

try {
    $pdo = getDatabaseConnection();
    
    // Get total count of companies in database
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM companies");
    $result = $stmt->fetch();
    
    sendJSON([
        'total_companies' => (int)$result['total'],
        'database' => 'connected',
        'timestamp' => date('c')
    ]);
    
} catch (PDOException $e) {
    sendError('Failed to fetch stats: ' . $e->getMessage());
}
?>
