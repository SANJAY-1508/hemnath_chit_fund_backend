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
    $provided_referral_code = isset($obj->referral_code) ? trim($obj->referral_code) : null;

    if (!empty($customer_name) && !empty($mobile_number) && !empty($email_id) && !empty($password)) {

        if (is_numeric($mobile_number) && strlen($mobile_number) == 10) {
            // Check if mobile number already exists
            $stmt = $conn->prepare("SELECT * FROM `customers` WHERE (`mobile_number` = ? OR `email_id` = ?) AND `deleted_at` = 0");
            $stmt->bind_param("ss", $mobile_number, $email_id);

            $stmt->execute();
            $mobileCheck = $stmt->get_result();

            if ($mobileCheck->num_rows == 0) {

                $referred_by_id = null;
                if (!empty($provided_referral_code)) {
                    $stmtCheck = $conn->prepare("SELECT `customer_id` FROM `customers` WHERE `referral_code` = ? AND `deleted_at` = 0");
                    $stmtCheck->bind_param("s", $provided_referral_code);
                    $stmtCheck->execute();
                    $resultCheck = $stmtCheck->get_result();
                    if ($row = $resultCheck->fetch_assoc()) {
                        $referred_by_id = $row['customer_id'];
                    } else {
                        $output = ["head" => ["code" => 400, "msg" => "Invalid referral code"]];
                        echo json_encode($output);
                        exit;
                    }
                    $stmtCheck->close();
                }


                $generated_referral_code = generateUniqueReferralCode($conn);

                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                // Insert new customer
                $stmtInsert = $conn->prepare("INSERT INTO `customers` (`customer_name`, `mobile_number`, `email_id`, `password`, `referral_code`, `referred_by_code`, `referred_by_id`, `created_at_datetime`, `deleted_at`) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)");
                $stmtInsert->bind_param("sssssss", $customer_name, $mobile_number, $email_id, $hashedPassword, $generated_referral_code, $provided_referral_code, $referred_by_id);

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

                    // Prepare created_by values
                    $created_by_id = isset($obj->created_by_id) ? trim($obj->created_by_id) : null;
                    $created_by_name = isset($obj->created_by_name) ? trim($obj->created_by_name) : null;

                    // Set remarks based on source
                    if ($created_by_id && $created_by_name) {
                        $remarks = "Customer created by $created_by_name";
                    } else {
                        $remarks = "Customer signed up successfully";
                    }


                    if ($provided_referral_code) {
                        $remarks .= " via referral code: $provided_referral_code";
                    }

                    logCustomerHistory($customer['customer_id'], $customer['customer_no'], 'created', null, $customer, $remarks, $created_by_id, $created_by_name);

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
