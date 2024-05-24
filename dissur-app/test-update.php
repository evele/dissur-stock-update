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


//try {
//php_sapi_name()!='cli'
if(false){
    // not valid
    echo 'desde acá no pillín';
  } else {

    $conn_woocommerce = connect_woocommerce(url_API_woo, ck_API_woo, cs_API_woo);
    
    $productos = get_products_woocommerce($conn_woocommerce, $productos);
   // var_dump($productos);

    foreach($productos as $p){
      if($p->sku != ""){
        $i=0;
        //var_dump($p);
        $sku_productos_arr[] = $p->sku;
        $id_productos_sku_map[$p->sku] = $p->id;
        //$p->id;
       // var_dump($p->id);
        
        $stock_id_productos_map[$p->id] = $p->manage_stock;
      }
    }
    //var_dump( $id_productos_sku_map);

    //-------------- acomodar if(res) o (error)
  // $drogueria_productos = connect_drogueriasur($sku_productos_arr);

   $drogueria_productos = array(
 
      array(
          "stock" => "S",
          "descripcion" => "TOMMY H.TOMMY MEN 100ml EDT",
          "categoria" => 5,
          "troquel" => "24324",
          "codigo_barras" => "",
          "codigo_barras2" => "022548024324",
          "codigo_barras3" => "022548024324",
          "trazable" => false,
          "msd" => false,
          "iva" => true,
          "pack" => false,
          "cadena_frio" => false,
          "precio_farmacia" => "91780.86"
        ),
      array(
          "stock" => "S",
          "descripcion" => "C.HERRERA 212 VIP BLACK.M 100",
          "categoria" => 5,
          "troquel" => "69376",
          "codigo_barras" => "8411061043844",
          "codigo_barras2" => NULL,
          "codigo_barras3" => "8411061869376",
          "trazable" => false,
          "msd" => false,
          "iva" => true,
          "pack" => false,
          "cadena_frio" => false,
          "precio_farmacia" => "82408.54"
        )
      
    );
   
   
    foreach($sku_productos_arr as $p_woo_sku){
      
      foreach($drogueria_productos as $p_ds){

        if($p_woo_sku == $p_ds['codigo_barras'] or $p_woo_sku == $p_ds['codigo_barras2'] or $p_woo_sku == $p_ds['codigo_barras3'] ){
          $item_data = ['id' => $id_productos_sku_map[$p_woo_sku] ];
          if (!in_array(floatval($p_woo_sku), DO_NOT_UPDATE_PRICE)) {
            $item_data['regular_price'] = $p_ds['iva']==true?floatval($p_ds['precio_farmacia'])*$iva*$ganancia:floatval($p_ds['precio_farmacia'])*$ganancia; 
            //$p['iva']==1?floatval($p['precio_farmacia'])*1.7:floatval($p['precio_farmacia'])*1.4;
            
          }
          if (!$stock_id_productos_map[$id_productos_sku_map[$p_woo_sku]]) {
            $item_data['stock_status'] = map_stock($p_ds['stock']);
          }
        } //cierre if sku vs codigos barra
        
      }// cierre foreach drogueria
      $items_data[] = $item_data;
    }
    
    $items_data_chunks = array_chunk($items_data,50);
    update_products($items_data_chunks, $conn_woocommerce);

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
      //var_dump($productos);
      $page_x++;
    }

    return $products;
}

