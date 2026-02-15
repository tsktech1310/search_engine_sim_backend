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
    
    // Multi-strategy search query for best results
    // Uses exact match, starts with, contains, fuzzy search, and full-text search
    $sql = "
        SELECT DISTINCT company_name, website
        FROM (
            -- Exact match (highest priority)
            SELECT company_name, website, 1 as priority
            FROM companies
            WHERE LOWER(company_name) = LOWER(:query1)
            
            UNION
            
            -- Starts with match
            SELECT company_name, website, 2 as priority
            FROM companies
            WHERE LOWER(company_name) LIKE LOWER(:query2) || '%'
            
            UNION
            
            -- Contains match
            SELECT company_name, website, 3 as priority
            FROM companies
            WHERE LOWER(company_name) LIKE '%' || LOWER(:query3) || '%'
            
            UNION
            
            -- Fuzzy match using trigram similarity
            SELECT company_name, website, 4 as priority
            FROM companies
            WHERE similarity(company_name, :query4) > 0.3
            
            UNION
            
            -- Full text search
            SELECT company_name, website, 5 as priority
            FROM companies
            WHERE tsv @@ plainto_tsquery('english', :query5)
        ) as results
        ORDER BY priority, company_name
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':query1' => $query,
        ':query2' => $query,
        ':query3' => $query,
        ':query4' => $query,
        ':query5' => $query
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
