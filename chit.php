<?php
include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// Validate Action
if (!isset($obj->action)) {
    echo json_encode(['head' => ['code' => 400, 'msg' => 'Action parameter is missing']]);
    exit();
}

$action = $obj->action;

/* ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ==
1️⃣  CREATE CHIT
===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  == */
if ($action === 'create_chit') {
    $required_fields = ['customer_id', 'scheme_id'];
    // customer_id like 'CUST00001', scheme_id like 'SCH00001'
    foreach ($required_fields as $field) {
        if (!isset($obj->$field) || trim($obj->$field) === '') {
            echo json_encode(['head' => ['code' => 400, 'msg' => "Missing field: $field"]]);
            exit();
        }
    }

    $customer_id_str = trim($obj->customer_id);
    $scheme_id_str = trim($obj->scheme_id);
    $start_date = isset($obj->start_date) ? trim($obj->start_date) : date('Y-m-d');

    // Fetch internal customer_id
    $stmtCust = $conn->prepare('SELECT `id`, `customer_no` FROM `customers` WHERE `customer_id` = ? AND `deleted_at` = 0');
    $stmtCust->bind_param('s', $customer_id_str);
    $stmtCust->execute();
    $resultCust = $stmtCust->get_result();
    if ($resultCust->num_rows === 0) {
        echo json_encode(['head' => ['code' => 404, 'msg' => 'Customer not found']]);
        exit();
    }
    $customer = $resultCust->fetch_assoc();
    $customer_id = $customer['id'];
    $customer_no = $customer['customer_no'];

    // Fetch internal scheme_id and details
    $stmtScheme = $conn->prepare('SELECT `id`, `duration`, `schemet_due_amount`, `duration_unit` FROM `schemes` WHERE `scheme_id` = ? AND `deleted_at` = 0');
    $stmtScheme->bind_param('s', $scheme_id_str);
    $stmtScheme->execute();
    $resultScheme = $stmtScheme->get_result();
    if ($resultScheme->num_rows === 0) {
        echo json_encode(['head' => ['code' => 404, 'msg' => 'Scheme not found']]);
        exit();
    }
    $scheme = $resultScheme->fetch_assoc();
    $scheme_id = $scheme['id'];
    $total_dues = $scheme['duration'];
    $due_amount = $scheme['schemet_due_amount'];
    $duration_unit = $scheme['duration_unit'];

    // Insert chit
    $pending_count = $total_dues;
    $stmtInsert = $conn->prepare("INSERT INTO `chits` 
        (`customer_id`, `scheme_id`, `start_date`, `total_dues`, `paid_count`, `pending_count`, `created_at`, `deleted_at`)
        VALUES (?, ?, ?, ?, 0, ?, NOW(), 0)");
    $stmtInsert->bind_param('iisis', $customer_id, $scheme_id, $start_date, $total_dues, $pending_count);

    if ($stmtInsert->execute()) {
        $insertId = $stmtInsert->insert_id;
        $chit_id = 'CHIT' . str_pad($insertId, 5, '0', STR_PAD_LEFT);
        $chit_no = 'CT' . date('ymd') . $insertId;

        // Update IDs
        $stmtUpdate = $conn->prepare('UPDATE `chits` SET `chit_id` = ?, `chit_no` = ? WHERE `id` = ?');
        $stmtUpdate->bind_param('ssi', $chit_id, $chit_no, $insertId);
        $stmtUpdate->execute();

        // Generate dues
        for ($i = 1; $i <= $total_dues; $i++) {
            $due_date_obj = new DateTime($start_date);
            $interval = ($duration_unit === 'month') ? new DateInterval('P' . ($i - 1) . 'M') : new DateInterval('P' . (($i - 1) * 7) . 'D');
            $due_date_obj->add($interval);
            $due_date = $due_date_obj->format('Y-m-d');

            $stmtDue = $conn->prepare("INSERT INTO `chit_dues` 
                (`chit_id`, `due_number`, `due_date`, `due_amount`, `created_at`)
                VALUES (?, ?, ?, ?, NOW())");
            $stmtDue->bind_param('iisd', $insertId, $i, $due_date, $due_amount);
            $stmtDue->execute();
            $stmtDue->close();
        }

        // Fetch chit details for logging
        $stmtChitGet = $conn->prepare("SELECT * FROM `chits` WHERE `id` = ?");
        $stmtChitGet->bind_param('i', $insertId);
        $stmtChitGet->execute();
        $chitResult = $stmtChitGet->get_result();
        $newChit = $chitResult->fetch_assoc();
        $stmtChitGet->close();

        // Prepare created_by values
        $created_by_id = isset($obj->created_by_id) ? trim($obj->created_by_id) : null;
        $created_by_name = isset($obj->created_by_name) ? trim($obj->created_by_name) : null;

        // Set remarks based on source
        if ($created_by_id && $created_by_name) {
            $remarks = "Chit created by $created_by_name";
        } else {
            $remarks = "Chit created";
        }

        // Log customer history
        logCustomerHistory($customer_id_str, $customer_no, 'chit_created', null, $newChit, $remarks, $created_by_id, $created_by_name);

        $output = ['head' => ['code' => 200, 'msg' => 'Chit Created Successfully'], 'body' => ['chit_id' => $chit_id, 'chit_no' => $chit_no]];
    } else {
        $output = ['head' => ['code' => 400, 'msg' => 'Failed to create chit']];
    }
    $stmtInsert->close();

    echo json_encode($output);
    exit();
}

/* ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ==
2️⃣  LIST CHITS ( FOR A CUSTOMER )
===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  == */ elseif ($action === 'list_chits') {

    $stmt = $conn->prepare("
        SELECT 
            c.*, 
            s.scheme_name, 
            s.schemet_due_amount, 
            s.scheme_maturtiy_amount,
            cust.customer_name
        FROM `chits` c
        JOIN `schemes` s ON c.scheme_id = s.id
        JOIN `customers` cust ON cust.id = c.customer_id
        WHERE c.deleted_at = 0
        ORDER BY c.id DESC
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    $chits = [];
    while ($row = $result->fetch_assoc()) {
        $chits[] = $row;
    }

    $output = [
        "head" => [
            "code" => 200,
            "msg"  => "All Chit List Retrieved"
        ],
        "body" => [
            "chits" => $chits
        ]
    ];

    echo json_encode($output);
    exit();
}

// elseif ( $action === 'list_chits' && isset( $obj->customer_id ) ) {
//     $customer_id_str = trim( $obj->customer_id );

//     // Fetch internal customer_id
//     $stmtCust = $conn->prepare( 'SELECT `id` FROM `customers` WHERE `customer_id` = ? AND `deleted_at` = 0' );
//     $stmtCust->bind_param( 's', $customer_id_str );
//     $stmtCust->execute();
//     $resultCust = $stmtCust->get_result();
//     if ( $resultCust->num_rows === 0 ) {
//         echo json_encode( [ 'head' => [ 'code' => 404, 'msg' => 'Customer not found' ] ] );
//         exit();
//     }
//     $customer = $resultCust->fetch_assoc();
//     $customer_id = $customer[ 'id' ];

//     $stmt = $conn->prepare( "SELECT c.*, s.scheme_name, s.schemet_due_amount, s.scheme_maturtiy_amount 
//                             FROM `chits` c 
//                             JOIN `schemes` s ON c.scheme_id = s.id 
//                             WHERE c.customer_id = ? AND c.deleted_at = 0 
//                             ORDER BY c.id DESC" );
//     $stmt->bind_param( 'i', $customer_id );
//     $stmt->execute();
//     $result = $stmt->get_result();

//     $chits = [];
//     while ( $row = $result->fetch_assoc() ) {
//         $chits[] = $row;
//     }

//     $output = [
//         'head' => [ 'code' => 200, 'msg' => 'Chit List Retrieved' ],
//         'body' => [ 'chits' => $chits ]
// ];

//     echo json_encode( $output );
//     exit();
// }

/* ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ==
3️⃣  GET CHIT DETAILS ( INCLUDING DUES, PAID/PENDING )
===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  == */ elseif ($action === 'get_chit_details' && isset($obj->chit_id)) {
    $chit_id_str = trim($obj->chit_id);

    // Fetch internal chit_id
    $stmtChit = $conn->prepare("SELECT c.*, s.scheme_name, s.schemet_due_amount, s.scheme_maturtiy_amount 
                                FROM `chits` c 
                                JOIN `schemes` s ON c.scheme_id = s.id 
                                WHERE c.chit_id = ? AND c.deleted_at = 0");
    $stmtChit->bind_param('s', $chit_id_str);
    $stmtChit->execute();
    $resultChit = $stmtChit->get_result();
    if ($resultChit->num_rows === 0) {
        echo json_encode(['head' => ['code' => 404, 'msg' => 'Chit not found']]);
        exit();
    }
    $chit = $resultChit->fetch_assoc();
    $chit_internal_id = $chit['id'];

    // Fetch dues
    $stmtDues = $conn->prepare('SELECT * FROM `chit_dues` WHERE `chit_id` = ? ORDER BY `due_number` ASC');
    $stmtDues->bind_param('i', $chit_internal_id);
    $stmtDues->execute();
    $resultDues = $stmtDues->get_result();

    $dues = [];
    while ($row = $resultDues->fetch_assoc()) {
        $dues[] = $row;
    }

    $output = [
        'head' => ['code' => 200, 'msg' => 'Chit Details Retrieved'],
        'body' => ['chit' => $chit, 'dues' => $dues]
    ];

    echo json_encode($output);
    exit();
}

/* ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ==
4️⃣  PAY DUE ( PARTIAL OR FULL )
===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  == */ elseif ($action === 'pay_due' && isset($obj->due_id) && isset($obj->amount)) {
    $due_id = intval($obj->due_id);
    $amount = floatval($obj->amount);

    if ($amount <= 0) {
        echo json_encode(['head' => ['code' => 400, 'msg' => 'Invalid payment amount']]);
        exit();
    }

    // Begin transaction for atomicity
    $conn->begin_transaction();

    try {
        // Lock and fetch due + chit details (include customer for logging)
        $stmtDue = $conn->prepare("
            SELECT 
                d.*, 
                c.id AS chit_id, 
                c.chit_id AS chit_no,
                c.paid_count, 
                c.pending_count, 
                c.total_paid_amount, 
                c.total_dues,
                c.status AS chit_status,
                cu.customer_id AS customer_id_str,
                cu.customer_no
            FROM `chit_dues` d 
            JOIN `chits` c ON d.chit_id = c.id 
            JOIN `customers` cu ON c.customer_id = cu.id
            WHERE d.id = ? AND c.deleted_at = 0 AND cu.deleted_at = 0
            FOR UPDATE
        ");
        $stmtDue->bind_param('i', $due_id);
        $stmtDue->execute();
        $resultDue = $stmtDue->get_result();

        if ($resultDue->num_rows === 0) {
            throw new Exception('Due not found or chit deleted', 404);
        }

        $due = $resultDue->fetch_assoc();
        $chit_id = $due['chit_id'];
        $customer_id_str = $due['customer_id_str'];
        $customer_no = $due['customer_no'];

        if ($due['status'] === 'paid') {
            throw new Exception('Due already paid', 400);
        }

        $oldDue = $due; // For logging

        $remaining = $due['due_amount'] - $due['paid_amount'];
        if ($amount > $remaining) {
            $amount = $remaining;
            // Cap at due amount
        }

        $new_paid_amount = $due['paid_amount'] + $amount;
        $was_pending = ($due['status'] === 'pending');
        $now_paid = ($new_paid_amount >= $due['due_amount']);

        $new_status = $now_paid ? 'paid' : 'pending';
        $paid_date = $now_paid ? $timestamp : null;

        // Update chit_due
        $stmtUpdateDue = $conn->prepare("
            UPDATE `chit_dues` 
            SET `paid_amount` = ?, `status` = ?, `paid_date` = ?, `updated_at` = NOW() 
            WHERE `id` = ?
        ");
        $stmtUpdateDue->bind_param('dssi', $new_paid_amount, $new_status, $paid_date, $due_id);
        $stmtUpdateDue->execute();

        // Update chit counters
        $new_total_paid = $due['total_paid_amount'] + $amount;
        $new_paid_count = $due['paid_count'];
        $new_pending_count = $due['pending_count'];

        // Only increment paid_count if this due just became fully paid
        if ($now_paid && $was_pending) {
            $new_paid_count++;
            $new_pending_count--;
        }

        // Update chit status
        $new_chit_status = $due['chit_status'];
        if ($new_paid_count >= $due['total_dues']) {
            $new_chit_status = 'completed';
        } elseif ($new_chit_status !== 'foreclosed') {
            $new_chit_status = 'active';
        }

        $stmtUpdateChit = $conn->prepare("
            UPDATE `chits` 
            SET 
                `paid_count` = ?, 
                `pending_count` = ?, 
                `total_paid_amount` = ?, 
                `status` = ?, 
                `updated_at` = NOW() 
            WHERE `id` = ?
        ");
        $stmtUpdateChit->bind_param('iidsi', $new_paid_count, $new_pending_count, $new_total_paid, $new_chit_status, $chit_id);
        $stmtUpdateChit->execute();

        $conn->commit();

        // Fetch updated due for logging
        $stmtUpdatedDue = $conn->prepare("SELECT * FROM `chit_dues` WHERE `id` = ?");
        $stmtUpdatedDue->bind_param('i', $due_id);
        $stmtUpdatedDue->execute();
        $updatedDueResult = $stmtUpdatedDue->get_result();
        $newDue = $updatedDueResult->fetch_assoc();
        $stmtUpdatedDue->close();

        // Prepare old and new values for logging (only due relevant info)
        $oldValue = [
            'due_id' => $oldDue['id'],
            'due_number' => $oldDue['due_number'],
            'old_paid_amount' => $oldDue['paid_amount'],
            'old_status' => $oldDue['status']
        ];

        $newValue = [
            'due_id' => $newDue['id'],
            'due_number' => $newDue['due_number'],
            'new_paid_amount' => $newDue['paid_amount'],
            'new_status' => $newDue['status'],
            'amount_paid' => $amount
        ];

        // Prepare created_by values
        $created_by_id = isset($obj->created_by_id) ? trim($obj->created_by_id) : null;
        $created_by_name = isset($obj->created_by_name) ? trim($obj->created_by_name) : null;

        // Set remarks based on source
        if ($created_by_id && $created_by_name) {
            $remarks = "Due payment processed by $created_by_name";
        } else {
            $remarks = "Due payment processed";
        }

        // Log customer history
        logCustomerHistory($customer_id_str, $customer_no, 'due_paid', $oldValue, $newValue, $remarks, $created_by_id, $created_by_name);

        $output = [
            'head' => ['code' => 200, 'msg' => 'Payment Processed Successfully'],
            'body' => [
                'due_id' => $due_id,
                'amount_paid' => $amount,
                'new_paid_amount' => $new_paid_amount,
                'status' => $new_status,
                'chit_paid_count' => $new_paid_count,
                'chit_pending_count' => $new_pending_count
            ]
        ];
    } catch (Exception $e) {
        $conn->rollback();
        $code = $e->getCode() ?: 400;
        $msg = $e->getMessage();
        $output = ['head' => ['code' => $code, 'msg' => $msg]];
    }

    echo json_encode($output);
    exit();
}

/* ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ==
5️⃣  FORECLOSE CHIT
===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  == */ elseif ($action === 'foreclose_chit' && isset($obj->chit_id)) {
    $chit_id_str = trim($obj->chit_id);

    // Fetch chit (include customer for logging)
    $stmtChit = $conn->prepare('SELECT c.*, cu.customer_id AS customer_id_str, cu.customer_no FROM `chits` c JOIN `customers` cu ON c.customer_id = cu.id WHERE c.`chit_id` = ? AND c.`deleted_at` = 0 AND cu.`deleted_at` = 0');
    $stmtChit->bind_param('s', $chit_id_str);
    $stmtChit->execute();
    $resultChit = $stmtChit->get_result();
    if ($resultChit->num_rows === 0) {
        echo json_encode(['head' => ['code' => 404, 'msg' => 'Chit not found']]);
        exit();
    }
    $oldChit = $resultChit->fetch_assoc();
    $chit_internal_id = $oldChit['id'];
    $customer_id_str = $oldChit['customer_id_str'];
    $customer_no = $oldChit['customer_no'];

    if ($oldChit['status'] !== 'active') {
        echo json_encode(['head' => ['code' => 400, 'msg' => 'Chit is closed']]);
        exit();
    }
    if ($oldChit['paid_count'] < 3) {
        echo json_encode(['head' => ['code' => 400, 'msg' => 'Minimum 3 dues must be paid for foreclosure']]);
        exit();
    }

    // Update status
    $stmtUpdate = $conn->prepare("UPDATE `chits` SET `status` = 'foreclosed', `updated_at` = NOW() WHERE `id` = ?");
    $stmtUpdate->bind_param('i', $chit_internal_id);
    $stmtUpdate->execute();

    // Fetch updated chit for logging
    $stmtUpdatedChit = $conn->prepare("SELECT * FROM `chits` WHERE `id` = ?");
    $stmtUpdatedChit->bind_param('i', $chit_internal_id);
    $stmtUpdatedChit->execute();
    $updatedChitResult = $stmtUpdatedChit->get_result();
    $newChit = $updatedChitResult->fetch_assoc();
    $stmtUpdatedChit->close();

    // Prepare created_by values
    $created_by_id = isset($obj->created_by_id) ? trim($obj->created_by_id) : null;
    $created_by_name = isset($obj->created_by_name) ? trim($obj->created_by_name) : null;

    // Set remarks based on source
    if ($created_by_id && $created_by_name) {
        $remarks = "Chit foreclosed by $created_by_name";
    } else {
        $remarks = "Chit foreclosed";
    }

    // Log customer history
    logCustomerHistory($customer_id_str, $customer_no, 'chit_foreclosed', $oldChit, $newChit, $remarks, $created_by_id, $created_by_name);

    $output = ['head' => ['code' => 200, 'msg' => 'Chit Foreclosed Successfully']];

    echo json_encode($output);
    exit();
}

/* ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ==
6️⃣  GET ALL DUES FOR CUSTOMER ( ACROSS ALL CHITS )
===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  == */ elseif ($action === 'get_customer_dues' && isset($obj->customer_id)) {
    $customer_id_str = trim($obj->customer_id);

    // Fetch internal customer_id
    $stmtCust = $conn->prepare('SELECT `id` FROM `customers` WHERE `customer_id` = ? AND `deleted_at` = 0');
    $stmtCust->bind_param('s', $customer_id_str);
    $stmtCust->execute();
    $resultCust = $stmtCust->get_result();
    if ($resultCust->num_rows === 0) {
        echo json_encode(['head' => ['code' => 404, 'msg' => 'Customer not found']]);
        exit();
    }
    $customer = $resultCust->fetch_assoc();
    $customer_id = $customer['id'];

    // Fetch all dues with chit details
    $stmt = $conn->prepare("SELECT cd.*, c.chit_id, c.chit_no, s.scheme_name 
                            FROM `chit_dues` cd 
                            JOIN `chits` c ON cd.chit_id = c.id 
                            JOIN `schemes` s ON c.scheme_id = s.id 
                            WHERE c.customer_id = ? AND c.deleted_at = 0 
                            ORDER BY c.chit_id, cd.due_number ASC");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $dues = [];
    while ($row = $result->fetch_assoc()) {
        $dues[] = $row;
    }

    $output = [
        'head' => ['code' => 200, 'msg' => 'Customer Dues Retrieved'],
        'body' => ['dues' => $dues]
    ];

    echo json_encode($output);
    exit();
}

/* ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ==
❌ INVALID ACTION
===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  ===  == */ else {
    echo json_encode(['head' => ['code' => 400, 'msg' => 'Invalid action parameter']]);
    exit();
}
