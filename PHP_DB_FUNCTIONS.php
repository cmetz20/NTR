<?php
date_default_timezone_set('America/Phoenix');
require_once('/home/scripts/OrderIntake/CreateWeeblyRSTicket.php');
require_once('/home/scripts/OrderIntake/CreateEbayRSTicket.php');
require_once('/home/scripts/OrderIntake/CreateBonanzaRSTicket.php');
$config = parse_ini_file("/home/scripts/config.ini");

function addToEbayDB($order){
	$servername = "localhost";
	$username = $config["db_username"];
	$password = $config["db_password"];
	$dbname = $config["db_name"];
	$conn = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$order_id = $order->OrderID;
	$ticket_number = 0000;
	// order_id is a primary, unique key
	// no duplicate order_id will be permitted in DB
	$user_id = $orderStatus = $order->BuyerUserID;
	$user_id = str_replace("'", "''", $user_id);
	//echo $user_id . "";
	if ($orderStatus) {
		$shippingAddress = $order->ShippingAddress;
		$name = $shippingAddress->Name;
		$name = str_replace("'", "''", $name);
		
		if ($shippingAddress->Street1 != null) {
			$address =  $shippingAddress->Street1;
		}
		if ($shippingAddress->Street2 != null) {
			$address .=  $shippingAddress->Street2;
		}
		if ($shippingAddress->CityName != null) {
			$city = 
					$shippingAddress->CityName;
		}
		if ($shippingAddress->StateOrProvince != null) {
			$state = 
					$shippingAddress->StateOrProvince;
		}
		if ($shippingAddress->PostalCode != null) {
			$zip = 
					$shippingAddress->PostalCode;
		}
		if ($shippingAddress->CountryName != null) {
			$country = 
					$shippingAddress->CountryName;
		}
		if ($shippingAddress->Phone != null) {
			$phone =  $shippingAddress->Phone . "\n";
		}
		if(strcmp($phone, "Invalid Request") == 0){
			$phone = "0000000000";
		}
		$date_purchased = $order->CreatedTime;
		$position = strpos($date_purchased, "T");
		$date_purchased = substr($date_purchased, 0,$position);
		$transactions = $order->TransactionArray;
		if ($transactions) {
			// iterate through each transaction for the order
			$i = 1;
			foreach ($transactions->Transaction as $transaction) {
				// get the buyer's email
				$contact_email = $transaction->Buyer->Email;
				$order_description = $transaction->Item->Title;
				$quantity = $transaction->QuantityPurchased;
				$order_id_temp = $order_id;
				if(sizeof($transactions->Transaction) > 1){
					// multiple transactions with same order #
					// need to add something to order # to add to DB
					$order_id_temp = $order_id . "(" .  $i . ")";
					$i++;
				}
				
				$sql = "INSERT INTO ebay_orders 
					(
					name,
					user_id,
					contact_email,
					address,
					city,
					state,
					zip,
					country,
					phone,
					order_id,
					order_description,
					quantity,
					date_purchased,
					ticket_number
					)
					VALUES (
					'$name',
					'$user_id',
					'$contact_email',
					'$address',
					'$city',
					'$state',
					'$zip',
					'$country',
					'$phone',
					'$order_id_temp',
					'$order_description',
					'$quantity',
					'$date_purchased',
					'$ticket_number'
					)";
				

				if ($conn->query($sql) === TRUE) {
				    $txt = date("Y-m-d") . " --- Customer name : $name --- order_id : $order_id has been added to ebay database...\n";
					if(strcmp($country, "United States") != 0){
						// Country is something other than United States
						// Can add to database, but ticket will need to be manually created
						$txt .= "\t Customer is outside US, need manual customer/ticket creation.";
						$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
					}
					else{
					    // Country is US. Adding to database then proceeding to create RepairShopr ticket
					    $txt .= "\t Customer in US, adding to DB, then creating RS ticket.";
						$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
						// now create RS ticket
						$name = str_replace("''", "'", $name);
						$user_id = str_replace("''", "'", $user_id);
						
						createEbayRSTicket($name, $user_id, $contact_email, $address, $city, $state, $zip, $country, $phone, $order_id_temp, $order_description, $quantity, $date_purchased);
					}
				}
				else if(mysqli_errno($conn) != 1062){
				    echo "MYSQL Error ::" .  mysqli_errno($conn) . ": " . mysqli_error($conn) . "\n";
				}
			}
		}
	}
	
	
}


function updateEbayDB($order_id, $ticket_number){
	$servername = "localhost";
	$username = $config["db_username"];
	$password = $config["db_password"];
	$dbname = $config["db_name"];
	$conn = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$sql = "UPDATE ebay_orders SET ticket_number='$ticket_number' WHERE order_id='$order_id'";
	
	if ($conn->query($sql) === TRUE) {
		$txt = date('Y-m-d H:i:s') . "- Added ticket# " . $ticket_number . " to ebay database with order_id = " . $order_id . "\n- - - - - - - - - - - - - - - - - - - -";
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
	}
}


