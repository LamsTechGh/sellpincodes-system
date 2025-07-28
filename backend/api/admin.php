<?php
/**
 * Admin API Endpoint
 * Handles admin dashboard data and operations
 * QuickCardsGH System
 * By Lamstech Solutions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Checker.php';
require_once __DIR__ . '/../models/PincodeInventory.php';
require_once __DIR__ . '/../models/PurchaseReference.php';
require_once __DIR__ . '/../models/BatchUpload.php';
require_once __DIR__ . '/../utils/SMSHandler.php';
require_once __DIR__ . '/../utils/ExcelUploadHandler.php';

// Simple authentication check (in production, use proper JWT or session management)
function checkAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    // Simple token check (replace with proper authentication)
    if ($authHeader !== 'Bearer admin-token-123') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
}

try {
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($action) {
        case 'dashboard':
            checkAuth();
            getDashboardData();
            break;
            
        case 'transactions':
            checkAuth();
            getTransactions();
            break;
            
        case 'statistics':
            checkAuth();
            getStatistics();
            break;
            
        case 'inventory':
            checkAuth();
            handleInventoryActions($method);
            break;
            
        case 'upload':
            checkAuth();
            handleUploadActions($method);
            break;
            
        case 'pricing':
            checkAuth();
            handlePricingActions($method);
            break;
            
        case 'references':
            checkAuth();
            handleReferenceActions($method);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Admin API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}

function getDashboardData() {
    $transactionModel = new Transaction();
    $checkerModel = new Checker();
    $inventoryModel = new PincodeInventory();
    $referenceModel = new PurchaseReference();
    $batchModel = new BatchUpload();
    $smsHandler = new SMSHandler();
    
    // Get today's date range
    $today = date('Y-m-d');
    $todayStart = $today . ' 00:00:00';
    $todayEnd = $today . ' 23:59:59';
    
    // Get this month's date range
    $monthStart = date('Y-m-01 00:00:00');
    $monthEnd = date('Y-m-t 23:59:59');
    
    // Get statistics
    $todayStats = $transactionModel->getStatistics($todayStart, $todayEnd);
    $monthStats = $transactionModel->getStatistics($monthStart, $monthEnd);
    $allTimeStats = $transactionModel->getStatistics();
    
    // Get inventory statistics
    $inventoryStats = $inventoryModel->getInventoryStats();
    $revenueStats = $inventoryModel->getRevenueStats();
    
    // Get other statistics
    $checkerStats = $checkerModel->getStatistics();
    $referenceStats = $referenceModel->getStatistics();
    $batchStats = $batchModel->getBatchStatistics();
    $smsStats = $smsHandler->getSMSStatistics();
    
    // Get recent data
    $recentTransactions = $transactionModel->getRecentTransactions(10);
    $recentBatches = $batchModel->getRecentBatches(5);
    $lowStockAlerts = $inventoryModel->getLowStockAlerts(10);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'today' => $todayStats,
            'month' => $monthStats,
            'all_time' => $allTimeStats,
            'inventory' => $inventoryStats,
            'revenue' => $revenueStats,
            'checkers' => $checkerStats,
            'references' => $referenceStats,
            'batches' => $batchStats,
            'sms' => $smsStats,
            'recent_transactions' => $recentTransactions,
            'recent_batches' => $recentBatches,
            'low_stock_alerts' => $lowStockAlerts
        ]
    ]);
}

function getTransactions() {
    $transactionModel = new Transaction();
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $status = $_GET['status'] ?? '';
    
    $conditions = [];
    if ($status) {
        $conditions['payment_status'] = $status;
    }
    
    $transactions = $transactionModel->findAll($conditions, 'created_at DESC', $limit);
    $total = $transactionModel->count($conditions);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $transactions,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getStatistics() {
    $transactionModel = new Transaction();
    $inventoryModel = new PincodeInventory();
    
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    
    $transactionStats = $transactionModel->getStatistics($dateFrom, $dateTo);
    $revenueStats = $inventoryModel->getRevenueStats(null, $dateFrom, $dateTo);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $transactionStats,
            'revenue' => $revenueStats
        ]
    ]);
}

function handleInventoryActions($method) {
    $inventoryModel = new PincodeInventory();
    
    switch ($method) {
        case 'GET':
            $serviceTypeId = $_GET['service_type_id'] ?? null;
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 50);
            $status = $_GET['status'] ?? null;
            
            $conditions = [];
            if ($serviceTypeId) {
                $conditions['service_type_id'] = $serviceTypeId;
            }
            if ($status) {
                $conditions['status'] = $status;
            }
            
            $inventory = $inventoryModel->findAll($conditions, 'created_at DESC', $limit);
            $total = $inventoryModel->count($conditions);
            $stats = $inventoryModel->getInventoryStats($serviceTypeId);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'inventory' => $inventory,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                    'stats' => $stats
                ]
            ]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            $status = $input['status'] ?? null;
            $notes = $input['notes'] ?? null;
            
            if (!$id || !$status) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                return;
            }
            
            $result = $inventoryModel->updateStatus($id, $status, $notes);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Status updated successfully' : 'Failed to update status'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
}

function handleUploadActions($method) {
    switch ($method) {
        case 'POST':
            if (!isset($_FILES['excel_file'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                return;
            }
            
            $serviceTypeId = $_POST['service_type_id'] ?? null;
            $examTypeId = $_POST['exam_type_id'] ?? null;
            $uploadedBy = 1; // TODO: Get from session/auth
            
            if (!$serviceTypeId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Service type is required']);
                return;
            }
            
            $uploadHandler = new ExcelUploadHandler();
            $result = $uploadHandler->handleUpload($_FILES['excel_file'], $serviceTypeId, $examTypeId, $uploadedBy);
            
            echo json_encode($result);
            break;
            
        case 'GET':
            $batchModel = new BatchUpload();
            
            if (isset($_GET['batch_id'])) {
                $batch = $batchModel->getBatchById($_GET['batch_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $batch
                ]);
            } else {
                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 20);
                $status = $_GET['status'] ?? null;
                
                $batches = $batchModel->getAllBatches($page, $limit, $status);
                $total = $batchModel->getBatchCount($status);
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'batches' => $batches,
                        'total' => $total,
                        'page' => $page,
                        'limit' => $limit,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $batchId = $input['batch_id'] ?? null;
            
            if (!$batchId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
                return;
            }
            
            $batchModel = new BatchUpload();
            $result = $batchModel->deleteBatch($batchId);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Batch deleted successfully' : 'Failed to delete batch'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
}

function handlePricingActions($method) {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($method) {
        case 'GET':
            try {
                $query = "SELECT * FROM service_types WHERE status = 'active' ORDER BY name";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $services = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => $services
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $serviceId = $input['service_id'] ?? null;
            $adminPrice = $input['admin_price'] ?? null;
            $sellingPrice = $input['selling_price'] ?? null;
            
            if (!$serviceId || !$adminPrice || !$sellingPrice) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                return;
            }
            
            try {
                $query = "UPDATE service_types SET admin_price = :admin_price, selling_price = :selling_price WHERE id = :service_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':admin_price', $adminPrice);
                $stmt->bindParam(':selling_price', $sellingPrice);
                $stmt->bindParam(':service_id', $serviceId);
                
                $result = $stmt->execute();
                
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Pricing updated successfully' : 'Failed to update pricing'
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
}

function handleReferenceActions($method) {
    $referenceModel = new PurchaseReference();
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['reference_code'])) {
                $reference = $referenceModel->findByReference($_GET['reference_code']);
                echo json_encode([
                    'success' => true,
                    'data' => $reference
                ]);
            } else {
                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 20);
                
                $references = $referenceModel->getRecentReferences($limit);
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'references' => $references,
                        'page' => $page,
                        'limit' => $limit
                    ]
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
}
?>
