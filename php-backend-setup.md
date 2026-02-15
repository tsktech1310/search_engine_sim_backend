# PHP Backend Setup Guide - Google Search Engine Clone

## 🚀 Complete PHP Backend Implementation

This guide provides a **PHP backend** alternative to the Node.js version, using the same PostgreSQL database on Supabase.

---

## Backend Files Structure

```
backend/
├── index.php              ← Main API entry point
├── config.php             ← Database configuration
├── search.php             ← Search endpoint
├── stats.php              ← Statistics endpoint
├── composer.json          ← PHP dependencies
└── .htaccess              ← Apache/Nginx routing
```

---

## Part 1: PHP Backend Files

### File 1: `composer.json`

```json
{
    "name": "business-search/api",
    "description": "Business Search API in PHP",
    "type": "project",
    "require": {
        "php": ">=8.0",
        "ext-pdo": "*",
        "ext-pgsql": "*"
    },
    "autoload": {
        "files": ["config.php"]
    }
}
```

---

### File 2: `config.php`

```php
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
$DATABASE_URL = getenv('DATABASE_URL') ?: 'postgresql://postgres:[YOUR-PASSWORD]@db.xxxxx.supabase.co:5432/postgres';

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
```

---

### File 3: `index.php`

```php
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
if ($request_method === 'GET') {
    
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
```

---

### File 4: `search.php`

```php
<?php
// Don't include config.php again (already included in index.php)

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
```

---

### File 5: `stats.php`

```php
<?php
// Don't include config.php again (already included in index.php)

try {
    $pdo = getDatabaseConnection();
    
    // Get total count of companies
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
```

---

### File 6: `random.php`

```php
<?php
// Don't include config.php again (already included in index.php)

// Get limit from query parameter (default: 5)
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

// Ensure limit is between 1 and 20
$limit = max(1, min(20, $limit));

try {
    $pdo = getDatabaseConnection();
    
    $sql = "
        SELECT company_name, website
        FROM companies
        ORDER BY RANDOM()
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll();
    
    sendJSON([
        'results' => $results,
        'count' => count($results)
    ]);
    
} catch (PDOException $e) {
    sendError('Failed to fetch random companies: ' . $e->getMessage());
}
?>
```

---

### File 7: `.htaccess` (for Apache)

```apache
# Enable rewrite engine
RewriteEngine On

# Remove .php extension
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^([^\.]+)$ $1.php [NC,L]

# Route everything through index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Enable CORS
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type"
</IfModule>

# PHP settings
<IfModule mod_php.c>
    php_value display_errors 1
    php_value error_reporting E_ALL
</IfModule>
```

---

### File 8: `nginx.conf` (for Nginx - optional)

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html;
    index index.php;

    # Enable CORS
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
    add_header Access-Control-Allow-Headers "Content-Type";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## Part 2: Deploy PHP Backend to Render

### Option A: Deploy from GitHub (Recommended)

#### Step 1: Create GitHub Repository
1. Go to **https://github.com**
2. Create new repository: `business-search-php-backend`
3. Upload all PHP files:
   - `index.php`
   - `config.php`
   - `search.php`
   - `stats.php`
   - `random.php`
   - `composer.json`
   - `.htaccess`

#### Step 2: Deploy on Render
1. Go to **https://render.com**
2. Click **New +** → **Web Service**
3. Connect your GitHub repository
4. Configure:
   - **Name**: `business-search-php-api`
   - **Region**: Choose closest
   - **Branch**: `main`
   - **Runtime**: **Docker** (or PHP if available)
   - **Build Command**: `composer install`
   - **Start Command**: `php -S 0.0.0.0:$PORT`

#### Step 3: Add Environment Variable
1. Scroll to **Environment Variables**
2. Add:
   - **Key**: `DATABASE_URL`
   - **Value**: Your Supabase connection string
3. Click **Create Web Service**

---

### Option B: Deploy to Traditional PHP Hosting

#### Popular PHP Hosts (Free Tiers Available):

**1. InfinityFree**
- Website: https://infinityfree.net
- Free tier: Unlimited bandwidth
- PHP 8.x support
- Steps:
  1. Sign up and create account
  2. Create new website
  3. Upload files via FTP or File Manager
  4. Edit `config.php` with your DATABASE_URL
  5. Done!

**2. 000WebHost**
- Website: https://www.000webhost.com
- Free tier: 300MB storage, 3GB bandwidth
- PHP 8.x support
- No ads on free tier

**3. Vercel (with PHP runtime)**
- Website: https://vercel.com
- Create `vercel.json`:
```json
{
  "functions": {
    "api/*.php": {
      "runtime": "vercel-php@0.6.0"
    }
  }
}
```

---

## Part 3: Local Testing

### Test Locally Before Deploying