function addToBonanzaDB($order){
	$servername = "localhost";
	$username = $config["db_username"];
	$password = $config["db_password"];
	$dbname = $config["db_name"];
	$conn = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	} 
	$ticket_number = 0000;
	if($order){
		$order = $order['order'];
		$name = $order['shippingAddress']['name'];
		$user_id = $order['buyerUserName'];
		$contact_email = $order['transactionArray']['transaction']['buyer']['email'];
		$address = $order['shippingAddress']['street1'];
		$address .= $order['shippingAddress']['street2'];
		$city = $order['shippingAddress']['cityName'];
		$state = $order['shippingAddress']['stateOrProvince'];
		$zip = $order['shippingAddress']['postalCode'];
		$country = $order['shippingAddress']['country'];
		$phone = "0000000000";
		$order_id = $order['orderID'];
		$order_description = $order['itemArray'][0]['item']['title'];
		$quantity = $order['itemArray'][0]['item']['quantity'];
		$date_purchased = $order['createdTime'];

		
	}
	$sql = "INSERT INTO bonanza_orders 
		(
		name,
		user_id,
		contact_email,
		address,
		city,
		state,
		zip,
		country,
		phone,
		order_id,
		order_description,
		quantity,
		ticket_number,
		date_purchased
		)
		VALUES (
		'$name',
		'$user_id',
		'$contact_email',
		'$address',
		'$city',
		'$state',
		'$zip',
		'$country',
		'$phone',
		'$$order_id',
		'$order_description',
		'$quantity',
		'$$ticket_number',
		'$date_purchased'
		)";
	if ($conn->query($sql) === TRUE) {
		$txt = date('Y-m-d H:i:s') . "- Added new entry to bonanza database for user: " . $user_id . " for order: " . $order_description;
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		if(strcmp($country, "US") != 0){
			// Country is something other than United States
			// Can add to database, but ticket will need to be manually created
			$txt = "\t Customer is outside US, need manual customer/ticket creation.";
			$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		}
		else{
			// now create RS ticket
			createBonanzaRSTicket($name, $user_id, $contact_email, $address, $city, $state, $zip, $country, $order_id, $order_description, $quantity, $date_purchased);
		}
		
	} else if(mysqli_errno($conn) != 1062){
		$txt = date('Y-m-d H:i:s') . "- New entry failed for user: " . $user_id . " for order: " . $order_description . " errno:" . mysqli_errno($conn);
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
	}
}

function updateBonanzaDB($order_id, $ticket_number){
	$servername = "localhost";
	$username = $config["db_username"];
	$password = $config["db_password"];
	$dbname = $config["db_name"];
	$conn = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$sql = "UPDATE bonanza_orders SET ticket_number='$ticket_number' WHERE order_id='$order_id'";
	
	if ($conn->query($sql) === TRUE) {
		$txt = date('Y-m-d H:i:s') . "- Added ticket# " . $ticket_number . " to bonanza database with order_id = " . $order_id . "\n- - - - - - - - - - - - - - - - - - - -";
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
	}
}


function addToWeeblyDB($order_id, $name, $address, $email, $phone, $order_description, $quantity){
	$servername = "localhost";
	$username = $config["db_username"];
	$password = $config["db_password"];
	$dbname = $config["db_name"];
	$conn = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	// order_id is a primary, unique key
	$ticket_number = 0000;
	$address = trim($address);
	$email = trim($email);
	$order_id = trim($order_id);
	$order_description = trim($order_description);
	$quantity = trim($quantity);
	
	
	$date_purchased = time();
	$contact_email = $email;
	$name = trim($name);
	$names = preg_split("/ /", $name);
	$first_name = $names[0];
	$last_name = "";
	$i = 1;
	while ($i < sizeof($names)){
		$last_name .= $names[$i] . " ";
		$i++;
	}
	
	
	
	$sql = "INSERT INTO weebly_orders 
		(
		name,
		contact_email,
		address,
		phone,
		order_description,
		quantity,
		ticket_number,
		date_purchased,
		order_id
		)
		VALUES (
		'$name',
		'$contact_email',
		'$address',
		'$phone',
		'$order_description',
		'$quantity',
		'$ticket_number',
		'$date_purchased',
		'$order_id'
		)";
	

	if ($conn->query($sql) === TRUE) {
		$txt = date('Y-m-d H:i:s') . "- Added new entry to weebly database for: " . $name . " for order: " . $order_description;
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		createWeeblyRSTicket($first_name, $last_name, $name, $contact_email, $address, $order_description,$phone, $quantity, $order_id);
		
	} else if(mysqli_errno($conn) != 1062){
		$txt = "New entry failed for user: " . $name . " for order: " . $order_description . "\n" . mysqli_errno($conn);
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
	}
}

function updateWeeblyDB($order_id, $ticket_number){
	$servername = "localhost";
	$username = $config["db_username"];
	$password = $config["db_password"];
	$dbname = $config["db_name"];
	$conn = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$sql = "UPDATE weebly_orders SET ticket_number='$ticket_number' WHERE order_id='$order_id'";
	
	if ($conn->query($sql) === TRUE) {
		$txt = date('Y-m-d H:i:s') . "- Added ticket# " . $ticket_number . " to weebly database with order_id = " . $order_id . "\n- - - - - - - - - - - - - - - - - - - -";
		$myfile = file_put_contents('/home/logs/script-logs/get-orders-current-month-log.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
	}
}


?>