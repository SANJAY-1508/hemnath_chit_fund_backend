<?php
$name = "localhost";
$username = "root";
$password = "";
$database = "zen_chit_app";

$conn = new mysqli($name, $username, $password, $database);

if ($conn->connect_error) {
    $output = array();
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "DB Connection Lost...";

    echo json_encode($output, JSON_NUMERIC_CHECK);
};



function numericCheck($data)
{
    if (!preg_match('/[^0-9]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function For Check Alphabets Only =====================>>>>>>>>>>

function alphaCheck($data)
{
    if (!preg_match('/[^a-zA-Z]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

function alphaNumericCheck($data)
{
    if (!preg_match('/[^a-zA-Z0-9]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

function pngImageToWebP($data, $file_path)
{
    if (empty($data)) {
        error_log('No image data provided, skipping conversion.');
        return false; // or return null if you want to indicate "no image"
    }

    if (!extension_loaded('gd')) {
        error_log('GD extension is not available. Please install or enable the GD extension.');
        return false;
    }

    // Remove data URI prefix (e.g., "data:image/png;base64,")
    if (preg_match('/^data:image\/[a-z]+;base64,/', $data)) {
        $data = preg_replace('/^data:image\/[a-z]+;base64,/', '', $data);
    }

    $imageData = base64_decode($data);
    if ($imageData === false || empty($imageData)) {
        error_log('Failed to decode base64 image data or data is empty.');
        return false;
    }

    $sourceImage = @imagecreatefromstring($imageData);
    if ($sourceImage === false) {
        error_log('Failed to create the source image. Possibly invalid data.');
        return false;
    }

    if (!is_dir($file_path)) {
        if (!mkdir($file_path, 0775, true)) {
            error_log('Failed to create directory: ' . $file_path);
            imagedestroy($sourceImage);
            return false;
        }
    }

    date_default_timezone_set('Asia/Calcutta');
    $timestamp = str_replace([' ', ':'], '-', date('Y-m-d H:i:s'));
    $file_pathnew = $file_path . $timestamp . ".webp";
    $retunfilename = $timestamp . ".webp";

    if (!is_writable($file_path)) {
        error_log('Directory is not writable: ' . $file_path);
        imagedestroy($sourceImage);
        return false;
    }

    try {
        if (!imagewebp($sourceImage, $file_pathnew, 80)) {
            error_log('Failed to convert PNG to WebP. Path: ' . $file_pathnew);
            imagedestroy($sourceImage);
            return false;
        }
    } catch (\Throwable $th) {
        error_log('Error in image conversion: ' . $th->getMessage());
        imagedestroy($sourceImage);
        return false;
    }

    imagedestroy($sourceImage);
    return $retunfilename;
}

function uniqueID($prefix_name, $auto_increment_id)
{

    date_default_timezone_set('Asia/Calcutta');
    $timestamp = date('Y-m-d H:i:s');
    $encryptId = $prefix_name . "_" . $timestamp . "_" . $auto_increment_id;

    $hashid = md5($encryptId);

    return $hashid;
}

function ImageRemove($string, $id)
{
    global $conn;
    $status = "No Data Updated";
    if ($string == "user") {
        $sql_user = "UPDATE `user` SET `img`=null WHERE `user_id` ='$id' ";
        if ($conn->query($sql_user) === TRUE) {
            $status = "User Image Removed Successfully";
        } else {
            $status = "User Image Not Removed !";
        }
    } else if ($string == "customer") {
        $sql_customer = "UPDATE `customer` SET `img`=null WHERE `customer_id`='$id' ";
        if ($conn->query($sql_customer) === TRUE) {
            $status = "customer Image Removed Successfully";
        } else {
            $status = "customer Image Not Removed !";
        }
    } else if ($string == "company") {
        $sql_company = "UPDATE `company` SET  `img`=null WHERE `id`='$id' ";
        if ($conn->query($sql_company) === TRUE) {
            $status = "company Image Removed Successfully";
        } else {
            $status = "company Image Not Removed !";
        }
    } else if ($string == "customer_proof") {
        $sql_products = " UPDATE `customer` SET `proof_img`=null WHERE `customer_id`='$id' ";
        if ($conn->query($sql_products) === TRUE) {
            $status = "Customer Proff Image Removed Successfully";
        } else {
            $status = "Customer Proff Image Not Removed !";
        }
    }
    return $status;
}

// Log customer history
function logCustomerHistory($customer_id, $customer_no, $action_type, $old_value = null, $new_value = null, $remarks = null, $created_by_id = null, $created_by_name = null)
{
    global $conn, $timestamp;
    $old_value = $old_value ? json_encode($old_value, JSON_NUMERIC_CHECK) : null;
    $new_value = $new_value ? json_encode($new_value, JSON_NUMERIC_CHECK) : null;
    $sql = "INSERT INTO `customer_history` (`customer_id`, `customer_no`, `action_type`, `old_value`, `new_value`, `remarks`, `created_by_id`, `created_by_name`, `created_at`) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssssssss", $customer_id, $customer_no, $action_type, $old_value, $new_value, $remarks, $created_by_id, $created_by_name, $timestamp);
        $stmt->execute();
        $stmt->close();
    }
}

function getUserName($user)
{
    global $conn;
    $result = "";

    $checkUser = $conn->query("SELECT `name` FROM `user` WHERE `id`='$user'");
    if ($checkUser->num_rows > 0) {
        if ($userData = $checkUser->fetch_assoc()) {
            $result = $userData['name'];
        }
    }

    return $result;
}

function generateUniqueReferralCode($conn)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $codeLength = 5;
    do {
        $code = '';
        for ($i = 0; $i < $codeLength; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        // Check uniqueness
        $stmt = $conn->prepare("SELECT id FROM `customers` WHERE `referral_code` = ? AND `deleted_at` = 0");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $code;
}
