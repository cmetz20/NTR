<?php
require_once('/home/scripts/OrderIntake/PHP_DB_FUNCTIONS.php');
global $config = parse_ini_file("/home/scripts/config.ini");
# $dev_name = developer name (ID)
# $cert_name = certificate name (ID)
function BonapititSecureApiCall($dev_name, $cert_name, $api_call_and_args) {
  $url = "https://api.bonanza.com/api_requests/secure_request";
  $headers = array("X-BONANZLE-API-DEV-NAME: " . $dev_name, "X-BONANZLE-API-CERT-NAME: " . $cert_name);
  $resp = BonapititSendHttpRequest($url,$headers, $api_call_and_args);
  return $resp;
}

# $dev_name = developer ID
# $api_call_and_args - string of the form: api_call={json_data}
function BonapititStandardApiCall($dev_name, $api_call_and_args) {
  $url = "https://api.bonanza.com/api_requests/standard_request";
  $headers = array("X-BONANZLE-API-DEV-NAME: " . $dev_name);
  $resp = BonapititSendHttpRequest($url,$headers, $api_call_and_args);
  return $resp;
}

# $url - string - url of the bonanza api
# $headers - string - http headers containing X-BONANZLE-API-DEV-NAME and/or X-BONANZLE-API-CERT-NAME
# $post_fields - string - data that will be posted to the $url.
function BonapititSendHttpRequest($url, $headers, $post_fields) {
  $connection = curl_init();
  curl_setopt($connection, CURLOPT_URL, $url);
  curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);  //stop CURL from verifying the peer's certificate
  curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($connection, CURLOPT_HTTPHEADER, $headers); //set the headers using the array of headers
  curl_setopt($connection, CURLOPT_POST, 1);  //set method as POST
  curl_setopt($connection, CURLOPT_POSTFIELDS, $post_fields);
  curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);  //set it to return the transfer as a string from curl_exec
  $response = curl_exec($connection);
  curl_close($connection);
  return $response;
}

# $api_call_name - string - like UpdateBooth
# $assoc_array - array - associative array of data
function BonapititBuildRequest($api_call_name, $assoc_array) {
  $request_name = $api_call_name . "Request";
  $json = json_encode($assoc_array, JSON_HEX_AMP);
  $request = $request_name . "=" .  $json;
  return $request;
}

$dev_name = $config["dev_name"];
$cert_name = $config["cert_name"];
$token = $config["token"];

date_default_timezone_set('America/Phoenix');
$date = date('Y-m-d', time());
$date = strtotime('+24 hour' , strtotime($date));
$date = date('Y-m-d', $date);

// Set this time to see how far back to look
// Set to 168 hrs = 1 week
$earlierDate = strtotime('-168 hour', strtotime($date));
$earlierDate = date('Y-m-d', $earlierDate);

// Now make a call to getOrders with seller's token
$args = array();
$args['requesterCredentials']['bonanzleAuthToken'] = $token;
$args['orderRole'] = 'seller';
$args['soldTimeFrom'] = $earlierDate;
$args['soldTimeTo'] = $date;
$request = BonapititBuildRequest('getOrders',$args);
$response = BonapititSecureApiCall($dev_name, $cert_name, $request);
$response = json_decode($response,true);
//var_dump($response);
$orders = $response['getOrdersResponse']['orderArray'];
foreach ($orders as $order){
	addToBonanzaDB($order);
}
?>