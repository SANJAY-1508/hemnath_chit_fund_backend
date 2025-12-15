<?php
// chat.php
include 'db/config.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

date_default_timezone_set('Asia/Calcutta');

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = [];

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    echo json_encode(["msg" => "OK"]);
    exit();
}

if (!isset($obj->action)) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Action missing"]]);
    exit();
}

$action = $obj->action;

/* ===============================================================
   1️⃣ SEND MESSAGE (Customer → Manager)
   ==============================================================*/
if ($action === "send") {

    $required = ['customer_id', 'message', 'sender', 'message_id'];
    foreach ($required as $r) {
        if (!isset($obj->$r) || trim($obj->$r) == "") {
            echo json_encode(["head" => ["code" => 400, "msg" => "Missing $r"]]);
            exit();
        }
    }

    $message_id = $obj->message_id;
    $customer_id = $obj->customer_id;
    $sender = $obj->sender;
    $message = $obj->message;

    $stmt = $conn->prepare("
        INSERT INTO chat_messages (message_id, customer_id, sender, message, status, created_at)
        VALUES (?, ?, ?, ?, 'sent', NOW())
    ");
    $stmt->bind_param("ssss", $message_id, $customer_id, $sender, $message);

    if ($stmt->execute()) {
        echo json_encode([
            "head" => ["code" => 200, "msg" => "Message Sent"],
            "body" => ["message_id" => $message_id]
        ]);
    } else {
        echo json_encode([
            "head" => ["code" => 500, "msg" => "Error while saving message"]
        ]);
    }
    exit();
}

/* ===============================================================
   2️⃣ FETCH MESSAGES (Customer Inbox)
   ==============================================================*/
else if ($action === "list" && isset($obj->customer_id)) {

    $cid = $obj->customer_id;

    $stmt = $conn->prepare("
        SELECT id, message_id, customer_id, sender, message, status, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at
        FROM chat_messages
        WHERE customer_id = ?
        ORDER BY created_at ASC
    ");

    $stmt->bind_param("s", $cid);
    $stmt->execute();
    $res = $stmt->get_result();

    $messages = [];
    while ($row = $res->fetch_assoc()) {
        $messages[] = $row;
    }

    echo json_encode([
        "head" => ["code" => 200, "msg" => "Messages Retrieved"],
        "body" => ["messages" => $messages]
    ]);
    exit();
}

/* ===============================================================
   3️⃣ MARK AS SEEN (Update delivered to seen for specified sender)
   ==============================================================*/
else if ($action === "seen" && isset($obj->customer_id) && isset($obj->sender)) {
    $cid = $obj->customer_id;
    $sender = $obj->sender;

    $stmt = $conn->prepare("
        UPDATE chat_messages 
        SET status = 'seen'
        WHERE customer_id = ? AND sender = ? AND status = 'delivered'
    ");
    $stmt->bind_param("ss", $cid, $sender);
    $stmt->execute();

    echo json_encode(["head" => ["code" => 200, "msg" => "Seen Updated"]]);
    exit();
}

/* ===============================================================
   4️⃣ DELIVERED (Update sent to delivered for specified sender)
   ==============================================================*/
else if ($action === "deliver" && isset($obj->customer_id) && isset($obj->sender)) {
    $cid = $obj->customer_id;
    $sender = $obj->sender;

    $stmt = $conn->prepare("
        UPDATE chat_messages 
        SET status = 'delivered'
        WHERE customer_id = ? AND sender = ? AND status = 'sent'
    ");
    $stmt->bind_param("ss", $cid, $sender);
    $stmt->execute();

    echo json_encode(["head" => ["code" => 200, "msg" => "Delivered updated"]]);
    exit();
}

/* ===============================================================
   ❌ INVALID ACTION
   ==============================================================*/
echo json_encode(["head" => ["code" => 400, "msg" => "Invalid action"]]);
exit();
