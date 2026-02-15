<?php
// This file is included from index.php, so config.php is already loaded

// Get search query from URL parameter
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// If no query provided, return empty results
if (empty($query)) {
    sendJSON([
        'results' => [],
        'count' => 0,
        'query' => ''
    ]);
}

try {
    $pdo = getDatabaseConnection();
    
    // Simplified search query
    $sql = "
        SELECT company_name, website
        FROM companies
        WHERE 
            LOWER(company_name) = LOWER(:query1)
            OR LOWER(company_name) LIKE LOWER(:query2) || '%'
            OR LOWER(company_name) LIKE '%' || LOWER(:query3) || '%'
            OR similarity(company_name, :query4) > 0.3
        ORDER BY 
            CASE 
                WHEN LOWER(company_name) = LOWER(:query5) THEN 1
                WHEN LOWER(company_name) LIKE LOWER(:query6) || '%' THEN 2
                ELSE 3
            END,
            company_name
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':query1' => $query,
        ':query2' => $query,
        ':query3' => $query,
        ':query4' => $query,
        ':query5' => $query,
        ':query6' => $query
    ]);
    
    $results = $stmt->fetchAll();
    
    sendJSON([
        'results' => $results,
        'count' => count($results),
        'query' => $query
    ]);
    
} catch (PDOException $e) {
    sendError('Search failed: ' . $e->getMessage());
}
?>
