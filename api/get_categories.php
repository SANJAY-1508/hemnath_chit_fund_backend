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



if (isset($obj->get_categories)) {

    $sql = "SELECT `category_id`, `category_name` FROM `category` WHERE `delete_at` = 0 ORDER BY `category_name` ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["categories"][] = $row;
        }
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No categories found";
        $output["body"]["categories"] = [];
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
