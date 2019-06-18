<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '/home/scripts/PHPMailer/src/Exception.php';
require_once '/home/scripts/PHPMailer/src/PHPMailer.php';
require_once '/home/scripts/PHPMailer/src/SMTP.php';

if (!function_exists('emailCustomerProfile')) {
function emailCustomerProfile($contact_email, $RS_user_url){

	$config = parse_ini_file("/home/scripts/config.ini");
	$mail = new PHPMailer;
	$mail->isSMTP(); // Set mailer to use SMTP
	$mail->SMTPAuth = true; // Enable SMTP authentication

	$mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
	$mail->Username = $config['guser'];    // SMTP username
	
	$encrypt_method = "AES-256-CBC";
	$secret_key = $config['gkey'];
	$secret_iv = $config['giv'];
	$string = $config['gpass'];
	$key = hash('sha256', $secret_key);
	$iv = substr(hash('sha256', $secret_iv), 0, 16);
	$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
	
	$mail->Password = $output;  // SMTP password
	$mail->SMTPSecure = 'starttls';   // Enable encryption, 'ssl' also accepted
	$mail->Port = 587;  

	$mail->addAddress($contact_email);
	$mail->From = "noreply.nickstvs@gmail.com";
	$mail->FromName = "Nick's TV Repair";
	$mail->Subject = "Nick's TV Repair Customer Profile";
	$mail->Body    = 'Please send your board(s) to the following address:  


Nick\'s TV Repair

ATTN: Repairs  

1230 E Pennsylvania St

STE 101  

Tucson, AZ 85714  

USA  


When shipping your board(s), use a box large enough to allow 1"  of space around each edge of the board(s). Make sure to fill this extra space with enough bubble wrap as to prevent the board(s) from moving inside the box. If the board(s) gets damaged during shipping, we may not be able to repair it/them. 

Enclosed with the board, please attach a description of the following:  

*Original symptoms with the TV.  
*Any attempted repair such as re-flow or component level repair
*With a sharpie/marker, write your user name (if you ordered through an online auction site) on the board(s)
*Please include your ticket # if you were provided with one (check link below)
*Please forward us the tracking number once you have shipped the board(s)

Feel free to contact us with any questions you may have at our support email support@nickstvs.com

Below is a link to your personalized customer profile and ticket within our repair software. This will provide updates such as when we receive your circuit board, what stage of the repair it is in, and what our technicians are looking into. 

Please follow the link to your customer profile and find the ticket for your repair service. 
After making your way to your open ticket, please enter your board\'s issues & symptoms into the "New Ticket Comment" field and press the "Add Note to Ticket" button. 
The more thorough you can be, the better we can diagnose, repair, and return your circuit board back to you.' . "

" . $RS_user_url .
"

 
-Nick's TV Repair";

	$mail->Send();
}
}
?>