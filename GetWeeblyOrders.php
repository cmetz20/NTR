<?php
require_once('/home/scripts/OrderIntake/PHP_DB_FUNCTIONS.php');
/* connect to gmail */
$config = parse_ini_file("/home/scripts/config.ini");
$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
$username = $config['guser'];
$encrypt_method = "AES-256-CBC";
$secret_key = $config['gkey'];
$secret_iv = $config['giv'];
$string = $config['gpass'];
$key = hash('sha256', $secret_key);
$iv = substr(hash('sha256', $secret_iv), 0, 16);
$password = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);

/* try to connect */
$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to Gmail: ' . imap_last_error());

/* grab emails */
$emails = imap_search($inbox, 'SUBJECT "Order #" UNSEEN');
$email_count = 0;
/* if emails are returned, cycle through each... */
if(!$emails){
	echo "no new emails";
}
if($emails) {
	
	/* begin output var */
	$output = '';
	
	/* for every email... */
	$name = "";
	$email = "";
	foreach($emails as $email_number) {
		$email_count += 1;
		/* get information specific to this email */
		$message = imap_fetchbody($inbox,$email_number,1);
		
		/* output the email body */
		$output = $message;
		// Remove all '=' characters
		$clean_output = preg_replace('~=~', '', $output);
		
		$output = preg_replace('~\n~', '', $clean_output);
		$output = preg_replace('~\r~', '', $output);
		$output = preg_replace('~>~', '> ', $output);
		$output = preg_replace('~<~', ' <', $output);
		$word_array = preg_split('/>|</',$output);
		$i = 0;
		$order_number;
		$first_name;
		$last_name;
		$email;
		$address = "";
		$phone;
		$quantity;
		$order_description = "";
		$ready_for_shipping_info = false;
		$shipping_info_processed = false;
		$ready_for_country = false;
		$ready_for_city = false;
		$span_found = false;
		$order_info_processed = false;
		$ready_for_order_info = false;
		$shipping_skip_count = 0;
		$info_skip_count = 0;
		while($i < sizeof($word_array)){
			$word = $word_array[$i];
			//echo $word . "\n";
			if(strpos($word, "Order No.") !== false){
				if(strlen($word) < 40)
					$order_number = $word;
			}
			
			if(!$shipping_info_processed){
				if(strcmp($word, " Shipping Information ") == 0){
					if(strcmp($word_array[$i+1],'/span') == 0){
						// reached section of shipping information
						$ready_for_shipping_info = true;
					}
				}
			}
			
			if(!$order_info_processed){
				if(strcmp($word, " Order Summary ") == 0){
					if(strcmp($word_array[$i+1],'/span') == 0){
						// reached section of order summary
						$ready_for_order_info = true;
					}
				}
			}
			
			if($ready_for_order_info){
				if(strcmp($word,'p class3D"t-commerce-bold t-commerce-name t-font-style--subtitle" style3D"font-size: 16px; line-height: 24px; margin: 0; padding-bottom: 0; font-weight: 700; color: #666c70;"') == 0){
					if(strcmp($word_array[$i+2], "span") == 0){
						$order_description = $word_array[$i+3];
					}
				}
				if(strpos($word, "Quantity") !== false){
					$quantity = $word;
					$ready_for_order_info = false;
					$order_info_processed = true;
				}	

			}

			if($ready_for_shipping_info){
				if(strcmp($word, 'p style3D"font-size: 0.9em; line-height: inherit; margin: 0; padding-bottom: 0; color: inherit;"') == 0){
					$shipping_skip_count++;
					if($shipping_skip_count == 1){
						$name = trim($word_array[$i+1]);
					}
					
					else if($shipping_skip_count == 2){
						$address .= trim($word_array[$i+1]);
					}
					else{
						$address .= " " . trim($word_array[$i+1]);
					}
					
				}
				if(strcmp($word,"span") == 0){
					$ready_for_shipping_info = false;
					$ready_for_city = true;
				}
			}
			if($ready_for_city){
				if(strcmp($word, 'p style3D"font-size: 0.9em; line-height: inherit; margin: 0; padding-bottom: 0; color: inherit;"') == 0){
					//City/state finished, now grab country
					$ready_for_city = false;
					$ready_for_country = true;
				}
				if(strcmp($word,"span") == 0){
					$address .= $word_array[$i+1] . " ";
				}
			}
			if($ready_for_country){				
				if(strcmp($word, 'p style3D"font-size: 0.9em; line-height: inherit; margin: 0; padding-bottom: 0; color: inherit;"') == 0){
					$info_skip_count++;
					if($info_skip_count == 1){
						$address .= $word_array[$i+1];
					}
					if($info_skip_count == 2){
						$email = $word_array[$i+1];
					}
					if($info_skip_count == 3){
						$phone = $word_array[$i+1];
						$ready_for_country = false;
						$shipping_info_processed = true;
					}
				}
			}	

			$i += 1;
		}

		$pattern = "/Quantity: /";
		$quantity = preg_replace($pattern, '', $quantity);
		$pattern = "/Order No. /";
		$order_number = preg_replace($pattern, '', $order_number);
		$pattern = "/-| |(|)/";
		$phone = preg_replace($pattern, '', $phone);
		addToWeeblyDB($order_number, $name, $address, $email, $phone, $order_description, $quantity);
	}
} 

/* close the connection */
imap_close($inbox);

?>