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
if (isset($obj->search_text)) {
    // <<<<<<<<<<===================== This is to list feedbacks =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT `id`, `customer_feedback_id`, `customer_id`, `customer_name`, `customer_feedback_message`, `customer_feedback_rating`, `created_date` FROM `customer_feedback` WHERE `deleted_at` = 0 AND `customer_feedback_message` LIKE '%$search_text%'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["body"]["feedback"][$count] = $row;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Feedback Details Not Found";
        $output["body"]["feedback"] = [];
    }
} else if (isset($obj->customer_id) && isset($obj->customer_feedback_message) && isset($obj->customer_feedback_rating)) {
    // <<<<<<<<<<===================== This is to Create feedback =====================>>>>>>>>>>
    $customer_id = $obj->customer_id;
    $customer_feedback_message = $obj->customer_feedback_message;
    $customer_feedback_rating = $obj->customer_feedback_rating;
    if (!empty($customer_id) && !empty($customer_feedback_message) && !empty($customer_feedback_rating)) {
        // Fetch customer_name from customers table using customer_id
        $customerCheck = $conn->query("SELECT `customer_name` FROM `customers` WHERE `customer_id` = '$customer_id' AND `deleted_at` = 0");
        if ($customerCheck->num_rows > 0) {
            $customer = $customerCheck->fetch_assoc();
            $customer_name = $customer['customer_name'];
            // Assuming multiple feedbacks per customer are allowed; no uniqueness check
            $createFeedback = "INSERT INTO `customer_feedback` (`customer_id`, `customer_name`, `customer_feedback_message`, `customer_feedback_rating`, `created_date`) VALUES ('$customer_id', '$customer_name', '$customer_feedback_message', '$customer_feedback_rating', '$timestamp')";
            if ($conn->query($createFeedback)) {
                $id = $conn->insert_id;
                $enid = uniqueID('customer_feedback', $id);
                $update = "UPDATE `customer_feedback` SET `customer_feedback_id`='$enid' WHERE `id` = $id";
                $conn->query($update);
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Feedback Created";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to connect. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Customer not found.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter Mismatch";
    $output["head"]["inputs"] = $obj;
}
echo json_encode($output, JSON_NUMERIC_CHECK);
?>