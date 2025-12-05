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



if (isset($obj->get_date_collection_report)) {
    $from_date = isset($obj->from_date) ? $obj->from_date : '';
    $to_date = isset($obj->to_date) ? $obj->to_date : '';
    $chit_type_filter = isset($obj->chit_type) ? $obj->chit_type : '';
    $customer_no_filter = isset($obj->customer_no) ? $obj->customer_no : '';
    $payment_status_filter = isset($obj->payment_status) ? $obj->payment_status : '';
    // --- NEW: Check for mandatory customer_no filter ---
    if (empty($customer_no_filter)) {
        $output["head"]["code"] = 400; // Use 400 for a client error/missing parameter
        $output["head"]["msg"] = "Please fill the customer no";
    } else {
        // Only execute the query if customer_no is present
        $query = "SELECT cu.customer_no, cu.customer_name AS name, DATE(cd.due_date) AS collection_date, s.scheme_name AS chit_type, cd.due_number AS due_no, cd.due_amount AS due_amt, cd.paid_amount AS paid_amt, (cd.due_amount - cd.paid_amount) AS balance_amt, cd.status AS payment_status, cd.paid_date AS paid_at,
                    CASE WHEN cd.status = 'pending' AND cd.due_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue,
                    CASE WHEN cd.status = 'pending' AND cd.due_date < CURDATE() THEN (cd.due_amount - cd.paid_amount) ELSE 0 END AS overdue_amount,
                    CASE WHEN cd.status = 'pending' THEN (cd.due_amount - cd.paid_amount) ELSE 0 END AS unpaid_amount
                    FROM chit_dues cd
                    JOIN chits c ON cd.chit_id = c.id
                    JOIN customers cu ON c.customer_id = cu.id
                    JOIN schemes s ON c.scheme_id = s.id
                    WHERE c.deleted_at = 0 AND s.deleted_at = 0";
        $params = [];
        $types = '';
        if (!empty($from_date) && !empty($to_date)) {
            $query .= " AND cd.due_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
            $types .= 'ss';
        }
        if (!empty($chit_type_filter)) {
            $query .= " AND s.scheme_name = ?";
            $params[] = $chit_type_filter;
            $types .= 's';
        }
        // --- MODIFIED: Apply customer_no filter unconditionally since it is now mandatory ---
        $query .= " AND cu.customer_no LIKE ?";
        $params[] = "%$customer_no_filter%";
        $types .= 's';
        if (!empty($payment_status_filter)) {
            $query .= " AND cd.status = ?";
            $params[] = $payment_status_filter;
            $types .= 's';
        }
        $query .= " ORDER BY collection_date ASC, cu.customer_name, cd.due_number";
        $stmt = $conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        $stmt->close();
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Date collection report retrieved successfully";
        $output["data"] = $report;
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
}


echo json_encode($output, JSON_NUMERIC_CHECK);
