<?php

include_once './api-data.php';
require __DIR__ . '/vendor/autoload.php';
use Automattic\WooCommerce\Client;
    
//ini_set('max_execution_time', 240);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

$productos = [];
$sku_productos_arr = [];
$id_productos_sku_map = [];
$stock_id_productos_map = [];
$items_data = [];
$values = infoJson();
define('DO_NOT_UPDATE_PRICE', $values["DO_NOT_UPDATE_PRICE"]);
$iva = $values["IVA"];
$ganancia = $values["ganancia"];
$emails = $values["emails"];


try {
//php_sapi_name()!='cli'
if(false){
    // not valid
    echo 'desde acá no pillín';
  } else {

    $conn_woocommerce = connect_woocommerce(url_API_woo, ck_API_woo, cs_API_woo);
    
    $productos = get_products_woocommerce($conn_woocommerce, $productos);
  

    foreach($productos as $p){
      if($p->sku != ""){
      
        $sku_productos_arr[] = $p->sku;
        $id_productos_sku_map[$p->sku] = $p->id;
        $stock_id_productos_map[$p->id] = $p->manage_stock;
      }
    }
    
    $response_data = connect_drogueriasur($sku_productos_arr);

    if (isset($response_data['res'])) {
      $drogueria_productos = $response_data['res'];
    } elseif (isset($response_data['error'])) {
        // Si existe la clave 'error', lanzar una excepción con el mensaje de error
        throw new Exception("Error: " . $response_data['error']['message']);
    } else {
        // Si no existe ni 'res' ni 'error', lanzar una excepción genérica
        throw new Exception("Respuesta inesperada de la API");
    }
  
   
    foreach($sku_productos_arr as $p_woo_sku){
      
      foreach($drogueria_productos as $p_ds){

        if($p_woo_sku == $p_ds['codigo_barras'] or $p_woo_sku == $p_ds['codigo_barras2'] or $p_woo_sku == $p_ds['codigo_barras3'] ){
          $item_data = ['id' => $id_productos_sku_map[$p_woo_sku] ];
          if (!in_array(floatval($p_woo_sku), DO_NOT_UPDATE_PRICE)) {
            $item_data['regular_price'] = $p_ds['iva']==true?floatval($p_ds['precio_farmacia'])*$iva*$ganancia:floatval($p_ds['precio_farmacia'])*$ganancia; 
          
            
          }
          if (!$stock_id_productos_map[$id_productos_sku_map[$p_woo_sku]]) {
            $item_data['stock_status'] = map_stock($p_ds['stock']);
          }
        } //cierre if sku vs codigos barra
        
      }// cierre foreach drogueria
      $items_data[] = $item_data;
    }
    
    $items_data_chunks = array_chunk($items_data,50);
    update_products($items_data_chunks, $conn_woocommerce, $emails);

  }
  } catch (Exception $e) {
    send_mails($emails,'Farmacia Ezcurra - api update failed error', 'sep palmó otra vez, sonamos :( '.$e);
   
}


//----------------------------------------------------------------------------------------------------------------
function connect_woocommerce($url, $ck, $cs){
    $woocommerce = new Client(
        $url,
        $ck,
        $cs,
        [
          'version' => 'wc/v3',
          'query_string_auth' => true,
          'verify_ssl' => false,
          'timeout' => 400]
    );
    return $woocommerce;
}

function get_products_woocommerce($conn){
    $products =[];
    $page_full = 100;
    $page_x = 1;
    while ($page_full==100 /*&& $page_x <3*/){
      $parameters = ['per_page' => 100,
                    'page' => $page_x,
                     'order_by' =>'id'
                    //'include'=> [8556]
                  ];
      sleep(3);
      $products_in_page_x = $conn->get('products',$parameters);
      $products = array_merge($products_in_page_x,$products);
      $page_full = count($products_in_page_x);

      $page_x++;
    }

    return $products;
}

