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
    // <<<<<<<<<<===================== This is to list customers =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `customers` 
        WHERE `deleted_at` = 0 AND `customer_name` LIKE '%$search_text%'";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["customer"][$count] = $row;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Customer Details Not Found";
        $output["body"]["customer"] = [];
    }
} elseif (isset($obj->customer_name) && isset($obj->mobile_number) && isset($obj->email_id)) {


    $customer_name = $obj->customer_name;
    $mobile_number = $obj->mobile_number;
    $email_id = $obj->email_id;

    // Handle password
    $password = null;
    if (isset($obj->password) && trim($obj->password) !== "") {
        $password = password_hash($obj->password, PASSWORD_DEFAULT);
    }

    if (!empty($customer_name) && !empty($mobile_number) && !empty($email_id)) {

        if (!preg_match('/[^a-zA-Z0-9., ]+/', $customer_name)) {
            if (ctype_digit($mobile_number) && strlen($mobile_number) == 10) {
                if (filter_var($email_id, FILTER_VALIDATE_EMAIL)) {

                    if (isset($obj->edit_customer_id)) {
                        $edit_id = $obj->edit_customer_id;

                        if ($edit_id) {

                            // Fetch Old Data
                            $stmtOld = $conn->prepare("SELECT * FROM customers WHERE customer_id = ? AND deleted_at = 0");
                            $stmtOld->bind_param('s', $edit_id);
                            $stmtOld->execute();
                            $resultOld = $stmtOld->get_result();
                            $oldCustomer = $resultOld->fetch_assoc();
                            $stmtOld->close();

                            if (!$oldCustomer) {
                                $output["head"]["code"] = 400;
                                $output["head"]["msg"] = "Customer not found.";
                            } else {

                                // Build update query based on password
                                if ($password !== null) {
                                    // Password given → update password
                                    $updateCustomer = "
                                        UPDATE customers 
                                        SET customer_name='$customer_name',
                                            mobile_number='$mobile_number',
                                            email_id='$email_id',
                                            password='$password'
                                        WHERE customer_id='$edit_id'
                                    ";
                                } else {
                                    // Password empty → don't update password
                                    $updateCustomer = "
                                        UPDATE customers 
                                        SET customer_name='$customer_name',
                                            mobile_number='$mobile_number',
                                            email_id='$email_id'
                                        WHERE customer_id='$edit_id'
                                    ";
                                }

                                if ($conn->query($updateCustomer)) {

                                    // Fetch new data
                                    $stmtNew = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
                                    $stmtNew->bind_param('s', $edit_id);
                                    $stmtNew->execute();
                                    $resultNew = $stmtNew->get_result();
                                    $newCustomer = $resultNew->fetch_assoc();
                                    $stmtNew->close();

                                    // created_by details
                                    $created_by_id = isset($obj->created_by_id) ? trim($obj->created_by_id) : null;
                                    $created_by_name = isset($obj->created_by_name) ? trim($obj->created_by_name) : null;

                                    $remarks = $created_by_name
                                        ? "Customer details updated by $created_by_name"
                                        : "Customer details updated";

                                    // Log history
                                    logCustomerHistory(
                                        $oldCustomer['customer_id'],
                                        $oldCustomer['customer_no'],
                                        'updated',
                                        $oldCustomer,
                                        $newCustomer,
                                        $remarks,
                                        $created_by_id,
                                        $created_by_name
                                    );

                                    $output["head"]["code"] = 200;
                                    $output["head"]["msg"] = "Successfully Customer Details Updated";
                                } else {
                                    $output["head"]["code"] = 400;
                                    $output["head"]["msg"] = "Failed to update: " . $conn->error;
                                }
                            }
                        } else {
                            $output["head"]["code"] = 400;
                            $output["head"]["msg"] = "Customer not found.";
                        }
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Edit customer ID is required.";
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Invalid Email.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid Phone Number.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Customer Name Should be Alphanumeric.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} elseif (isset($obj->delete_customer_id)) {
    // <<<<<<<<<<===================== This is to Delete the customers =====================>>>>>>>>>>
    $delete_customer_id = $obj->delete_customer_id;
    if (!empty($delete_customer_id)) {
        if ($delete_customer_id) {
            // First, get the internal customer ID and old data
            $stmt = $conn->prepare("SELECT * FROM `customers` WHERE `customer_id` = ? AND `deleted_at` = 0");
            $stmt->bind_param('s', $delete_customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $output["head"]["code"] = 404;
                $output["head"]["msg"] = "Customer not found.";
            } else {
                $oldCustomer = $result->fetch_assoc();
                $internal_customer_id = $oldCustomer['id'];

                // Soft delete related chits
                $deleteChits = $conn->prepare("UPDATE `chits` SET `deleted_at` = 1, `deleted_at_datetime` = NOW() WHERE `customer_id` = ?");
                $deleteChits->bind_param('i', $internal_customer_id);
                $chits_deleted = $deleteChits->execute();
                $deleteChits->close();

                // Soft delete the customer
                $deleteCustomer = $conn->prepare("UPDATE `customers` SET `deleted_at` = 1 WHERE `customer_id` = ?");
                $deleteCustomer->bind_param('s', $delete_customer_id);
                $customer_deleted = $deleteCustomer->execute();
                $deleteCustomer->close();

                if ($customer_deleted && $chits_deleted) {
                    // Prepare created_by values
                    $created_by_id = isset($obj->created_by_id) ? trim($obj->created_by_id) : null;
                    $created_by_name = isset($obj->created_by_name) ? trim($obj->created_by_name) : null;

                    // Set remarks based on source
                    if ($created_by_id && $created_by_name) {
                        $remarks = "Customer deleted by $created_by_name";
                    } else {
                        $remarks = "Customer deleted";
                    }

                    // Log customer history
                    logCustomerHistory($oldCustomer['customer_id'], $oldCustomer['customer_no'], 'deleted', $oldCustomer, null, $remarks, $created_by_id, $created_by_name);

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Customer and related Chits Deleted.";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to delete. Please try again.";
                }
            }
            $stmt->close();
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid data.";
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
