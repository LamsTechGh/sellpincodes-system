<?php
/**
 * API Router
 * Main entry point for API requests
 * Sellpincodes System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the requested endpoint
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove 'backend/api' from path if present
$endpoint = end($pathParts);

// Route to appropriate endpoint
switch ($endpoint) {
    case 'purchase':
        require_once __DIR__ . '/purchase.php';
        break;
        
    case 'retrieve':
        require_once __DIR__ . '/retrieve.php';
        break;
        
    case 'services':
        require_once __DIR__ . '/services.php';
        break;
        
    case 'admin':
        require_once __DIR__ . '/admin.php';
        break;
        
    default:
        // API documentation/info
        echo json_encode([
            'success' => true,
            'message' => 'Sellpincodes API v1.0',
            'endpoints' => [
                'GET /services' => 'Get available services and pricing',
                'POST /purchase' => 'Purchase checkers',
                'POST /retrieve' => 'Retrieve old checkers',
                'GET /admin' => 'Admin dashboard (requires authentication)'
            ],
            'version' => '1.0.0',
            'timestamp' => date('c')
        ]);
}
?>
