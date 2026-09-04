<?php
if(session_status()===PHP_SESSION_NONE){$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax']);session_start();}
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');header('Referrer-Policy: strict-origin-when-cross-origin');header('Permissions-Policy: geolocation=(), microphone=(), camera=()');header('Cache-Control: no-store');
$app=require __DIR__.'/../config/app.php';date_default_timezone_set($app['timezone']);define('BASE_URL',$app['base_url']);
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../helpers/security.php';require_once __DIR__.'/../helpers/auth.php';require_once __DIR__.'/../helpers/ui.php';foreach(glob(__DIR__.'/../services/*.php') as $f)require_once $f;
