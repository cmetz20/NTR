<?php
include('/home/scripts/OrderIntake/SendAutomatedEmail.php');
include_once('/home/scripts/OrderIntake/PHP_DB_FUNCTIONS.php');
global $RS_user_id;
global $RS_user_url;
global $RS_ticket_number;
global $config = parse_ini_file("/home/scripts/config.ini");
date_default_timezone_set('America/Phoenix');
if (!function_exists('CheckIfEbayCustomerExists')) {
	function checkIfEbayCustomerExists($user_id, $contact_email){
		global $RS_user_id;
		global $RS_user_url;
		$curl = curl_init();
		if(!$user_id){
			echo "No username given, returning\n";
			return;
		}
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $config["repairshopr_url"] . $user_id . $config["api_key"]
		));
		// Send the request & save response to $resp
		$resp = curl_exec($curl);
		$resp = json_decode($resp, true);
		if($resp){
			if($resp['customers']){
				if(sizeof($resp['customers']) != 0){
					$RS_user_id = $resp['customers'][0]['id'];
					$RS_user_url = $resp['customers'][0]['online_profile_url'];
					return true;
				}
			}
		}
		
		// No customer found yet
		// Check based on email address now
		$curl = curl_init();
		if(!$contact_email){
			echo "No email given, returning\n";
			return;
		}
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $config["repairshopr_url"] . $contact_email . $config["api_key"]
		));
		$resp = curl_exec($curl);
		$resp = json_decode($resp, true);
		if($resp){
			if($resp['customers']){
				if(sizeof($resp['customers']) != 0){
					$RS_user_id = $resp['customers'][0]['id'];
					$RS_user_url = $resp['customers'][0]['online_profile_url'];
					return true;
				}
			}
		}
		else{
			return false;
		}
	}
}
if (!function_exists('createNewEbayCustomer')) {
	function createNewEbayCustomer($user_id,$name,$contact_email,$address,$city,$state,$zip,$phone){
		global $RS_user_id;
		global $RS_user_url;
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $config["repairshopr_url"] . $config["api_key"],
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array(
				'firstname' => $user_id,
				'lastname' => $name,
				'email' => $contact_email,
				'address' => $address,
				'city' => $city,
				'state' => $state,
				'zip' => $zip,
				'phone' => $phone
			)
		));
		$resp = curl_exec($curl);
		$resp = json_decode($resp, true);
		$RS_user_id = $resp['customer']['id'];
		$RS_user_url = $resp['customer']['online_profile_url'];
	}
}
if (!function_exists('createNewEbayTicket')) {
	function createNewEbayTicket($order_description){
		global $RS_user_id;
		global $RS_user_url;
		global $RS_ticket_number;
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $config["repairshopr_url"] . $config["api_key"],
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array(
				'status' => "Awaiting Package Arrival",
				'problem_type' => "Online Repair Service",
				'customer_id' => $RS_user_id,
				'subject' => "Ebay - " . $order_description,
				'ticket_type_name' => "Online Repair Service"
			)
		));
		$resp = curl_exec($curl);
		$resp = json_decode($resp, true);
		$RS_ticket_number = $resp['ticket']['number'];
		curl_close($curl);
	}
}
function createEbayRSTicket($name, $user_id, $contact_email, $address, $city, $state, $zip, $country, $phone, $order_id, $order_description, $quantity, $date_purchased){
	global $RS_user_id;
	global $RS_user_url;
	global $RS_ticket_number;
	if(!checkIfEbayCustomerExists($user_id, $contact_email)){
		// If customer does NOT exist
		$txt = date('Y-m-d H:i:s') . "- no customer found for both " . $user_id . " or " . $contact_email;
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		createNewEbayCustomer($user_id,$name,$contact_email,$address,$city,$state,$zip,$phone);
	}
	else{
		$txt = date('Y-m-d H:i:s') . "- customer found";
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
	}
	//$RS_user_id now holds RS customer's numerical ID
	$i = 0;
	while($i < $quantity){
		if($quantity > 1){
			$temp_description = $order_description . " (" . $i . " of " . $quantity . ")";
			createNewEbayTicket($temp_description);
		}
		else{
			createNewEbayTicket($order_description);
		}
		$txt = date('Y-m-d H:i:s') . "- Ebay Ticket Created for order with quantity = ". $quantity;
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		$i++;
	}
	// now email customer their customer profile
	updateEbayDB($order_id,$RS_ticket_number);
	emailCustomerProfile($contact_email, $RS_user_url);
	
}

?>