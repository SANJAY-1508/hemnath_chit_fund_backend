<?php

include 'db/config.php';
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
header( 'Access-Control-Allow-Headers: Content-Type' );

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'OPTIONS' ) {
    exit();
}

$json = file_get_contents( 'php://input' );
$obj = json_decode( $json );
$output = array();

date_default_timezone_set( 'Asia/Calcutta' );
$timestamp = date( 'Y-m-d H:i:s' );

if (isset( $obj->search_text ) ) {
    $sql = 'SELECT * FROM `bank_details` WHERE `delete_at`=0';
    $result = $conn->query( $sql );
    if ( $result->num_rows > 0 ) {
        $count = 0;
         while ( $row = $result->fetch_assoc() ) {
            if ( $row[ 'qr_code_img' ] != null && $row[ 'qr_code_img' ] != 'null' && strlen( $row[ 'qr_code_img' ] ) > 0 ) {
                $imgLink = 'https://' . $_SERVER[ 'SERVER_NAME' ] . '/zenchitbilling/uploads/bank_qr_codes/' . $row[ 'qr_code_img' ];
                $row[ 'qr_code_img' ] = $imgLink;
            }
            $output[ 'body' ][ 'bank_details' ][] = $row;
        }
    } else {
        $output[ 'head' ][ 'code' ] = 200;
        $output[ 'head' ][ 'msg' ] = 'Bank Details Not Found';
        $output[ 'body' ][ 'bank_details' ] = [];
    }

    $output[ 'head' ][ 'code' ] = 200;
    $output[ 'head' ][ 'msg' ] = 'Success';
}
//Create Bank Details
else if ( isset( $obj->current_user_id ) && isset( $obj->image_url ) && isset( $obj->bank_details ) ) {
    $current_user_id = $conn->real_escape_string($obj->current_user_id);
    $image_url = $conn->real_escape_string($obj->image_url);
    $bank_details = $conn->real_escape_string($obj->bank_details);
    $upi_id = $conn->real_escape_string(isset($obj->upi_id) ? $obj->upi_id : ''); 
    $created_name = $conn->real_escape_string(isset($obj->created_name) ? $obj->created_name : '');
    $outputFilePath = '../uploads/bank_qr_codes/'; // Changed folder name for bank details context

    if ( !file_exists( $outputFilePath ) ) {
        mkdir( $outputFilePath, 0777, true );
    }

    $profile_path = pngImageToWebP( $image_url, $outputFilePath );


    $createBankDetails = "INSERT INTO `bank_details` 
        (`qr_code_img`, `upi_id`, `bank_details`, `delete_at`, `created_by`, `created_name`, `created_date`) 
        VALUES (
            '$profile_path',
            '$upi_id',
            '$bank_details', 
            0,
            '$current_user_id', 
            '$created_name',
            '$timestamp'
        )";

    if ( $conn->query( $createBankDetails ) ) {
        $output[ 'head' ][ 'code' ] = 200;
        $output[ 'head' ][ 'msg' ] = 'Successfully Bank Details Created';
    } else {
        $output[ 'head' ][ 'code' ] = 400;
        $output[ 'head' ][ 'msg' ] = 'Failed to Added. Please try again. Error: ' . $conn->error;
    }
}

//Update Bank Details
else if ( isset( $obj->bank_details_id ) && isset( $obj->current_user_id ) && isset( $obj->bank_details ) ) {
    $bank_details_id = $conn->real_escape_string($obj->bank_details_id);
    $current_user_id = $conn->real_escape_string($obj->current_user_id); 
    $bank_details = $conn->real_escape_string($obj->bank_details);
    $upi_id = $conn->real_escape_string(isset($obj->upi_id) ? $obj->upi_id : ''); 
    
    if ( isset( $obj->image_url ) ) {
        $image_url = $conn->real_escape_string($obj->image_url);
        $outputFilePath = '../uploads/bank_qr_codes/'; 

        if ( !file_exists( $outputFilePath ) ) {
            mkdir( $outputFilePath, 0777, true );
        }

        $profile_path = pngImageToWebP( $image_url, $outputFilePath );
        $image_update_sql = "`qr_code_img` = '$profile_path',";
    } else {
        $image_update_sql = ""; // No image update if image_url is missing
    }

    $updateBankDetails = "UPDATE `bank_details` SET 
        $image_update_sql
        `upi_id` = '$upi_id',
        `bank_details` = '$bank_details'
        WHERE `id` = '$bank_details_id'";

    if ( $conn->query( $updateBankDetails ) ) {
        $output[ 'head' ][ 'code' ] = 200;
        $output[ 'head' ][ 'msg' ] = 'Successfully Bank Details Updated';
    } else {
        $output[ 'head' ][ 'code' ] = 400;
        $output[ 'head' ][ 'msg' ] = 'Failed to Update. Please try again. Error: ' . $conn->error;
    }
}
// ------------------------------------------

else if ( isset( $obj->bank_details_id ) && isset( $obj->current_user_id ) ) {
    
    // The incoming JSON property name is 'bank_details_id'
    $bank_details_id = $conn->real_escape_string($obj->bank_details_id);
    $current_user_id = $conn->real_escape_string($obj->current_user_id);
    
    $deleteBankDetails = "UPDATE `bank_details` SET `delete_at`=1 WHERE `id`='$bank_details_id'"; 

    if ( $conn->query( $deleteBankDetails ) ) {
        $output[ 'head' ][ 'code' ] = 200;
        $output[ 'head' ][ 'msg' ] = 'Successfully Bank Details Deleted !';
    } else {
        $output[ 'head' ][ 'code' ] = 400;
        $output[ 'head' ][ 'msg' ] = 'Failed to Delete. Please try again. Error: ' . $conn->error;
    }
}

else {
    $output[ 'head' ][ 'code' ] = 400;
    $output[ 'head' ][ 'msg' ] = 'Parameter is Mismatch';
}

echo json_encode( $output, JSON_NUMERIC_CHECK );


?>