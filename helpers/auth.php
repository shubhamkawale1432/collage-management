<?php
function current_user(){return $_SESSION['user']??null;}
function login_user($u){session_regenerate_id(true);$_SESSION['user']=$u;$_SESSION['last_activity']=time();}
function logout_user(){$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy();}
function require_login(){if(!current_user()){redirect((defined('BASE_URL')?BASE_URL:'').'/auth/login.php');}if(time()-($_SESSION['last_activity']??time())>($GLOBALS['app']['session_timeout']??3600)){logout_user();redirect((defined('BASE_URL')?BASE_URL:'').'/auth/login.php?expired=1');}$_SESSION['last_activity']=time();}
function has_role(...$roles){return current_user() && in_array(current_user()['role_name'],$roles,true);}
function require_role(...$roles){require_login();if(!has_role(...$roles)){http_response_code(403);exit('Forbidden');}}
function can($perm){$u=current_user();if(!$u)return false;if(in_array($u['role_name'],['Super Admin'],true))return true;$q=db()->prepare('SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id JOIN roles r ON r.id=rp.role_id WHERE r.name=? AND p.name=? LIMIT 1');$q->execute([$u['role_name'],$perm]);return (bool)$q->fetchColumn();}
function require_perm($perm){require_login();if(!can($perm)){http_response_code(403);exit('Permission denied');}}
function actor_id(){return current_user()['id']??null;}
