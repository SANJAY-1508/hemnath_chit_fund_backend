<?php

include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

if (isset($obj->list_history)) {
    $customer_id = isset($obj->customer_id) ? $obj->customer_id : null;
    $customer_no = isset($obj->customer_no) ? $obj->customer_no : null;

    $sql = "SELECT `id`, `customer_id`, `customer_no`, `action_type`, `old_value`, `new_value`, `remarks`, `created_at` FROM `customer_history` WHERE 1";
    $params = [];
    $types = "";

    if (!empty($customer_id)) {
        $sql .= " AND `customer_id` = ?";
        $params[] = $customer_id;
        $types .= "s";
    }
    if (!empty($customer_no)) {
        $sql .= " AND `customer_no` = ?";
        $params[] = $customer_no;
        $types .= "s";
    }

    $sql .= " ORDER BY `created_at` DESC";
    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];

    foreach ($history as &$record) {
        try {
            $record['old_value'] = $record['old_value'] ? json_decode($record['old_value'], true) : null;
            $record['new_value'] = $record['new_value'] ? json_decode($record['new_value'], true) : null;
        } catch (Exception $e) {
            $record['old_value'] = null;
            $record['new_value'] = null;
        }
    }

    $output["body"]["history"] = $history;
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = $result->num_rows > 0 ? "Success" : "No History Found";
    $stmt->close();
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
}

echo json_encode($output, JSON_NUMERIC_CHECK);