function connect_drogueriasur($sku_arr){
  $drogueria_productos = [];

  if (count($sku_arr)>0){
    $curl = curl_init();
  
    $params = json_encode(['username' => farma_user,'password' => farma_pass,'codigos' => $sku_arr]);

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://www.drogueriasur.com.ar/dsapi/articulos/search.json",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_CONNECTTIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS =>$params,
      CURLOPT_HTTPHEADER => array(
        "Content-Type: application/json"
      ),
    ));

    $drogueria_productos = json_decode(curl_exec($curl),true);


    curl_close($curl);

    return $drogueria_productos;

  }
}

function get_id($p,$map_sku_id){
  $id = '';

  if (isset($map_sku_id[floatval($p['codigo_barras'])])){
 
 
    $id = $map_sku_id[floatval($p['codigo_barras'])];
  } else {
    if ($p['codigo_barras2']!=''){ 
      if (isset($map_sku_id[floatval($p['codigo_barras2'])])){
        $id = $map_sku_id[floatval($p['codigo_barras2'])];
      }
    } else {
      if (isset($map_sku_id[floatval($p['codigo_barras3'])])){
        $id = $map_sku_id[floatval($p['codigo_barras3'])];
      }
    }
  } 
  echo "ID que devuelvo /-/-/-/-/-/-/-/-/-/-/ \r\n";

  return $id;

}

function update_price($p){
  $result = true;

  if(in_array(floatval($p['codigo_barras']), DO_NOT_UPDATE_PRICE) or in_array(floatval($p['codigo_barras2']), DO_NOT_UPDATE_PRICE) or in_array(floatval($p['codigo_barras3']), DO_NOT_UPDATE_PRICE)){
    $result = false;
  }
  return $result;
 
}

function map_stock($stock_string) {
  $stock_status = 'outofstock';
  switch ($stock_string) {
    case 'S': $stock_status = 'instock';      //stock habitual
              break;
    case 'B': $stock_status = 'outofstock';   //stock bajo, confirme con su operadora
              break;
    case 'F': $stock_status = 'outofstock';   //producto en falta
              break;
    case 'D': $stock_status = 'outofstock';   //producto descontinuado -- por ahí se puede quitar
              break;
    case 'R': $stock_status = 'outofstock';   //producto sujeto a stock
              break;
  }
  return $stock_status;

}

function infoJson(){
  $data_json = 'data.json';

  if (file_exists($data_json)) {

    $json_data = file_get_contents($data_json);
    
    $data = json_decode($json_data, true);
    
    if ($data !== null) {
        return $data;
    } else {
        echo "Error al decodificar el archivo JSON.";
    }
  } else {
    echo "El archivo JSON no existe.";
  }
}

function update_products($items_data_chunks, $woocommerce, $mails){
  $i = 1;
 
  foreach($items_data_chunks as $item_data)
  {
      $data = [
        'update' => $item_data,
      ];
     
      sleep(3);

      $result = $woocommerce->post('products/batch', $data);

    
      if (! $result) {
        print("❗Error al actualizar productos ".$i."\n");
        write_log("❗Error al actualizar productos ".$i."\n");
        send_mails($mails,'Farmacia Ezcurra - api update failed', 'sep palmó otra vez, sonamos :( - error al escribir el archivo u alguna otra cosa');
       } else {
        send_mails($mails,'Farmacia Ezcurra - api update WORKED', 'Mails funcionando');
        write_log("✔ Productos actualizados correctamente ".$i."\n");
        print("✔ Productos actualizados correctamente ".$i."\n");
      }
      $i++;
  ;
  }
}

function write_log($text){
  $mes = date('Y-m');
  $dia_hora = date('d H:i');
  $logfile = fopen("logs/".$mes."-cron.log", "a") or die("Unable to open file!");
  fwrite($logfile, $dia_hora.' - '.$text);
  fclose($logfile);
}

function send_mails($mails, $asunto, $msg){
  $recipients = array_filter($mails, fn($email) => !empty($email));

if (!empty($recipients)) {
    $to = implode(',', $recipients);
    $subject = $asunto;
    $message = $msg;
    //$headers = "From: remitente@example.com";

    // Enviar el correo
    if (mail($to, $subject, $message)) {
        echo "Correo enviado correctamente a: $to";
    } else {
        echo "Error al enviar el correo.";
    }
} else {
    echo "No hay destinatarios válidos para enviar el correo.";
}
}
