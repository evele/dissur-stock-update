<?php

require __DIR__ . '/vendor/autoload.php';
use Automattic\WooCommerce\Client;

$whitelist = array(
  '127.0.0.1',
  '::1'
);

define('DO_NOT_UPDATE_PRICE', [3337872413728,8429420126252,8429420126251,8429420126254]);


if(!in_array($_SERVER['REMOTE_ADDR'], $whitelist)){
  // not valid
  echo 'desde acá no pillín';
} else {*/

  // Conexión WooCommerce API destino
  // ================================
  /* local 
  $url_API_woo = 'http://wordpress-test.com/';
  $ck_API_woo = 'ck_a4d3d5977643295a39a1543e9bcf5d206e52e567';
  $cs_API_woo = 'cs_14c29d53e0e53ac09185ff42cbabcdb5041eaa00'; */
  /* online */

  
  $woocommerce = new Client(
      $url_API_woo,
      $ck_API_woo,
      $cs_API_woo,
      ['version' => 'wc/v3',
      'verify_ssl' => true]
  );
  
  $productos = [];
  $page_full = 100;
  $page_x = 1;
  while ($page_full==100){
    $parameters = ['per_page' => 100,
                  'page' => $page_x,
                  //'include'=> [14409]
                  'include'=> [16345]
                ];
    $productos_in_page_x = $woocommerce->get('products',$parameters);
    $productos = array_merge($productos_in_page_x,$productos); 
    $page_full = count($productos_in_page_x);
    //var_dump($page_x);
    $page_x++;
  }
  
  $array_of_skus = []; 
  $map_sku_id = [];
  $map_id_stock = [];
  foreach ($productos as $p){
    if ($p->sku!=''){
      $array_of_skus[] = floatval($p->sku) ;
      $map_sku_id[floatval($p->sku)] = $p->id;
      $map_id_stock[$p->id] = $p->manage_stock;
    
    }
  }

  /* echo '<pre>';
  print_r($productos);
  echo '</pre>';

   echo '<pre>';
      print_r($array_of_skus);
      echo '</pre>'; */
  
  $curl = curl_init();

  $farma_user = "lala_u";
  $farma_pass = "lala_p";
  
  //$params = json_encode(['username' => $farma_user,'password' => $farma_pass,'codigo' => '7793640000747']);
  //$params = json_encode(['username' => $farma_user,'password' => $farma_pass,'codigos' => ['7793640000747','7794640172601']]);
  $params = json_encode(['username' => $farma_user,'password' => $farma_pass,'codigos' => $array_of_skus]);
  
  /*
   echo '<pre>';
    echo 'params ';

      print_r($params);
      echo '</pre>';
	*/
  curl_setopt_array($curl, array(
    CURLOPT_URL => "https://www.drogueriasur.com.ar/dsapi/articulos/search.json",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
	CURLOPT_SSL_VERIFYHOST => false,
	CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POSTFIELDS =>$params,
    CURLOPT_HTTPHEADER => array(
      "Content-Type: application/json"
    ),
  )); 
  
  $productos_drogueria = json_decode(curl_exec($curl),true);
  curl_close($curl);
  /*
  $r = curl_exec($curl);
  echo 'productos_drogueria otro ';
  var_dump($r);
  echo '<pre>';
  //print_r($productos_drogueria);
  print_r($r);
  echo '</pre>';
  echo '<pre>';
  echo 'curl info ';
  print_r(curl_getinfo($curl));
  echo '</pre>';
  */

  $items_data = [];
  
  $param_sku ='';
  foreach ($productos_drogueria as $producto){
    foreach ($producto as $p){
      //var_dump($p['codigo_barras']);
      $id = get_id($p,$map_sku_id);
      $item_data = ['id' => $id];

      if (update_price($p)) {
          //$item_data['regular_price'] = 200;
          $item_data['regular_price'] = $p['iva']==1?floatval($p['precio_farmacia'])*1.7:floatval($p['precio_farmacia'])*1.4;
          //'regular_price' => $p['iva']==1?floatval($p['precio_farmacia'])*1.7:floatval($p['precio_farmacia'])*1.4,
      }
      if ($map_id_stock[$id]!=1){
          $item_data['stock_status'] = map_stock($p['stock']);
      }
      $items_data[] = $item_data;
    }    
  }
  
  
  $items_data_chunks = array_chunk($items_data,80);
  foreach($items_data_chunks as $item_data){
      $data = [
        'update' => $item_data,
      ];
     // var_dump($data);
      sleep(3);
      $result = false;
      //$result = $woocommerce->post('products/batch', $data);
      /*echo '<pre>';
      print_r($result);
      echo '</pre>'; */
      if (! $result) {
        print("❗Error al actualizar productos \n");
        write_log("❗Error al actualizar productos \n");
  		mail('jsepulveda@xulum.com', 'NUAGES - api update failed', 'sep palmó otra vez, sonamos :(');
      } else {
        write_log("✔ Productos actualizados correctamente \n");
        print("✔ Productos actualizados correctamente \n");
      }
  }
} // close del else



function write_log($text){
  
  $mes = date('Y-m');
  $dia_hora = date('H:i');
  $logfile = fopen($mes."-cron.log", "a") or die("Unable to open file!");
  fwrite($logfile, $dia_hora.' - '.$text);
  fclose($logfile); 

}

//var_dump($items_data_chunks);


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

function get_id($p,$map_sku_id){
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


