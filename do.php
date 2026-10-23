<?php
require 'phpmailer/PHPMailerAutoload.php';
require_once(__DIR__ . '/php/mail_config.php');
$mail = new PHPMailer;
configureAppMailer($mail);
$mail->addAddress('waqaspuri@gmail.com', 'Waqas Saeed Puri');
$mail->Subject  = 'First PHPMailer Message';
$mail->Body     = 'Hi! This is my first e-mail sent through PHPMailer.';
if(!$mail->send()) {
  echo 'Message was not sent.';
  echo 'Mailer error: ' . $mail->ErrorInfo;
} else {
  echo 'Message has been sent.';
}
?>
