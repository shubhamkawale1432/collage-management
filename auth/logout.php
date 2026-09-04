<?php
require_once __DIR__.'/../includes/bootstrap.php';if(current_user())AuditService::log('LOGOUT','auth',actor_id(),'Logout');logout_user();redirect('login.php');