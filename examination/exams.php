<?php
require_once __DIR__.'/../includes/bootstrap.php';require_role('Examination Officer','Faculty','Super Admin','College Admin');redirect('../admin/entity.php?e=exams');