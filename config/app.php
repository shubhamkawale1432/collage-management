<?php
return [
 'name'=>'Digital College Operating System 2077',
 'base_url'=>'/college_management',
 'timezone'=>'Asia/Kolkata',
 'session_timeout'=>3600,
 'upload_max_bytes'=>20*1024*1024,
 'ai_provider'=>getenv('CMS_AI_PROVIDER') ?: 'local',
 'ai_api_key'=>getenv('CMS_AI_API_KEY') ?: '',
 'mail_from'=>getenv('CMS_MAIL_FROM') ?: 'no-reply@college.local',
 'demo_mode'=>true,
];
