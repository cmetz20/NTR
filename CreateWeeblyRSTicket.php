<?php
include('/home/scripts/OrderIntake/SendAutomatedEmail.php');
include_once('/home/scripts/OrderIntake/PHP_DB_FUNCTIONS.php');
global $RS_user_id;
global $RS_user_url;
global $RS_ticket_number;
global $config = parse_ini_file("/home/scripts/config.ini");
date_default_timezone_set('America/Phoenix');
if (!function_exists('CheckIfWeeblyCustomerExists')) {
	function checkIfWeeblyCustomerExists($first_name, $last_name, $contact_email){
		global $RS_user_id;
		global $RS_user_url;
		$curl = curl_init();
		if(!$first_name || !$last_name){
			echo "No name given, returning\n";
			return;
		}
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $config["repairshopr_url"] . $first_name . "+" . $last_name . $config["api_key"]
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
if (!function_exists('createNewWeeblyCustomer')) {
	function createNewWeeblyCustomer($first_name,$last_name,$contact_email,$address,$phone){
		global $RS_user_id;
		global $RS_user_url;
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $config["repairshopr_url"] . $config["api_key"],
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array(
				'firstname' => $first_name,
				'lastname' => $last_name,
				'email' => $contact_email,
				'address' => $address,
				'phone' => $phone
			)
		));
		$resp = curl_exec($curl);
		$resp = json_decode($resp, true);
		//var_dump($resp);
		$RS_user_id = $resp['customer']['id'];
		$RS_user_url = $resp['customer']['online_profile_url'];
		
	}
}
if (!function_exists('createNewWeeblyTicket')) {
	function createNewWeeblyTicket($order_description){
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
				'subject' => "Website - " . $order_description,
				'ticket_type_name' => "Online Repair Service"
			)
		));
		$resp = curl_exec($curl);
		$resp = json_decode($resp, true);
		$RS_ticket_number = $resp['ticket']['number'];
		curl_close($curl);
	}
}

function createWeeblyRSTicket($first_name, $last_name, $name, $contact_email, $address, $order_description,$phone,$quantity, $order_id){
	global $RS_user_id;
	global $RS_user_url;
	global $RS_ticket_number;
	if(!checkIfWeeblyCustomerExists($first_name, $last_name, $contact_email)){
		// If customer does NOT exist
		$txt = date('Y-m-d H:i:s') . "- no customer found for both " . $name . " or " . $contact_email;
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		createNewWeeblyCustomer($first_name,$last_name,$contact_email,$address,$phone);
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
			createNewWeeblyTicket($temp_description);
		}
		else{
			createNewWeeblyTicket($order_description);
		}
		$txt = date('Y-m-d H:i:s') . "- Weebly Ticket Created for order with quantity = ". $quantity;
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		$i++;
	}
	updateWeeblyDB($order_id,$RS_ticket_number);
	emailCustomerProfile($contact_email, $RS_user_url);
}


?>