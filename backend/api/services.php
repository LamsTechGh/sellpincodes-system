<?php
/**
 * Services API Endpoint (Mock Version)
 * Provides service types, exam types, and pricing information
 * QuickCardsGH System
 * By Lamstech Solutions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Mock data for demonstration
    $services = [
        [
            'id' => 1,
            'name' => 'WAEC Results Checker',
            'code' => 'WAEC',
            'description' => 'West African Examinations Council Results Checker',
            'admin_price' => 15.00,
            'selling_price' => 18.00,
            'status' => 'active',
            'exam_types' => [
                ['id' => 1, 'name' => '2024 BECE', 'code' => '2024-BECE', 'year' => '2024'],
                ['id' => 2, 'name' => 'OLD BECE', 'code' => 'OLD-BECE', 'year' => null],
                ['id' => 3, 'name' => '2024 WASSCE', 'code' => '2024-WASSCE', 'year' => '2024'],
                ['id' => 4, 'name' => 'OLD WASSCE', 'code' => 'OLD-WASSCE', 'year' => null],
                ['id' => 5, 'name' => 'SSCE', 'code' => 'SSCE', 'year' => null],
                ['id' => 6, 'name' => 'ABCE', 'code' => 'ABCE', 'year' => null],
                ['id' => 7, 'name' => 'GBCE', 'code' => 'GBCE', 'year' => null]
            ],
            'pricing_tiers' => [
                ['id' => 1, 'min_quantity' => 1, 'unit_price' => 18.00, 'label' => '1 Checker @ Gh¢18 = Gh¢ 18'],
                ['id' => 2, 'min_quantity' => 2, 'unit_price' => 18.00, 'label' => '2 Checkers @ Gh¢18 = Gh¢ 36'],
                ['id' => 3, 'min_quantity' => 3, 'unit_price' => 18.00, 'label' => '3 Checkers @ Gh¢18 = Gh¢ 54'],
                ['id' => 4, 'min_quantity' => 5, 'unit_price' => 16.00, 'label' => '5 Checkers @ Gh¢16 = Gh¢ 80'],
                ['id' => 5, 'min_quantity' => 10, 'unit_price' => 16.00, 'label' => '10 Checkers @ Gh¢16 = Gh¢ 160']
            ]
        ],
        [
            'id' => 2,
            'name' => 'SHS Placement Checker',
            'code' => 'SHS',
            'description' => 'Senior High School Placement Checker',
            'admin_price' => 7.00,
            'selling_price' => 10.00,
            'status' => 'active',
            'exam_types' => [
                ['id' => 8, 'name' => '2023 SHS Placement', 'code' => '2023-SHS', 'year' => '2023']
            ],
            'pricing_tiers' => [
                ['id' => 6, 'min_quantity' => 1, 'unit_price' => 10.00, 'label' => '1 Checker @ Gh¢10 = Gh¢ 10'],
                ['id' => 7, 'min_quantity' => 2, 'unit_price' => 10.00, 'label' => '2 Checkers @ Gh¢10 = Gh¢ 20'],
                ['id' => 8, 'min_quantity' => 5, 'unit_price' => 7.50, 'label' => '5 Checkers @ Gh¢7.5 = Gh¢ 37.5'],
                ['id' => 9, 'min_quantity' => 10, 'unit_price' => 7.50, 'label' => '10 Checkers @ Gh¢7.5 = Gh¢ 75']
            ]
        ],
        [
            'id' => 3,
            'name' => 'UCC Admission Forms',
            'code' => 'UCC',
            'description' => 'University of Cape Coast Admission Forms',
            'admin_price' => 200.00,
            'selling_price' => 250.00,
            'status' => 'active',
            'exam_types' => [],
            'pricing_tiers' => [
                ['id' => 10, 'min_quantity' => 1, 'unit_price' => 231.30, 'label' => 'UCC DISTANCE UNDERGRADUATE = Gh¢225.00 + Gh¢6.3 online charge = Gh¢231.3'],
                ['id' => 11, 'min_quantity' => 1, 'unit_price' => 334.10, 'label' => 'UCC DISTANCE POSTGRADUATE = Gh¢325.00 + Gh¢9.1 online charge = Gh¢334.1']
            ]
        ]
    ];
    
    $momoProviders = [
        ['id' => 1, 'name' => 'MTN', 'code' => 'MTN', 'status' => 'active'],
        ['id' => 2, 'name' => 'AIRTEL', 'code' => 'AIRTEL', 'status' => 'active'],
        ['id' => 3, 'name' => 'TIGO', 'code' => 'TIGO', 'status' => 'active'],
        ['id' => 4, 'name' => 'VODAFONE', 'code' => 'VODAFONE', 'status' => 'active']
    ];
    
    // Response
    echo json_encode([
        'success' => true,
        'data' => [
            'services' => $services,
            'momo_providers' => $momoProviders
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Services API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load services data'
    ]);
}
?>
