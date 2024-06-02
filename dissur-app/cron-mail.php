<?php
$to = 'inux2012@gmail.com';
$subject = "Test cron and mail";
$message = "Yes, its alive!";
mail($to, $subject, $message);