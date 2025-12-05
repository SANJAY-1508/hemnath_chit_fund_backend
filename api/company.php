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
    $sql = "SELECT `id`, `company_name`, `address`, `pincode`, `phone`, `mobile`, `gst_no`, `state`, `city`, `img`, `acc_number`, `acc_holder_name`, `bank_name`, `ifsc_code`, `bank_branch`, `deleted_at`, `created_by`, `created_name`, `created_date` FROM `company` WHERE 1";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        if ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["company"] = $row;
            $imgLink = null;
            if ($row["img"] != null && $row["img"] != 'null' && strlen($row["img"]) > 0) {
                $imgLink = "https://" . $_SERVER['SERVER_NAME'] . "/uploads/company/" . $row["img"];
                $output["body"]["company"]["img"] = $imgLink;
            } else {
                $output["body"]["company"]["img"] = $imgLink;
            }
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Company Details Not Found";
    }
} else if (isset($obj->company_name) && isset($obj->acc_holder_name) && isset($obj->company_profile_img) && isset($obj->address) && isset($obj->pincode) && isset($obj->city) && isset($obj->state) && isset($obj->phone_number) && isset($obj->mobile_number) && isset($obj->gst_number) && isset($obj->acc_number) && isset($obj->bank_name) && isset($obj->ifsc_code) && isset($obj->bank_branch)) {

    $company_name = $obj->company_name;
    $company_profile_img = $obj->company_profile_img;
    $address = $obj->address;
    $pincode = $obj->pincode;
    $city = $obj->city;
    $state = $obj->state;
    $phone_number = $obj->phone_number;
    $mobile_number = $obj->mobile_number;
    $gst_number = $obj->gst_number;
    $acc_number = $obj->acc_number;
    $acc_holder_name = $obj->acc_holder_name;
    $bank_name = $obj->bank_name;
    $ifsc_code = $obj->ifsc_code;
    $bank_branch = $obj->bank_branch;

    if (!empty($company_name) && !empty($address) && !empty($pincode) && !empty($phone_number) && !empty($city) && !empty($state)) {

        if (!preg_match('/[^a-zA-Z0-9., ]+/', $company_name)) {

            if (numericCheck($phone_number) && strlen($phone_number) == 10) {

                $edit_id = 1;
                $updateCompany = "";
                if (!empty($company_profile_img)) {
                    $outputFilePath = "../uploads/company/";
                    $profile_path = pngImageToWebP($company_profile_img, $outputFilePath);
                    $updateCompany = "UPDATE `company` SET `company_name`='$company_name', `img`='$profile_path', `address`='$address', `pincode`='$pincode', `city`='$city', `state`='$state', `phone`='$phone_number', `mobile`='$mobile_number', `gst_no`='$gst_number', `acc_number`='$acc_number', `acc_holder_name`='$acc_holder_name', `bank_name`='$bank_name', `ifsc_code`='$ifsc_code', `bank_branch`='$bank_branch' WHERE `id`='$edit_id'";
                } else {
                    $updateCompany = "UPDATE `company` SET `company_name`='$company_name', `address`='$address', `pincode`='$pincode', `city`='$city', `state`='$state', `phone`='$phone_number', `mobile`='$mobile_number', `gst_no`='$gst_number', `acc_number`='$acc_number', `acc_holder_name`='$acc_holder_name', `bank_name`='$bank_name', `ifsc_code`='$ifsc_code', `bank_branch`='$bank_branch' WHERE `id`='$edit_id'";
                }

                if ($conn->query($updateCompany)) {
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Company Details Updated";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again." . $conn->error;
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid Phone Number.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Company name Should be Alphanumeric.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
}

// <<<<<<<<<<===================== This is to Delete the users =====================>>>>>>>>>>

else if (isset($obj->image_delete)) {

    $image_delete = $obj->image_delete;

    if ($image_delete === true) {

        $status = ImageRemove('company', 1);
        if ($status == "company Image Removed Successfully") {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "successfully company Image deleted !.";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "faild to deleted.please try againg.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Parameter is Mismatch";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
