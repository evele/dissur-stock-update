<?php

//ini_set('max_execution_time', 240);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

include_once './api-data.php';
require __DIR__ . '/vendor/autoload.php';
use Automattic\WooCommerce\Client;



//try {
//php_sapi_name()!='cli'
 if(false){
    // not valid
    echo 'desde acá no pillín';
  } else {

   
    $values = infoJson();
    $iva = $values["IVA"];
    $ganancia = $values["ganancia"];
    define('DO_NOT_UPDATE_PRICE', $values["DO_NOT_UPDATE_PRICE"]);

     // Conexión WooCommerce API destino
    // ================================
    $woocommerce = new Client(
        url_API_woo,
        ck_API_woo,
        cs_API_woo,
        
        [
         //'wp_api' => true,
          'version' => 'wc/v3',
          //'oauth_only' => true,
          'query_string_auth' => true,
          'verify_ssl' => false,
          'timeout' => 400]
    );

    $productos = [];
    $page_full = 100;
    $page_x = 1;
    while ($page_full==100 /*&& $page_x <3*/){
      $parameters = ['per_page' => 100,
                    'page' => $page_x,
                     'order_by' =>'id'
                    //'include'=> [8556]
                  ];
      sleep(3);
      $productos_in_page_x = $woocommerce->get('products',$parameters);
      $productos = array_merge($productos_in_page_x,$productos);
      $page_full = count($productos_in_page_x);
      //var_dump($productos);
      $page_x++;
    }

    $array_of_skus = []; // to update
    $map_sku_id = [];
    $map_id_stock = [];
    foreach ($productos as $p){
      if ($p->sku!=''){
        
        $array_of_skus[] = $p->sku ;
        $map_sku_id[$p->sku] = $p->id;
        $map_id_stock[$p->id] = $p->manage_stock;
      }
    }
    //var_dump($array_of_skus);
    // la pagina tenía elementos
    if (count($array_of_skus)>0){
      $curl = curl_init();

      //$params = json_encode(['username' => $farma_user,'password' => $farma_pass,'codigo' => '7793640000747']);
      //$params = json_encode(['username' => $farma_user,'password' => $farma_pass,'codigos' => ['7793640000747','7794640172601']]);
      $params = json_encode(['username' => farma_user,'password' => farma_pass,'codigos' => $array_of_skus]);

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

      //var_dump($params);
      $productos_drogueria = json_decode(curl_exec($curl),true);
      //var_dump($productos_drogueria);
      //var_dump(curl_getinfo($curl));

      curl_close($curl);

      $items_data = [];

      $param_sku ='';
      foreach ($productos_drogueria as $producto){
        foreach ($producto as $p){
          echo "recorriendo p_ds";
          var_dump($p);
        	$id = get_id($p,$map_sku_id);
        	$item_data = ['id' => $id];
          if (update_price($p)) {
             $item_data['regular_price'] = $p['iva']==true?floatval($p['precio_farmacia'])*$iva*$ganancia:floatval($p['precio_farmacia'])*$ganancia; 
             //$p['iva']==1?floatval($p['precio_farmacia'])*1.7:floatval($p['precio_farmacia'])*1.4;
          }
          if ($map_id_stock[$id]!=1) {
            $item_data['stock_status'] = map_stock($p['stock']);
          }
          $items_data[] = $item_data;

        }
      }

      $items_data_chunks = array_chunk($items_data,50);
      $i = 1;
      foreach($items_data_chunks as $item_data)
      {
          $data = [
            'update' => $item_data,
          ];
          //var_dump($data);
          sleep(3);
          //set_time_limit(3);
          $result = $woocommerce->post('products/batch', $data);
          echo '<br>';
          if (! $result) {
            print("❗Error al actualizar productos ".$i."\n");
            write_log("❗Error al actualizar productos ".$i."\n");
            //mail('jsepulveda@xulum.com', 'NUAGES - api update failed', 'sep palmó otra vez, sonamos :( - error al escribir el archivo u alguna otra cosa');
            //mail('inux2012@gmail.com', 'NUAGES - api update failed', 'sep palmó otra vez, sonamos :( - error al escribir el archivo u alguna otra cosa');
          } else {
            write_log("✔ Productos actualizados correctamente ".$i."\n");
            print("✔ Productos actualizados correctamente ".$i."\n");
          }
          $i++;
          var_dump($i);
      }
    }
  } // fin else

/*} catch (Exception $e) {
   // mail('jsepulveda@xulum.com', 'NUAGES - api update failed error', 'sep palmó otra vez, sonamos :( '.$e);
    //mail('inux2012@gmail.com', 'NUAGES - api update failed error', 'sep palmó otra vez, sonamos :( '.$e);
}*/

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

//como devuelve el valor en cualquier de cualquier código de barras, tengo que chequear por cada uno, se asume que son exlcusivos y no se pisan con ningún producto

function get_id($p,$map_sku,$map_sku_id){
  $id = '';
  
  if (isset($map_sku_id[floatval($p['codigo_barras'])])){
    // si está el primero asumo que es ese y a la bosta
    $id = $map_sku_id[floatval($p['codigo_barras'])];
  } else { //chequeo el siguiente código
    if ($p['codigo_barras2']!=''){ //chequeo si hay código
      if (isset($map_sku_id[floatval($p['codigo_barras2'])])){
        $id = $map_sku_id[floatval($p['codigo_barras2'])];
      }
    } else {
      if (isset($map_sku_id[floatval($p['codigo_barras3'])])){
        $id = $map_sku_id[floatval($p['codigo_barras3'])];
      }
    }
  } // en teoría no llegamos con id vacío aca

  return $id;

}

function write_log($text){

  $mes = date('Y-m');
  $dia_hora = date('d H:i');
  $logfile = fopen("logs/".$mes."-cron.log", "a") or die("Unable to open file!");
  fwrite($logfile, $dia_hora.' - '.$text);
  fclose($logfile);


}

//otra chanchada por los distintos códigos de barras
function update_price($p){
  $result = true;
  if (in_array(floatval($p['codigo_barras']), DO_NOT_UPDATE_PRICE)){
    $result = false;
  } else { //chequeo el siguiente código
    if ($p['codigo_barras2']!=''){ //chequeo si hay código
      if (in_array(floatval($p['codigo_barras2']), DO_NOT_UPDATE_PRICE)){
        $result = false;
      } else {
        if ($p['codigo_barras3']!=''){
          if (in_array(floatval($p['codigo_barras3']), DO_NOT_UPDATE_PRICE)){
            $result = false;
          }
        }
      }
    } else {
      if ($p['codigo_barras3']!=''){
        if (in_array(floatval($p['codigo_barras3']), DO_NOT_UPDATE_PRICE)){
            $result = false;
        }
      }
    }
  }
  return $result;
}

//-------------------------------------------------------------------------------------------------------------
// traer info IVA y ganancia
function infoJson(){
  $data_json = 'data.json';

  if (file_exists($data_json)) {
    // Cargar el contenido del archivo JSON
    $json_data = file_get_contents($data_json);
    
    // Decodificar el contenido JSON en un array asociativo
    $data = json_decode($json_data, true);
    
    // Verificar si la decodificación fue exitosa
    if ($data !== null) {
        // Acceder a los valores del array asociativo
        return $data;
        
        
       // echo "IVA: $iva<br>";
       // echo "Ganancia: $ganancia<br>";
        
      
    } else {
        echo "Error al decodificar el archivo JSON.";
    }
  } else {
    echo "El archivo JSON no existe.";
  }
}
