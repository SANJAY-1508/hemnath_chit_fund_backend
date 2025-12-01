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
} else if (isset($obj['get_monthly_data']) || isset($obj['get_daily_data'])) {

    date_default_timezone_set('Asia/Calcutta');
    $current_date = date('Y-m-d');

    /*-------------------------------------------
        1. DAILY DATA (User selected a month)
    --------------------------------------------*/
    if (isset($obj['get_daily_data']) && isset($obj['month'])) {

        $month = $obj['month'];            // e.g. "2025-11"
        $from_date = $month . "-01";     // Start of month
        $to_date = date("Y-m-t", strtotime($from_date)); // End of month

        $query_daily = "
            SELECT 
                DATE(cd.due_date) AS day,
                SUM(cd.paid_amount) AS Paid,
                SUM(cd.due_amount - cd.paid_amount) AS UnPaid
            FROM chit_dues cd
            JOIN chits c ON cd.chit_id = c.id
            WHERE cd.due_date BETWEEN ? AND ? 
              AND c.deleted_at = 0
            GROUP BY DATE(cd.due_date)
            ORDER BY DATE(cd.due_date) ASC
        ";

        $stmt = $conn->prepare($query_daily);
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $daily_data = [];
        while ($row = $result->fetch_assoc()) {
            $daily_data[$row['day']] = $row;
        }

        $stmt->close();

        // Generate full days for the month
        $full_daily = [];
        $start = new DateTime($from_date);
        $end = new DateTime($to_date);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

        foreach ($period as $date) {
            $day_str = $date->format('Y-m-d');
            $full_daily[] = [
                'day' => $day_str,
                'Paid' => isset($daily_data[$day_str]) ? (float)$daily_data[$day_str]['Paid'] : 0.00,
                'UnPaid' => isset($daily_data[$day_str]) ? (float)$daily_data[$day_str]['UnPaid'] : 0.00
            ];
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Daily data retrieved successfully";
        $output["data"] = $full_daily;
    }
    /*-------------------------------------------
        2. MONTHLY DATA (Full Year: Jan to Dec)
    --------------------------------------------*/ else {

        $year = isset($obj['year']) ? $obj['year'] : date('Y');
        $start_date = $year . '-01-01';
        $end_date = (intval($year) + 1) . '-01-01';

        $query_monthly = "
            SELECT 
                DATE_FORMAT(cd.due_date, '%Y-%m') AS month_key,
                DATE_FORMAT(cd.due_date, '%b') AS name,
                SUM(cd.paid_amount) AS Paid,
                SUM(cd.due_amount - cd.paid_amount) AS UnPaid
            FROM chit_dues cd
            JOIN chits c ON cd.chit_id = c.id
            WHERE cd.due_date >= ? AND cd.due_date < ?
              AND c.deleted_at = 0
            GROUP BY YEAR(cd.due_date), MONTH(cd.due_date)
            ORDER BY YEAR(cd.due_date), MONTH(cd.due_date)
        ";

        $stmt = $conn->prepare($query_monthly);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $monthly_data = [];
        while ($row = $result->fetch_assoc()) {
            $monthly_data[$row['month_key']] = $row;
        }

        $stmt->close();

        // Generate full 12 months for the year
        $full_monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $month_key = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
            $name = date('M', mktime(0, 0, 0, $m, 1, $year));
            $full_monthly[] = [
                'month_key' => $month_key,
                'name' => $name,
                'Paid' => 0.00,
                'UnPaid' => 0.00
            ];
        }

        // Merge queried data into full months
        foreach ($full_monthly as &$item) {
            if (isset($monthly_data[$item['month_key']])) {
                $item['Paid'] = (float)$monthly_data[$item['month_key']]['Paid'];
                $item['UnPaid'] = (float)$monthly_data[$item['month_key']]['UnPaid'];
            }
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Monthly data retrieved successfully";
        $output["data"] = $full_monthly;
    }
} else if (isset($obj['get_chit_distribution'])) {
    // Get all schemes
    $query_schemes = "SELECT id, scheme_name FROM schemes WHERE deleted_at = 0";
    $schemes_result = $conn->query($query_schemes);
    $schemes = [];
    while ($row = $schemes_result->fetch_assoc()) {
        $schemes[] = $row;
    }

    $distribution = [];
    $total_active = 0;
    foreach ($schemes as $scheme) {
        $scheme_id = $scheme['id'];
        $query_count = "SELECT COUNT(*) AS count FROM chits WHERE scheme_id = ? AND deleted_at = 0 AND status = 'active'";
        $stmt = $conn->prepare($query_count);
        $stmt->bind_param("i", $scheme_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)$row['count'];
        $stmt->close();

        if ($count > 0) {
            $total_active += $count;
        }

        $distribution[] = [
            'scheme_name' => $scheme['scheme_name'],
            'count' => $count,
            'percentage' => 0 // temp
        ];
    }

    // Calculate percentages
    if ($total_active > 0) {
        foreach ($distribution as &$item) {
            $item['percentage'] = round(($item['count'] / $total_active) * 100, 2);
        }
    }

    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Chit distribution retrieved successfully";
    $output["data"] = $distribution;
} else if (isset($obj['get_chit_payment_report'])) {
    // Get all schemes
    $query_schemes = "SELECT id, scheme_name FROM schemes WHERE deleted_at = 0";
    $schemes_result = $conn->query($query_schemes);
    $schemes = [];
    while ($row = $schemes_result->fetch_assoc()) {
        $schemes[] = $row;
    }

    $report = [];
    foreach ($schemes as $scheme) {
        $scheme_id = $scheme['id'];

        // Count total past dues
        $query_total = "SELECT COUNT(*) AS total 
                        FROM chit_dues cd 
                        JOIN chits c ON cd.chit_id = c.id 
                        WHERE c.scheme_id = ? AND c.deleted_at = 0 AND cd.due_date < CURDATE()";
        $stmt_total = $conn->prepare($query_total);
        $stmt_total->bind_param("i", $scheme_id);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $row_total = $result_total->fetch_assoc();
        $total = (int)$row_total['total'];
        $stmt_total->close();

        // Count paid past dues
        $query_paid = "SELECT COUNT(*) AS paid 
                       FROM chit_dues cd 
                       JOIN chits c ON cd.chit_id = c.id 
                       WHERE c.scheme_id = ? AND c.deleted_at = 0 AND cd.due_date < CURDATE() AND cd.status = 'paid'";
        $stmt_paid = $conn->prepare($query_paid);
        $stmt_paid->bind_param("i", $scheme_id);
        $stmt_paid->execute();
        $result_paid = $stmt_paid->get_result();
        $row_paid = $result_paid->fetch_assoc();
        $paid = (int)$row_paid['paid'];
        $stmt_paid->close();

        $percentage = ($total > 0) ? round(($paid / $total) * 100, 2) : 0;

        $report[] = [
            'scheme_name' => $scheme['scheme_name'],
            'percentage_paid' => $percentage
        ];
    }

    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Chit payment report retrieved successfully";
    $output["data"] = $report;
} else if (isset($obj['get_recent_paid'])) {
    // Query to get last 5 paid dues, ordered by paid_date DESC
    $query = "SELECT 
                cd.id AS due_id,
                c.chit_id,
                c.chit_no,
                cu.customer_id,
                cu.customer_name AS name,
                cu.customer_no,
                s.scheme_name AS chit_type,
                cd.due_number AS due_no,
                cd.due_amount AS due_amt,
                cd.due_date,
                cd.paid_amount AS paid_amt,
                cd.paid_date AS paid_at,
                cd.status AS payment_status
              FROM `chit_dues` cd
              JOIN `chits` c ON cd.chit_id = c.id
              JOIN `customers` cu ON c.customer_id = cu.id
              JOIN `schemes` s ON c.scheme_id = s.id
              WHERE cd.`status` = 'paid' 
                AND c.`deleted_at` = 0
              ORDER BY cd.`paid_date` DESC 
              LIMIT 10";

    $result = $conn->query($query);

    $recent_paid = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recent_paid[] = $row;
        }
    }

    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Recent paid transactions retrieved successfully";
    $output["data"] = $recent_paid;
} else if (isset($obj['get_collection_report'])) {
    // Get all schemes
    $query_schemes = "SELECT id, scheme_name FROM schemes WHERE deleted_at = 0";
    $schemes_result = $conn->query($query_schemes);
    $schemes = [];
    while ($row = $schemes_result->fetch_assoc()) {
        $schemes[] = $row;
    }

    $report = [];
    foreach ($schemes as $scheme) {
        $scheme_id = $scheme['id'];

        // Sum collected amount (paid_amount for paid dues)
        $query_collected = "SELECT SUM(cd.paid_amount) AS collected 
                            FROM chit_dues cd 
                            JOIN chits c ON cd.chit_id = c.id 
                            WHERE c.scheme_id = ? AND c.deleted_at = 0 AND cd.status = 'paid'";
        $stmt_collected = $conn->prepare($query_collected);
        $stmt_collected->bind_param("i", $scheme_id);
        $stmt_collected->execute();
        $result_collected = $stmt_collected->get_result();
        $row_collected = $result_collected->fetch_assoc();
        $collected = (float)($row_collected['collected'] ?? 0);
        $stmt_collected->close();

        // Sum unpaid amount (remaining balance for pending dues)
        $query_unpaid = "SELECT SUM(cd.due_amount - cd.paid_amount) AS unpaid 
                         FROM chit_dues cd 
                         JOIN chits c ON cd.chit_id = c.id 
                         WHERE c.scheme_id = ? AND c.deleted_at = 0 AND cd.status = 'pending'";
        $stmt_unpaid = $conn->prepare($query_unpaid);
        $stmt_unpaid->bind_param("i", $scheme_id);
        $stmt_unpaid->execute();
        $result_unpaid = $stmt_unpaid->get_result();
        $row_unpaid = $result_unpaid->fetch_assoc();
        $unpaid = (float)($row_unpaid['unpaid'] ?? 0);
        $stmt_unpaid->close();

        $report[] = [
            'scheme_name' => $scheme['scheme_name'],
            'collected' => $collected,
            'unpaid' => $unpaid
        ];
    }

    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Collection report retrieved successfully";
    $output["data"] = $report;
} else if (isset($obj['get_customer_collection_report'])) {
    $customer_no = isset($obj->customer_no) ? $obj->customer_no : '';
    $payment_status = isset($obj->payment_status) ? $obj->payment_status : '';

    $query = "SELECT cu.id AS customer_id, cu.customer_id, cu.customer_name, cu.customer_no, s.scheme_name AS chit_type,
              SUM(CASE WHEN cd.status = 'paid' THEN cd.paid_amount ELSE 0 END) AS total_paid,
              SUM(CASE WHEN cd.status = 'pending' THEN (cd.due_amount - cd.paid_amount) ELSE 0 END) AS total_unpaid,
              SUM(CASE WHEN cd.status = 'pending' AND cd.due_date < CURDATE() THEN (cd.due_amount - cd.paid_amount) ELSE 0 END) AS total_overdue,
              COUNT(cd.id) AS total_installments
              FROM customers cu
              JOIN chits c ON cu.id = c.customer_id
              JOIN schemes s ON c.scheme_id = s.id
              JOIN chit_dues cd ON cd.chit_id = c.id
              WHERE c.deleted_at = 0 AND s.deleted_at = 0";

    $params = [];
    $types = '';
    if (!empty($customer_no)) {
        $query .= " AND cu.customer_no LIKE ?";
        $params[] = "%$customer_no%";
        $types .= 's';
    }
    if (!empty($payment_status)) {
        $query .= " AND cd.status = ?";
        $params[] = $payment_status;
        $types .= 's';
    }

    $query .= " GROUP BY cu.id, cu.customer_id, cu.customer_name, cu.customer_no, s.id, s.scheme_name
                ORDER BY cu.customer_name";

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

    $output = [
        'head' => ['code' => 200, 'msg' => 'Customer collection report retrieved successfully'],
        'body' => ['report' => $report]
    ];
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
}



echo json_encode($output, JSON_NUMERIC_CHECK);
