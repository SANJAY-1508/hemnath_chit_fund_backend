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
    // <<<<<<<<<<===================== This is to list users =====================>>>>>>>>>>
    $search_text = $obj->search_text;
    $sql = "SELECT `id`, `user_id`,`user_name`, `name`, `phone`, `img`, `role`, `password`, `deleted_at`, `created_date` FROM `user` WHERE `deleted_at` = 0 AND `name` LIKE '%$search_text%'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["body"]["user"][$count] = $row;
            $imgLink = null;
            if ($row["img"] != null && $row["img"] != 'null' && strlen($row["img"]) > 0) {
                $imgLink = "https://" . $_SERVER['SERVER_NAME'] . "/uploads/users/" . $row["img"];
                $output["body"]["user"][$count]["img"] = $imgLink;
            } else {
                $output["body"]["user"][$count]["img"] = $imgLink;
            }
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "User Details Not Found";
        $output["body"]["user"] = [];
    }
} else if (isset($obj->name) && isset($obj->user_name) && isset($obj->phone_number) && isset($obj->password) && isset($obj->role) && isset($obj->user_profile_img)) {
    // <<<<<<<<<<===================== This is to Create and Edit users =====================>>>>>>>>>>
    $name = $obj->name;
    $user_name = $obj->user_name;
    $phone_number = $obj->phone_number;
    $password = $obj->password;
    $role = $obj->role;
    $user_profile_img = $obj->user_profile_img;

    if (!empty($user_name) && !empty($phone_number) && !empty($password) && !empty($role)) {

        if (!preg_match('/[^a-zA-Z0-9., ]+/', $user_name)) {

            if (numericCheck($phone_number) && strlen($phone_number) == 10) {

                if (isset($obj->edit_user_id)) {
                    $edit_id = $obj->edit_user_id;
                    if ($edit_id) {
                        $updateUser = "";
                        if (!empty($user_profile_img)) {
                            $outputFilePath = "../uploads/users/";
                            $profile_path = pngImageToWebP($user_profile_img, $outputFilePath);
                            $updateUser = "UPDATE `user` SET `name`='$name',`user_name`='$user_name', `img`='$profile_path', `phone`='$phone_number', `password`='$password', `role`='$role' WHERE `user_id`='$edit_id'";
                        } else {
                            $updateUser = "UPDATE `user` SET `name`='$name',`user_name`='$user_name', `phone`='$phone_number', `password`='$password', `role`='$role' WHERE `user_id`='$edit_id'";
                        }

                        if ($conn->query($updateUser)) {
                            $output["head"]["code"] = 200;
                            $output["head"]["msg"] = "Successfully User Details Updated";
                        } else {
                            $output["head"]["code"] = 400;
                            $output["head"]["msg"] = "Failed to connect. Please try again." . $conn->error;
                        }
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "User not found.";
                    }
                } else {
                    $mobileCheck = $conn->query("SELECT `id` FROM `user` WHERE `phone`='$phone_number'");
                    if ($mobileCheck->num_rows == 0) {
                        $createUser = "";
                        if (!empty($user_profile_img)) {
                            $outputFilePath = "../uploads/users/";
                            $profile_path = pngImageToWebP($user_profile_img, $outputFilePath);
                            $createUser = "INSERT INTO `user` (`name`, `user_name`,`phone`, `password`, `role`, `created_date`, `img`) VALUES ('$name','$user_name', '$phone_number', '$password', '$role', '$timestamp', '$profile_path')";
                        } else {
                            $createUser = "INSERT INTO `user` (`name`, `user_name`,`phone`, `password`, `role`, `created_date` ) VALUES ('$name','$user_name', '$phone_number', '$password', '$role', '$timestamp')";
                        }

                        if ($conn->query($createUser)) {
                            $id = $conn->insert_id;
                            $enid = uniqueID('user', $id);
                            $update = "UPDATE `user` SET `user_id`='$enid' WHERE `id` = $id";
                            $conn->query($update);

                            $output["head"]["code"] = 200;
                            $output["head"]["msg"] = "Successfully User Created";
                        } else {
                            $output["head"]["code"] = 400;
                            $output["head"]["msg"] = "Failed to connect. Please try again.";
                        }
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Mobile Number Already Exists.";
                    }
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid Phone Number.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Username Should be Alphanumeric.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->delete_user_id) && isset($obj->image_delete)) {
    $delete_user_id = $obj->delete_user_id;

    $image_delete = $obj->image_delete;


    if (!empty($delete_user_id)) {




        if ($image_delete === true) {

            $status = ImageRemove('user', $delete_user_id);
            if ($status == "User Image Removed Successfully") {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "successfully user Image deleted !.";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "faild to deleted.please try againg.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "User not found.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->delete_user_id)) {
    // <<<<<<<<<<===================== This is to Delete the users =====================>>>>>>>>>>
    $delete_user_id = $obj->delete_user_id;
    if (!empty($delete_user_id)) {
        if ($delete_user_id) {
            $deleteuser = "UPDATE `user` SET `deleted_at`=1 WHERE `user_id`='$delete_user_id'";
            if ($conn->query($deleteuser) === true) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully User Deleted.";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to delete. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid data.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else if (isset($obj->phone) && isset($obj->password)) {
    // <<<<<<<<<<===================== This is to Login the user =====================>>>>>>>>>>
    $phone = $obj->phone;
    $password = $obj->password;

    if (!empty($phone) && !empty($password)) {
        $loginCheck = $conn->query("SELECT `id`, `user_id`, `name`, `phone`, `img`, `role`, `password`, `deleted_at`, `created_date` FROM `user` WHERE `phone`='$phone' AND `password`='$password' AND `deleted_at`=0");
        if ($loginCheck->num_rows > 0) {
            $user = $loginCheck->fetch_assoc();
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Login Successful";
            $output["body"]["user"] = $user;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid Credentials";
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
