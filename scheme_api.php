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

// Validate Action
if (!isset($obj->action)) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Action parameter is missing"]]);
    exit();
}

$action = $obj->action;

/* ==============================================================
   1️⃣  CREATE SCHEME
   ==============================================================*/
if ($action === "create") {

    $required_fields = ['scheme_name', 'duration', 'schemet_due_amount', 'scheme_bonus', 'scheme_maturtiy_amount', 'duration_unit'];

    foreach ($required_fields as $field) {
        if (!isset($obj->$field) || trim($obj->$field) === "") {
            echo json_encode(["head" => ["code" => 400, "msg" => "Missing field: $field"]]);
            exit();
        }
    }

    $scheme_name = trim($obj->scheme_name);
    $duration = intval($obj->duration);
    $schemet_due_amount = floatval($obj->schemet_due_amount);
    $scheme_bonus = floatval($obj->scheme_bonus);
    $scheme_maturtiy_amount = floatval($obj->scheme_maturtiy_amount);
    $duration_unit = trim($obj->duration_unit);
    if (!in_array($duration_unit, ['month', 'week'])) {
        echo json_encode(["head" => ["code" => 400, "msg" => "Invalid duration_unit (must be 'month' or 'week')"]]);
        exit();
    }

    // Insert scheme
    $stmtInsert = $conn->prepare("INSERT INTO `schemes` 
        (`scheme_name`, `duration`, `schemet_due_amount`, `scheme_bonus`, `scheme_maturtiy_amount`, `duration_unit`, `created_at`, `deleted_at`)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)");
    $stmtInsert->bind_param("siddds", $scheme_name, $duration, $schemet_due_amount, $scheme_bonus, $scheme_maturtiy_amount, $duration_unit);

    if ($stmtInsert->execute()) {
        $insertId = $stmtInsert->insert_id;
        $scheme_id = "SCH" . str_pad($insertId, 5, "0", STR_PAD_LEFT);
        $scheme_no = "SC" . date("ymd") . $insertId;

        // Update IDs
        $stmtUpdate = $conn->prepare("UPDATE `schemes` SET `scheme_id` = ?, `scheme_no` = ? WHERE `id` = ?");
        $stmtUpdate->bind_param("ssi", $scheme_id, $scheme_no, $insertId);
        $stmtUpdate->execute();

        $output = ["head" => ["code" => 200, "msg" => "Scheme Created Successfully"], "body" => ["scheme_id" => $scheme_id, "scheme_no" => $scheme_no]];
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Failed to create scheme"]];
    }
    $stmtInsert->close();

    echo json_encode($output);
    exit();
}

/* ==============================================================
   2️⃣  UPDATE SCHEME
   ==============================================================*/
elseif ($action === "update" && isset($obj->id)) {

    $id = intval($obj->id);

    $fields = ['scheme_name', 'duration', 'schemet_due_amount', 'scheme_bonus', 'scheme_maturtiy_amount', 'duration_unit'];
    $updates = [];
    $params = [];
    $types = "";

    foreach ($fields as $field) {
        if (isset($obj->$field)) {
            $updates[] = "`$field` = ?";
            if ($field === 'duration') {
                $params[] = intval($obj->$field);
                $types .= "i";
            } elseif (in_array($field, ['schemet_due_amount', 'scheme_bonus', 'scheme_maturtiy_amount'])) {
                $params[] = floatval($obj->$field);
                $types .= "d";
            } else {
                $params[] = trim($obj->$field);
                $types .= "s";
            }
        }
    }

    if (empty($updates)) {
        echo json_encode(["head" => ["code" => 400, "msg" => "No fields provided for update"]]);
        exit();
    }

    $query = "UPDATE `schemes` SET " . implode(", ", $updates) . ", `updated_at` = NOW() WHERE `id` = ? AND `deleted_at` = 0";
    $types .= "i";
    $params[] = $id;

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $output = ["head" => ["code" => 200, "msg" => "Scheme Updated Successfully"]];
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Failed to update scheme"]];
    }

    echo json_encode($output);
    exit();
}

/* ==============================================================
   3️⃣  LIST SCHEMES
   ==============================================================*/
elseif ($action === "list") {

    $stmt = $conn->prepare("SELECT * FROM `schemes` WHERE `deleted_at` = 0 ORDER BY `id` DESC");
    $stmt->execute();
    $result = $stmt->get_result();

    $schemes = [];
    while ($row = $result->fetch_assoc()) {
        $schemes[] = $row;
    }

    $output = [
        "head" => ["code" => 200, "msg" => "Scheme List Retrieved"],
        "body" => ["schemes" => $schemes]
    ];

    echo json_encode($output);
    exit();
}

/* ==============================================================
   4️⃣  DELETE SCHEME (Soft Delete)
   ==============================================================*/
elseif ($action === "delete" && isset($obj->id)) {

    $id = intval($obj->id);
    $stmt = $conn->prepare("UPDATE `schemes` SET `deleted_at` = 1, `deleted_at_datetime` = NOW() WHERE `id` = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $output = ["head" => ["code" => 200, "msg" => "Scheme Deleted Successfully"]];
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Failed to delete scheme"]];
    }

    echo json_encode($output);
    exit();
}

/* ==============================================================
   ❌ INVALID ACTION
   ==============================================================*/
else {
    echo json_encode(["head" => ["code" => 400, "msg" => "Invalid action parameter"]]);
    exit();
}
?>