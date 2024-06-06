<?php
$to = 'inux2012@gmail.com';
$subject = "Test cron and mail";
$message = "Yes, its alive! ".php_sapi_name();
mail($to, $subject, $message);