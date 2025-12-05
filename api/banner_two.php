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
    $sql = "SELECT * FROM `banner_two` WHERE `delete_at`=0";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["banner_two"][$count] = $row;
            $imgLink = null;
            if ($row["img"] != null && $row["img"] != 'null' && strlen($row["img"]) > 0) {
                $imgLink = "https://" . $_SERVER['SERVER_NAME'] . "/uploads/banner_two/" . $row["img"];
                $output["body"]["banner_two"][$count]["img"] = $imgLink;
            } else {
                $output["body"]["banner_two"][$count]["img"] = $imgLink;
            }
            $imgLink = null;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Banner Details Not Found";
        $output["body"]["banner"] = [];
    }
} else if (isset($obj->image_id)  && isset($obj->current_user_id)) {

    $image_id = $obj->image_id;
    $current_user_id = $obj->current_user_id;

    if (!empty($current_user_id)) {

        if (numericCheck($current_user_id)) {

            $current_user_name = getUserName($current_user_id);

            if (!empty($current_user_name)) {
                if (!empty($image_id)) {
                    if (numericCheck($image_id)) {

                        $deleteBanner = "UPDATE `banner_two` SET `delete_at`=1 WHERE `id`='$image_id'";
                        if ($conn->query($deleteBanner)) {
                            $output["head"]["code"] = 200;
                            $output["head"]["msg"] = "Successfully Banner Deleted !";
                        } else {
                            $output["head"]["code"] = 400;
                            $output["head"]["msg"] = "Failed to Deleted. Please try again.";
                        }
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Image Id Should be Numeric";
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Banner not found.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "user not found.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Error Occurred: Please restart the application and try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->current_user_id) && isset($obj->image_url)) {
    $image_url = $obj->image_url;
    $current_user_id = $obj->current_user_id;
    if (!empty($current_user_id)) {

        if (numericCheck($current_user_id)) {

            $current_user_name = getUserName($current_user_id);

            if (!empty($current_user_name)) {
                if (!empty($image_url)) {
                    $outputFilePath = "../uploads/banner_two/";

                    $profile_path = pngImageToWebP($image_url, $outputFilePath);

                    $createBanner = "INSERT INTO `banner_two`(`img`, `delete_at`, `created_by`, `created_name`, `created_date`) VALUES ('$profile_path',0,'$current_user_id','$current_user_name ','$timestamp')";
                    if ($conn->query($createBanner)) {
                        $output["head"]["code"] = 200;
                        $output["head"]["msg"] = "Successfully Banner Created";
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Failed to Added. Please try again.";
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Banner Image Not Upload.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "user not found.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Error Occurred: Please restart the application and try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
