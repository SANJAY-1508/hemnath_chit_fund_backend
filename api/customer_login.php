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

// <<<<<<<<<<===================== Customer Login =====================>>>>>>>>>>
if (!isset($obj->action)) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}

$action = $obj->action;

if ($action === "login") {
    $mobile_number = $obj->mobile_number ?? '';
    $password = $obj->password ?? '';

    // Basic validation
    if (empty($mobile_number) || empty($password)) {
        echo json_encode([
            "head" => ["code" => 400, "msg" => "Please provide both mobile number and password"]
        ]);
        exit();
    }

    // Validate mobile number format
    if (!is_numeric($mobile_number) || strlen($mobile_number) != 10) {
        echo json_encode([
            "head" => ["code" => 400, "msg" => "Invalid mobile number format"]
        ]);
        exit();
    }

    // Check customer existence
    $stmt = $conn->prepare("SELECT * FROM `customers` WHERE `mobile_number` = ? AND `deleted_at` = 0 LIMIT 1");
    $stmt->bind_param("s", $mobile_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $customer['password'])) {
            $output = [
                "head" => [
                    "code" => 200,
                    "msg" => "Login Successful"
                ],
                "body" => [
                    "customer_id" => $customer['customer_id'],
                    "customer_no" => $customer['customer_no'],
                    "customer_name" => $customer['customer_name'],
                    "mobile_number" => $customer['mobile_number'],
                    "email_id" => $customer['email_id']
                ]
            ];
        } else {
            $output = [
                "head" => [
                    "code" => 401,
                    "msg" => "Incorrect password"
                ]
            ];
        }
    } else {
        $output = [
            "head" => [
                "code" => 404,
                "msg" => "Customer not found"
            ]
        ];
    }

    $stmt->close();
    echo json_encode($output);
    exit();
} else {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Invalid action parameter"]
    ]);
    exit();
}
?>