function connect_drogueriasur($sku_arr){
  $drogueria_productos = [];

  if (count($sku_arr)>0){
    $curl = curl_init();
    
    //$params = json_encode(['username' => $farma_user,'password' => $farma_pass,'codigo' => '7793640000747']);
    //$params = json_encode(['username' => $farma_user,'password' => $farma_pass,'codigos' => ['7793640000747','7794640172601']]);
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
  echo "ID que devuelvo /-/-/-/-/-/-/-/-/-/-/ \r\n";
  var_dump($id);
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

function update_products($items_data_chunks, $woocommerce){
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
        mail('jsepulveda@xulum.com', 'NUAGES - api update failed', 'sep palmó otra vez, sonamos :( - error al escribir el archivo u alguna otra cosa');
        mail('inux2012@gmail.com', 'NUAGES - api update failed', 'sep palmó otra vez, sonamos :( - error al escribir el archivo u alguna otra cosa');
      } else {
        mail('grossoanalaura@gmail.com', 'NUAGES - api update failed', 'sep palmó otra vez, sonamos :( - error al escribir el archivo u alguna otra cosa');
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

/*
-----------PRODUCTOS DROGUERÍA -------------
array(1) { ["res"]=> array(2) { 
  [0]=> array(13) 
    { ["stock"]=> string(1) "S" 
      ["descripcion"]=> string(27) "TOMMY H.TOMMY MEN 100ml EDT" 
      ["categoria"]=> int(5) 
      ["troquel"]=> string(5) "24324" 
      ["codigo_barras"]=> string(11) "22548024324" 
      ["codigo_barras2"]=> string(12) "022548024324" 
      ["codigo_barras3"]=> string(12) "022548024324" 
      ["trazable"]=> bool(false) 
      ["msd"]=> bool(false) 
      ["iva"]=> bool(true) 
      ["pack"]=> bool(false) 
      "cadena_frio"]=> bool(false) 
      ["precio_farmacia"]=> string(8) "91780.86" 
    } 
  [1]=> array(13) 
    { ["stock"]=> string(1) "S" 
      ["descripcion"]=> string(29) "C.HERRERA 212 VIP BLACK.M 100" 
      ["categoria"]=> int(5) 
      ["troquel"]=> string(5) "69376" 
      ["codigo_barras"]=> string(13) "8411061043844" 
      ["codigo_barras2"]=> NULL 
      ["codigo_barras3"]=> string(13) "8411061869376" 
      ["trazable"]=> bool(false) 
      ["msd"]=> bool(false) 
      ["iva"]=> bool(true) 
      ["pack"]=> bool(false) 
      ["cadena_frio"]=> bool(false) 
      ["precio_farmacia"]=> string(8) "82408.54" 
    } } } */

/*
["stock_status"]=> string(10) "outofstock"


array(3) { [0]=> object(stdClass)#10 (69) { ["id"]=> int(15) 
                                            ["name"]=> string(4) "Otro" 
                                            ["slug"]=> string(4) "otro" 
                                            ["permalink"]=> string(30) "http://localhost/product/otro/" 
                                            ["date_created"]=> string(19) "2024-05-15T20:56:01" 
                                            ["date_created_gmt"]=> string(19) "2024-05-15T20:56:01" 
                                            ["date_modified"]=> string(19) "2024-05-15T21:55:02" 
                                            ["date_modified_gmt"]=> string(19) "2024-05-15T21:55:02" 
                                            ["type"]=> string(6) "simple" 
                                            ["status"]=> string(7) "publish" 
                                            ["featured"]=> bool(false) 
                                            ["catalog_visibility"]=> string(7) "visible" 
                                            ["description"]=> string(0) "" 
                                            ["short_description"]=> string(0) "" 
                                            ["sku"]=> string(11) "22548024324" 
                                            ["price"]=> string(11) "166582.2609" 
                                            ["regular_price"]=> string(11) "166582.2609" 
                                            ["sale_price"]=> string(0) "" 
                                            ["date_on_sale_from"]=> NULL 
                                            ["date_on_sale_from_gmt"]=> NULL 
                                            ["date_on_sale_to"]=> NULL 
                                            ["date_on_sale_to_gmt"]=> NULL 
                                            ["on_sale"]=> bool(false) 
                                            ["purchasable"]=> bool(true) 
                                            ["total_sales"]=> int(0) 
                                            ["virtual"]=> bool(false) 
                                            ["downloadable"]=> bool(false) 
                                            ["downloads"]=> array(0) { } 
                                            ["download_limit"]=> int(-1) 
                                            ["download_expiry"]=> int(-1) 
                                            ["external_url"]=> string(0) "" 
                                            ["button_text"]=> string(0) "" 
                                            ["tax_status"]=> string(7) "taxable" 
                                            ["tax_class"]=> string(0) "" 
                                            ["manage_stock"]=> bool(false) 
                                            ["stock_quantity"]=> NULL 
                                            ["backorders"]=> string(2) "no" 
                                            ["backorders_allowed"]=> bool(false) 
                                            ["backordered"]=> bool(false) 
                                            ["low_stock_amount"]=> NULL 
                                            ["sold_individually"]=> bool(false) 
                                            ["weight"]=> string(0) "" 
                                            ["dimensions"]=> object(stdClass)#11 (3) { ["length"]=> string(0) "" ["width"]=> string(0) "" ["height"]=> string(0) "" } 
                                            ["shipping_required"]=> bool(true) 
                                            ["shipping_taxable"]=> bool(true) 
                                            ["shipping_class"]=> string(0) "" 
                                            ["shipping_class_id"]=> int(0) 
                                            ["reviews_allowed"]=> bool(true) 
                                            ["average_rating"]=> string(4) "0.00" 
                                            ["rating_count"]=> int(0) 
                                            ["upsell_ids"]=> array(0) { } 
                                            ["cross_sell_ids"]=> array(0) { } 
                                            ["parent_id"]=> int(0) 
                                            ["purchase_note"]=> string(0) "" 
                                            ["categories"]=> array(1) { [0]=> object(stdClass)#12 (3) { ["id"]=> int(15) ["name"]=> string(13) "Uncategorized" ["slug"]=> string(13) "uncategorized" } } ["tags"]=> array(0) { } ["images"]=> array(0) { } ["attributes"]=> array(1) { [0]=> object(stdClass)#13 (7) { ["id"]=> int(1) ["name"]=> string(6) "Update" ["slug"]=> string(9) "pa_update" ["position"]=> int(0) ["visible"]=> bool(true) ["variation"]=> bool(false) ["options"]=> array(1) { [0]=> string(1) "1" } } } ["default_attributes"]=> array(0) { } ["variations"]=> array(0) { } ["grouped_products"]=> array(0) { } ["menu_order"]=> int(0) ["price_html"]=> string(139) "$ 166.582,26" ["related_ids"]=> array(2) { [0]=> int(14) [1]=> int(12) } ["meta_data"]=> array(0) { } ["stock_status"]=> string(7) "instock" ["has_options"]=> bool(false) ["post_password"]=> string(0) "" ["_links"]=> object(stdClass)#15 (2) { ["self"]=> array(1) { [0]=> object(stdClass)#14 (1) { ["href"]=> string(42) "http://localhost/wp-json/wc/v3/products/15" } } ["collection"]=> array(1) { [0]=> object(stdClass)#16 (1) { ["href"]=> string(39) "http://localhost/wp-json/wc/v3/products" } } } } [1]=> object(stdClass)#17 (69) { ["id"]=> int(14) ["name"]=> string(5) "nuevo" ["slug"]=> string(5) "nuevo" ["permalink"]=> string(31) "http://localhost/product/nuevo/" ["date_created"]=> string(19) "2024-05-15T20:47:06" ["date_created_gmt"]=> string(19) "2024-05-15T20:47:06" ["date_modified"]=> string(19) "2024-05-15T20:54:47" ["date_modified_gmt"]=> string(19) "2024-05-15T20:54:47" ["type"]=> string(6) "simple" ["status"]=> string(7) "publish" ["featured"]=> bool(false) ["catalog_visibility"]=> string(7) "visible" ["description"]=> string(0) "" ["short_description"]=> string(0) "" ["sku"]=> string(13) "3614272642225" ["price"]=> string(3) "110" ["regular_price"]=> string(3) "110" ["sale_price"]=> string(0) "" ["date_on_sale_from"]=> NULL ["date_on_sale_from_gmt"]=> NULL ["date_on_sale_to"]=> NULL ["date_on_sale_to_gmt"]=> NULL ["on_sale"]=> bool(false) ["purchasable"]=> bool(true) ["total_sales"]=> int(0) ["virtual"]=> bool(false) ["downloadable"]=> bool(false) ["downloads"]=> array(0) { } ["download_limit"]=> int(-1) ["download_expiry"]=> int(-1) ["external_url"]=> string(0) "" ["button_text"]=> string(0) "" ["tax_status"]=> string(7) "taxable" ["tax_class"]=> string(0) "" ["manage_stock"]=> bool(false) ["stock_quantity"]=> NULL ["backorders"]=> string(2) "no" ["backorders_allowed"]=> bool(false) ["backordered"]=> bool(false) ["low_stock_amount"]=> NULL ["sold_individually"]=> bool(false) ["weight"]=> string(0) "" ["dimensions"]=> object(stdClass)#18 (3) { ["length"]=> string(0) "" ["width"]=> string(0) "" ["height"]=> string(0) "" } ["shipping_required"]=> bool(true) ["shipping_taxable"]=> bool(true) ["shipping_class"]=> string(0) "" ["shipping_class_id"]=> int(0) ["reviews_allowed"]=> bool(true) ["average_rating"]=> string(4) "0.00" ["rating_count"]=> int(0) ["upsell_ids"]=> array(0) { } ["cross_sell_ids"]=> array(0) { } ["parent_id"]=> int(0) ["purchase_note"]=> string(0) "" ["categories"]=> array(1) { [0]=> object(stdClass)#19 (3) { ["id"]=> int(15) ["name"]=> string(13) "Uncategorized" ["slug"]=> string(13) "uncategorized" } } ["tags"]=> array(0) { } ["images"]=> array(0) { } ["attributes"]=> array(1) { [0]=> object(stdClass)#20 (7) { ["id"]=> int(1) ["name"]=> string(6) "Update" ["slug"]=> string(9) "pa_update" ["position"]=> int(1) ["visible"]=> bool(true) ["variation"]=> bool(false) ["options"]=> array(1) { [0]=> string(1) "1" } } } ["default_attributes"]=> array(0) { } ["variations"]=> array(0) { } ["grouped_products"]=> array(0) { } ["menu_order"]=> int(0) ["price_html"]=> string(135) "$ 110,00" ["related_ids"]=> array(2) { [0]=> int(15) [1]=> int(12) } ["meta_data"]=> array(0) { } ["stock_status"]=> string(7) "instock" ["has_options"]=> bool(false) ["post_password"]=> string(0) "" ["_links"]=> object(stdClass)#22 (2) { ["self"]=> array(1) { [0]=> object(stdClass)#21 (1) { ["href"]=> string(42) "http://localhost/wp-json/wc/v3/products/14" } } ["collection"]=> array(1) { [0]=> object(stdClass)#23 (1) { ["href"]=> string(39) "http://localhost/wp-json/wc/v3/products" } } } } [2]=> object(stdClass)#24 (69) { ["id"]=> int(12) ["name"]=> string(15) "Algún producto" ["slug"]=> string(14) "algun-producto" ["permalink"]=> string(40) "http://localhost/product/algun-producto/" ["date_created"]=> string(19) "2024-05-03T19:28:18" ["date_created_gmt"]=> string(19) "2024-05-03T19:28:18" ["date_modified"]=> string(19) "2024-05-15T21:55:02" ["date_modified_gmt"]=> string(19) "2024-05-15T21:55:02" ["type"]=> string(6) "simple" ["status"]=> string(7) "publish" ["featured"]=> bool(false) ["catalog_visibility"]=> string(7) "visible" ["description"]=> string(0) "" ["short_description"]=> string(0) "" ["sku"]=> string(13) "8411061869376" ["price"]=> string(11) "149571.5001" ["regular_price"]=> string(11) "149571.5001" ["sale_price"]=> string(0) "" ["date_on_sale_from"]=> NULL ["date_on_sale_from_gmt"]=> NULL ["date_on_sale_to"]=> NULL ["date_on_sale_to_gmt"]=> NULL ["on_sale"]=> bool(false) ["purchasable"]=> bool(true) ["total_sales"]=> int(0) ["virtual"]=> bool(false) ["downloadable"]=> bool(false) ["downloads"]=> array(0) { } ["download_limit"]=> int(-1) ["download_expiry"]=> int(-1) ["external_url"]=> string(0) "" ["button_text"]=> string(0) "" ["tax_status"]=> string(7) "taxable" ["tax_class"]=> string(0) "" ["manage_stock"]=> bool(false) ["stock_quantity"]=> NULL ["backorders"]=> string(2) "no" ["backorders_allowed"]=> bool(false) ["backordered"]=> bool(false) ["low_stock_amount"]=> NULL ["sold_individually"]=> bool(false) ["weight"]=> string(0) "" ["dimensions"]=> object(stdClass)#25 (3) { ["length"]=> string(0) "" ["width"]=> string(0) "" ["height"]=> string(0) "" } ["shipping_required"]=> bool(true) ["shipping_taxable"]=> bool(true) ["shipping_class"]=> string(0) "" ["shipping_class_id"]=> int(0) ["reviews_allowed"]=> bool(true) ["average_rating"]=> string(4) "0.00" ["rating_count"]=> int(0) ["upsell_ids"]=> array(0) { } ["cross_sell_ids"]=> array(0) { } ["parent_id"]=> int(0) ["purchase_note"]=> string(0) "" ["categories"]=> array(1) { [0]=> object(stdClass)#26 (3) { ["id"]=> int(15) ["name"]=> string(13) "Uncategorized" ["slug"]=> string(13) "uncategorized" } } ["tags"]=> array(0) { } ["images"]=> array(0) { } ["attributes"]=> array(1) { [0]=> object(stdClass)#27 (7) { ["id"]=> int(1) ["name"]=> string(6) "Update" ["slug"]=> string(9) "pa_update" ["position"]=> int(0) ["visible"]=> bool(false) ["variation"]=> bool(false) ["options"]=> array(1) { [0]=> string(1) "0" } } } ["default_attributes"]=> array(0) { } ["variations"]=> array(0) { } ["grouped_products"]=> array(0) { } ["menu_order"]=> int(0) ["price_html"]=> string(139) "$ 149.571,50" ["related_ids"]=> array(2) { [0]=> int(14) [1]=> int(15) } ["meta_data"]=> array(0) { } ["stock_status"]=> string(7) "instock" ["has_options"]=> bool(false) ["post_password"]=> string(0) "" ["_links"]=> object(stdClass)#29 (2) { ["self"]=> array(1) { [0]=> object(stdClass)#28 (1) { ["href"]=> string(42) "http://localhost/wp-json/wc/v3/products/12" } } ["collection"]=> array(1) { [0]=> object(stdClass)#30 (1) { ["href"]=> string(39) "http://localhost/wp-json/wc/v3/products" } } } } } 
                                                */