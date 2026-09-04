<?php
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function redirect($url){header('Location: '.$url); exit;}
function csrf_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf(){ if($_SERVER['REQUEST_METHOD']==='POST'){ $t=$_POST['csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??''; if(!$t || !hash_equals($_SESSION['csrf']??'', $t)){http_response_code(419); exit('CSRF validation failed');}}}
function json_response($data,$status=200){http_response_code($status);header('Content-Type: application/json');echo json_encode($data);exit;}
function client_ip(){return $_SERVER['REMOTE_ADDR']??'0.0.0.0';}
function rate_limit_key($key){$k='rl_'.sha1($key);$now=time();$a=$_SESSION[$k]??[];$a=array_values(array_filter($a,fn($x)=>$x>$now-60)); if(count($a)>=60)return false;$a[]=$now;$_SESSION[$k]=$a;return true;}
function secure_random($n=32){return bin2hex(random_bytes($n));}