#### Requirements:
- PHP 8.0 or higher
- PostgreSQL PDO extension

#### Installation:

**Windows:**
```bash
# Install XAMPP or download PHP from php.net
# Enable pgsql extension in php.ini:
extension=pdo_pgsql
extension=pgsql
```

**Mac:**
```bash
brew install php
brew install php-pgsql
```

**Linux:**
```bash
sudo apt update
sudo apt install php8.1 php8.1-pgsql php8.1-cli php8.1-mbstring
```

#### Run Local Server:

```bash
# Navigate to your backend folder
cd backend/

# Edit config.php and add your DATABASE_URL

# Start PHP built-in server
php -S localhost:8000

# Test in browser:
# http://localhost:8000/
# http://localhost:8000/api/search?q=google
# http://localhost:8000/api/stats
```

---

## Part 4: Testing Your API

### Test Endpoints:

```bash
# Health check
curl https://your-php-api.onrender.com/

# Search for companies
curl "https://your-php-api.onrender.com/api/search?q=google"

# Get statistics
curl https://your-php-api.onrender.com/api/stats

# Get random companies
curl "https://your-php-api.onrender.com/api/random?limit=5"
```

### Expected Responses:

**Health Check (`/`):**
```json
{
  "status": "ok",
  "message": "Business Search API (PHP)",
  "version": "1.0.0",
  "timestamp": "2026-02-15T21:30:00+00:00"
}
```

**Search (`/api/search?q=google`):**
```json
{
  "results": [
    {
      "company_name": "Google Inc.",
      "website": "https://google.com"
    }
  ],
  "count": 1,
  "query": "google"
}
```

**Stats (`/api/stats`):**
```json
{
  "total_companies": 64000,
  "database": "connected",
  "timestamp": "2026-02-15T21:30:00+00:00"
}
```

---

## Part 5: Update Frontend

In your `index.html`, update the API URL:

```javascript
// Change from:
const API_URL = 'YOUR_RENDER_BACKEND_URL';

// To your PHP backend URL:
const API_URL = 'https://business-search-php-api.onrender.com';
```

---

## Part 6: Troubleshooting

### Common Issues:

**1. "Database connection failed"**
- Check DATABASE_URL is correct
- Ensure PostgreSQL PDO extension is enabled
- Verify Supabase allows your server IP

**2. "Call to undefined function pg_connect"**
```bash
# Install PostgreSQL extension
sudo apt install php-pgsql
# Or enable in php.ini:
extension=pdo_pgsql
```

**3. "CORS errors in browser"**
- Verify .htaccess is present
- Check CORS headers in config.php
- For Nginx, use nginx.conf example

**4. "404 Not Found"**
- Check .htaccess rewrite rules
- Ensure mod_rewrite is enabled on Apache
- Try accessing with /index.php/api/search

**5. "No results found" but data exists**
- Test direct database connection
- Check if pg_trgm extension is enabled:
```sql
SELECT * FROM pg_extension WHERE extname = 'pg_trgm';
```

---

## Part 7: Performance Optimization

### Enable OPcache (Production):

Add to `php.ini` or `.user.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

### Connection Pooling:

For high traffic, use persistent connections:
```php
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_PERSISTENT => true,
    // ... other options
]);
```

---

## Comparison: PHP vs Node.js

| Feature | PHP Backend | Node.js Backend |
|---------|-------------|-----------------|
| **Setup** | Simpler, fewer dependencies | Requires npm packages |
| **Hosting** | More free hosts available | Limited free options |
| **Performance** | Good (8.x+) | Slightly faster |
| **Memory** | Lower usage | Higher usage |
| **Learning Curve** | Easier for beginners | Requires JS knowledge |
| **Ecosystem** | Mature, stable | Modern, growing |

---

## Cost Comparison

**Free PHP Hosting Options:**
- ✅ InfinityFree: Unlimited (with limits on CPU)
- ✅ 000WebHost: 300MB storage
- ✅ Render: 750 hours/month
- ✅ Vercel: Good for serverless PHP

**Total Cost: $0/month**

---

## Next Steps

1. ✅ Create all PHP files
2. ✅ Test locally (optional)
3. ✅ Upload to GitHub
4. ✅ Deploy to Render or PHP host
5. ✅ Update frontend API_URL
6. ✅ Test search functionality
7. 🎉 Launch!

---

## Additional Resources

- **PHP Documentation**: https://www.php.net/docs.php
- **PDO Tutorial**: https://www.php.net/manual/en/book.pdo.php
- **PostgreSQL PHP**: https://www.php.net/manual/en/book.pgsql.php
- **Composer**: https://getcomposer.org/

---

## Support

If you need help:
1. Check PHP error logs
2. Enable `display_errors` in config.php
3. Test database connection separately
4. Verify all extensions are installed

Good luck with your PHP backend! 🚀