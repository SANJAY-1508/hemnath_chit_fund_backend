<?php

include 'db/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Read JSON input
$json = file_get_contents('php://input');
$obj = json_decode($json, true);

// Default output
$output = [];

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// Extract action from input
$action = isset($obj['action']) ? $obj['action'] : null;

// Dashboard API Route
if ($action === 'dashboard') {

    // Total Active Customers
    $query = "SELECT COUNT(*) AS customer_count FROM `customers` WHERE `deleted_at` = 0";
    $customer_count = $conn->query($query)->fetch_assoc()['customer_count'];

    // Overdue Payments
    $query = "SELECT COUNT(*) AS overdue_count 
              FROM `chit_dues` cd 
              JOIN `chits` c ON cd.chit_id = c.id 
              WHERE c.`deleted_at` = 0 
              AND cd.`due_date` < CURDATE() 
              AND cd.`status` = 'pending'";
    $overdue_count = $conn->query($query)->fetch_assoc()['overdue_count'];

    // Today's Dues
    $query = "SELECT COUNT(*) AS current_due_count 
              FROM `chit_dues` cd 
              JOIN `chits` c ON cd.chit_id = c.id 
              WHERE c.`deleted_at` = 0 
              AND cd.`due_date` = CURDATE() 
              AND cd.`status` = 'pending'";
    $current_due_count = $conn->query($query)->fetch_assoc()['current_due_count'];

    // Total Paid
    $query = "SELECT COUNT(*) AS paid_count 
              FROM `chit_dues` cd 
              JOIN `chits` c ON cd.chit_id = c.id 
              WHERE c.`deleted_at` = 0 
              AND cd.`status` = 'paid'";
    $paid_count = $conn->query($query)->fetch_assoc()['paid_count'];

    // Final Output
    $output = [
        "head" => [
            "code" => 200,
            "msg" => "Dashboard data retrieved successfully"
        ],
        "body" => [
            "customer_count" => (int)$customer_count,
            "overdue_count" => (int)$overdue_count,
            "current_due_count" => (int)$current_due_count,
            "paid_count" => (int)$paid_count
        ]
    ];

    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit();
}


// If invalid action
$output = [
    "head" => [
        "code" => 400,
        "msg" => "Parameter Mismatch",
        "inputs" => $obj
    ]
];

echo json_encode($output, JSON_NUMERIC_CHECK);
