<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
  
    

require( './../wp-load.php' );

$user = get_user_by( 'id', 1 );
//var_dump($user);

//$all_meta_for_user = get_user_meta( 1, 'codigo_usuario' );
$all_meta_for_user = get_user_meta( 1 );
//var_dump($all_meta_for_user);


// get posted data
$data = json_decode(file_get_contents("php://input"));
//$myfile = fopen($mes."-cron-log.txt", "w") or die("Unable to open file!");
$myfile = fopen("order.txt", "a") or die("Unable to open file!");
//$txt = json_encode($data);
$txt = json_encode($all_meta_for_user);
fwrite($myfile, $txt);
fclose($myfile); 

  /*
// make sure data is not empty
if(
    !empty($data->name) &&
    !empty($data->price) &&
    !empty($data->description) &&
    !empty($data->category_id)
){
  
    // set product property values
    $product->name = $data->name;
    $product->price = $data->price;
    $product->description = $data->description;
    $product->category_id = $data->category_id;
    $product->created = date('Y-m-d H:i:s');
  
    // create the product
    if($product->create()){
  
        // set response code - 201 created
        http_response_code(201);
  
        // tell the user
        echo json_encode(array("message" => "Product was created."));
    }
  
    // if unable to create the product, tell the user
    else{
  
        // set response code - 503 service unavailable
        http_response_code(503);
  
        // tell the user
        echo json_encode(array("message" => "Unable to create product."));
    }
}
  
// tell the user data is incomplete
else{
  
    // set response code - 400 bad request
    http_response_code(400);
  
    // tell the user
    echo json_encode(array("message" => "Unable to create product. Data is incomplete."));
}
*/
?>