<?php 
$userName="923424859921";
$password="gbKg2i01bf2ExE1pG7";
$url="https://telenorcsms.com.pk:27677/corporate_sms2/api/auth.jsp?msisdn=". $userName . "&password=". $password;
$xml = simplexml_load_file($url);
$session_id = $xml->data; 

$sendSMSurl = "https://telenorcsms.com.pk:27677/corporate_sms2/api/sendsms.jsp?session_id=".$session_id."&to=923008207530&text=Hey There&mask=WEBSOLBIZ";

$sendSMS = file_get_contents($sendSMSurl); 

print_r($sendSMS); 


?>
