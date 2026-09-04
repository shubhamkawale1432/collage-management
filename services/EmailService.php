<?php
class EmailService{public static function send($to,$subject,$body){$from=$GLOBALS['app']['mail_from'];$headers="From: {$from}
Content-Type: text/html; charset=UTF-8
";return @mail($to,$subject,$body,$headers);}}
