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
// <<<<<<<<<<===================== This is to list payment_details =====================>>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `payment_details` WHERE `delete_at` = 0 AND `customer_details` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $imgLink = null;
            if ($row["payment_proof"] != null && $row["payment_proof"] != 'null' && strlen($row["payment_proof"]) > 0) {
                $imgLink = "https://" . $_SERVER['SERVER_NAME'] . "/zenchitbilling/uploads/payment_details/" . $row["payment_proof"];
            }
            $row["payment_proof"] = $imgLink;
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["payment_details"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "payment_details records not found";
        $output["body"]["payment_details"] = [];
    }
}
// <<<<<<<<<<===================== This is to Create and Edit payment_details =====================>>>>>>>>>>
else if (isset($obj->action)) {
    if ($obj->action == "create payment") {
        // Creation - require all fields except status (DB default: Waiting Approval)
        if (!isset($obj->customer_id) || !isset($obj->customer_details) || !isset($obj->due_details) || !isset($obj->payment_type) || !isset($obj->payment_amount)) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Parameter is Mismatch";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }
        $customer_id = $obj->customer_id;
        $customer_details = $obj->customer_details;
        $due_details = $obj->due_details;
        $payment_type = $obj->payment_type;
        $payment_amount = $obj->payment_amount;
        if (isset($obj->image_url) && !empty($obj->image_url)) {
            $outputFilePath = "../uploads/payment_details/";
            if (!file_exists($outputFilePath)) {
                mkdir($outputFilePath, 0777, true);
            }
            $payment_proof = pngImageToWebP($obj->image_url, $outputFilePath);
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Payment Proof Image Not Upload.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }
        $createPayment = "INSERT INTO `payment_details`(`customer_id`,`customer_details`,`due_details`,`payment_type`,`payment_amount`,`payment_proof`,`create_at`, `delete_at`) VALUES ('$customer_id','$customer_details','$due_details','$payment_type','$payment_amount','$payment_proof','$timestamp','0')";
        if ($conn->query($createPayment)) {
            $id = $conn->insert_id;
            $enId = uniqueID('payment_details', $id);
            $updatePaymentId = "UPDATE `payment_details` SET `payment_details_id` ='$enId' WHERE `id`='$id'";
            $conn->query($updatePaymentId);
            
            // Parse due_details to extract chit_dues id and update status
            $due_obj = json_decode($due_details);
            if ($due_obj && isset($due_obj->id)) {
                $due_id = $due_obj->id;
                $update_due = "UPDATE `chit_dues` SET `status` = 'Waiting Approval' WHERE `id` = '$due_id'";
                $conn->query($update_due);
            }
            
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully payment_details Created";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to create. Please try again.";
        }
    } else if ($obj->action == "update payment") {
        if (!isset($obj->edit_payment_id)) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Parameter is Mismatch";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }
        $edit_id = $obj->edit_payment_id;
        // Fetch existing record to preserve non-updatable fields
        $existing_sql = "SELECT * FROM `payment_details` WHERE `payment_details_id` = '$edit_id' AND `delete_at` = 0";
        $existing_result = $conn->query($existing_sql);
        if ($existing_result->num_rows == 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Payment details not found.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }
        $existing_row = $existing_result->fetch_assoc();
      
        // Prepare update fields - always update status to 'Approved'; optional payment_amount and image
        $update_fields = [];
        $update_fields[] = "`status` = 'Approved'";
        if (isset($obj->payment_amount)) {
            $update_fields[] = "`payment_amount` = '$obj->payment_amount'";
        }
      
        // Handle optional image update
        if (isset($obj->image_url) && !empty($obj->image_url)) {
            $outputFilePath = "../uploads/payment_details/";
            if (!file_exists($outputFilePath)) {
                mkdir($outputFilePath, 0777, true);
            }
            $new_payment_proof = pngImageToWebP($obj->image_url, $outputFilePath);
            $update_fields[] = "`payment_proof` = '$new_payment_proof'";
        }
      
        // Since status is always included, proceed with update
        $update_sql = "UPDATE `payment_details` SET " . implode(', ', $update_fields) . " WHERE `payment_details_id` = '$edit_id'";
        if ($conn->query($update_sql)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully payment_details Details Updated";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to update. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid action provided.";
    }
}
// <<<<<<<<<<===================== This is to Delete the payment_details =====================>>>>>>>>>>
else if (isset($obj->delete_payment_id)) {
    $delete_payment_id = $obj->delete_payment_id;
    if (!empty($delete_payment_id)) {
        $deletePayment = "UPDATE `payment_details` SET `delete_at`= 1 WHERE `payment_details_id`='$delete_payment_id'";
        if ($conn->query($deletePayment)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "payment_details Deleted Successfully.!";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
}
else if (isset($obj->customer_id)) {
    $customer_id = $obj->customer_id;
    $sql = "SELECT `id`, `payment_details_id`, `customer_id`, `customer_details`, `due_details`, `payment_type`, `payment_amount`, `payment_proof`, `status`, `create_at`, `delete_at` FROM `payment_details` WHERE `delete_at` = 0 AND `customer_id` = '$customer_id' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["payment_details"] = [];
   
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $imgLink = null;
            if ($row["payment_proof"] != null && $row["payment_proof"] != 'null' && strlen($row["payment_proof"]) > 0) {
                $imgLink = "https://" . $_SERVER['SERVER_NAME'] . "/zenchitbilling/uploads/payment_details/" . $row["payment_proof"];
            }
            $row["payment_proof"] = $imgLink;
            $output["body"]["payment_details"][] = $row;
        }
        $output["head"]["msg"] = "Success";
    } else {
        $output["head"]["msg"] = "payment_details records not found for this customer";
        $output["body"]["payment_details"] = [];
    }
}else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}
echo json_encode($output, JSON_NUMERIC_CHECK);
?>