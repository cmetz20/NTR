<?php
	require_once('/home/scripts/OrderIntake/PHP_DB_FUNCTIONS.php');
	require_once('/home/scripts/OrderIntake/get-common/keys.php');
	require_once('/home/scripts/OrderIntake/get-common/eBaySession.php');
	$siteID = 0;
	$verb = 'GetOrders';

	date_default_timezone_set('America/Phoenix');
	$date = date('Y-m-d', time());
	$date = strtotime('+24 hour' , strtotime($date));
	$date = date('Y-m-d', $date);

	// Set this time to see how far back to look
	// Set to 168 hrs = 1 week
	// 336 hrs = 2 weeks
	$earlierDate = strtotime('-168 hour', strtotime($date));
	$earlierDate = date('Y-m-d', $earlierDate);
	//Time with respect to GMT
	$CreateTimeFrom = $earlierDate;
	$CreateTimeTo = $date;

	///Build the request Xml string
	$requestXmlBody = '<?xml version="1.0" encoding="utf-8" ?>';
	$requestXmlBody .= '<GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
	$requestXmlBody .= '<DetailLevel>ReturnAll</DetailLevel>';
	$requestXmlBody .= "<CreateTimeFrom>$CreateTimeFrom</CreateTimeFrom><CreateTimeTo>$CreateTimeTo</CreateTimeTo>";
	$requestXmlBody .= '<OrderRole>Seller</OrderRole><OrderStatus>All</OrderStatus>';
	$requestXmlBody .= "<RequesterCredentials><eBayAuthToken>$userToken</eBayAuthToken></RequesterCredentials>";
	$requestXmlBody .= '</GetOrdersRequest>';

	//Create a new eBay session with all details pulled in from included keys.php
	$session = new eBaySession($userToken, $devID, $appID, $certID, $serverUrl, $compatabilityLevel, $siteID, $verb);

	//send the request and get response
	$responseXml = $session->sendHttpRequest($requestXmlBody);
	if (stristr($responseXml, 'HTTP 404') || $responseXml == ''){
		die('<P>Error sending request');
	}

	//Xml string is parsed and creates a DOM Document object
	$responseDoc = new DomDocument();
	$responseDoc->loadXML($responseXml);


	//get any error nodes
	$errors = $responseDoc->getElementsByTagName('Errors');
	$response = simplexml_import_dom($responseDoc);
	$entries = $response->PaginationResult->TotalNumberOfEntries;
    
	//if there are error nodes
	if ($errors->length > 0) {
		echo '<P><B>eBay returned the following error(s):</B>';
		//display each error
		//Get error code, ShortMesaage and LongMessage
		$code = $errors->item(0)->getElementsByTagName('ErrorCode');
		$shortMsg = $errors->item(0)->getElementsByTagName('ShortMessage');
		$longMsg = $errors->item(0)->getElementsByTagName('LongMessage');
		
		//Display code and shortmessage
		echo '<P>', $code->item(0)->nodeValue, ' : ', str_replace(">", "&gt;", str_replace("<", "&lt;", $shortMsg->item(0)->nodeValue));
		
		//if there is a long message (ie ErrorLevel=1), display it
		if (count($longMsg) > 0)
			echo '<BR>', str_replace(">", "&gt;", str_replace("<", "&lt;", $longMsg->item(0)->nodeValue));
	}else { //If there are no errors, continue
		if ($entries == 0) {
			echo "No entries found in the Time period requested.";
		} else {
			$orders = $response->OrderArray->Order;
			if ($orders != null) {
				foreach ($orders as $order) {
					addToEbayDB($order);
				}
			}
		}
	}
?>