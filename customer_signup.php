<?php
include 'db/config.php';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// Check Action
if (!isset($obj->action)) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Action parameter is missing"]]);
    exit();
}

$action = $obj->action;

// <<<<<<<<<<===================== Customer Signup =====================>>>>>>>>>>
if ($action === "signup" && isset($obj->customer_name) && isset($obj->mobile_number) && isset($obj->email_id) && isset($obj->password)) {

    $customer_name = trim($obj->customer_name);
    $mobile_number = trim($obj->mobile_number);
    $email_id = trim($obj->email_id);
    $password = trim($obj->password);

    if (!empty($customer_name) && !empty($mobile_number) && !empty($email_id) && !empty($password)) {

        if (is_numeric($mobile_number) && strlen($mobile_number) == 10) {
            // Check if mobile number already exists
            $stmt = $conn->prepare("SELECT * FROM `customers` WHERE (`mobile_number` = ? OR `email_id` = ?) AND `deleted_at` = 0");
            $stmt->bind_param("ss", $mobile_number, $email_id);

            $stmt->execute();
            $mobileCheck = $stmt->get_result();

            if ($mobileCheck->num_rows == 0) {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                // Insert new customer
                $stmtInsert = $conn->prepare("INSERT INTO `customers` (`customer_name`, `mobile_number`, `email_id`, `password`, `created_at_datetime`, `deleted_at`) VALUES (?, ?, ?, ?, NOW(), 0)");
                $stmtInsert->bind_param("ssss", $customer_name, $mobile_number, $email_id, $hashedPassword);

                if ($stmtInsert->execute()) {
                    $insertId = $stmtInsert->insert_id;

                    // Generate unique IDs
                    $customer_id = "CUST" . str_pad($insertId, 5, "0", STR_PAD_LEFT);
                    $customer_no = "CN" . date("ymd") . $insertId;

                    // Update record with generated IDs
                    $stmtUpdate = $conn->prepare("UPDATE `customers` SET `customer_id` = ?, `customer_no` = ? WHERE `id` = ?");
                    $stmtUpdate->bind_param("ssi", $customer_id, $customer_no, $insertId);
                    $stmtUpdate->execute();

                    // Fetch inserted data
                    $stmtGet = $conn->prepare("SELECT * FROM `customers` WHERE `id` = ?");
                    $stmtGet->bind_param("i", $insertId);
                    $stmtGet->execute();
                    $result = $stmtGet->get_result();
                    $customer = $result->fetch_assoc();


                    logCustomerHistory($customer['customer_id'], $customer['customer_no'], 'created', null, $customer, 'Customer signed up successfully', null, null);

                    $output = [
                        "head" => ["code" => 200, "msg" => "Signup Successful"],
                        "body" => ["customer" => $customer]
                    ];
                } else {
                    $output = ["head" => ["code" => 400, "msg" => "Failed to create customer"]];
                }
                $stmtInsert->close();
            } else {
                $output = ["head" => ["code" => 400, "msg" => "Mobile number OR Email already registered"]];
            }
            $stmt->close();
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Invalid mobile number"]];
        }
    } else {
        $output = ["head" => ["code" => 400, "msg" => "All fields are required"]];
    }

    echo json_encode($output);
    exit;
}

// <<<<<<<<<<===================== Invalid Action =====================>>>>>>>>>>
else {
    echo json_encode(["head" => ["code" => 400, "msg" => "Invalid action parameter"]]);
    exit;
}
