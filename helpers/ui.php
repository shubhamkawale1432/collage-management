<?php
declare(strict_types=1);

function page_top(string $title = 'DCOS 2077', bool $fluid = true): void
{
    global $app;
    $u = current_user() ?? [];
    $base = rtrim((string)($app['base_url'] ?? ''), '/');
    $pageTitle = $title . ' · ' . ($app['name'] ?? 'Digital College OS');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
        <meta name="theme-color" content="#4f46e5">
        <meta name="color-scheme" content="light dark">
        <title><?= e($pageTitle) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script defer src="<?= e($base) ?>/assets/js/app.js"></script>
    </head>
    <body>
    <div class="app-shell">
        <div class="sidebar-overlay" data-sidebar-overlay></div>
        <aside class="sidebar" id="sidebar">
            <div class="brand-wrap"><div class="brand-mark"><i class="fa-solid fa-atom" aria-hidden="true"></i></div><div><div class="brand">DCOS <span>2077</span></div><div class="brand-mini">DIGITAL CAMPUS OS</div></div></div>
            <?php nav_menu(); ?>
        </aside>
        <section class="content">
            <nav class="topbar" aria-label="Application navigation">
                <button class="nav-toggle" type="button" data-sidebar-toggle aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
                <div class="global-search"><label class="visually-hidden" for="globalSearch">Search</label><input id="globalSearch" class="form-control form-control-sm" type="search" placeholder="Search authorized records…" autocomplete="off"></div>
                <div class="ms-auto d-flex align-items-center gap-2"><button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle theme"><i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i></button><a class="icon-btn" href="<?= e($base) ?>/student/notifications.php" aria-label="Notifications"><i class="fa-regular fa-bell" aria-hidden="true"></i></a><div class="user-chip d-none d-sm-flex"><strong><?= e((string)($u['name'] ?? 'Guest')) ?></strong><small><?= e((string)($u['role_name'] ?? '')) ?></small></div><a href="<?= e($base) ?>/auth/logout.php" class="btn btn-sm btn-outline-danger">Logout</a></div>
            </nav>
            <main class="container-fluid py-4<?= $fluid ? '' : ' px-0' ?>"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="page-title mb-1"><?= e($title) ?></h1><div class="small text-muted"><?= e(date('d M Y · H:i')) ?></div></div></div>
    <?php
}

function page_bottom(): void
{
    global $app;
    ?>
            </main><footer class="text-center small text-muted py-4">Digital College Operating System · <?= e((string)($app['name'] ?? 'DCOS 2077')) ?></footer>
        </section>
    </div></body></html>
    <?php
}

function nav_menu(): void
{
    global $app;
    $base = rtrim((string)($app['base_url'] ?? ''), '/');
    $role = (string)(current_user()['role_name'] ?? '');
    $sets = [
        'Super Admin'=>['/admin/index.php'=>'Command Center','/admin/modules.php'=>'All Modules','/admin/entity.php?e=users'=>'Users','/admin/entity.php?e=students'=>'Students','/admin/entity.php?e=faculty'=>'Faculty','/admin/entity.php?e=departments'=>'Departments','/admin/entity.php?e=subjects'=>'Subjects','/admin/admissions.php'=>'Admissions & Enrollment','/admin/entity.php?e=applications'=>'Applications','/admin/entity.php?e=exams'=>'Exams','/admin/entity.php?e=timetables'=>'Timetables','/admin/entity.php?e=alumni'=>'Alumni','/admin/entity.php?e=fees'=>'Fees','/admin/entity.php?e=certificates'=>'Certificates','/admin/documents.php'=>'Documents','/admin/entity.php?e=library'=>'Library','/admin/entity.php?e=placements'=>'Placements','/admin/entity.php?e=activity_logs'=>'Audit Logs','/admin/settings.php'=>'Settings'],
        'College Admin'=>['/admin/index.php'=>'Command Center','/admin/modules.php'=>'All Modules','/admin/entity.php?e=students'=>'Students','/admin/entity.php?e=faculty'=>'Faculty','/admin/entity.php?e=subjects'=>'Subjects','/admin/admissions.php'=>'Admissions & Enrollment','/admin/entity.php?e=applications'=>'Applications','/admin/entity.php?e=exams'=>'Exams','/admin/entity.php?e=timetables'=>'Timetables','/admin/entity.php?e=alumni'=>'Alumni','/admin/entity.php?e=fees'=>'Fees','/admin/entity.php?e=certificates'=>'Certificates','/admin/documents.php'=>'Documents','/admin/reports.php'=>'Reports','/admin/settings.php'=>'Settings'],
        'Principal'=>['/principal/index.php'=>'Executive Dashboard','/admin/reports.php'=>'Institution Reports','/admin/entity.php?e=applications'=>'Approvals','/admin/entity.php?e=certificates'=>'Certificates','/admin/documents.php'=>'Documents'],
        'HOD'=>['/hod/index.php'=>'Department Dashboard','/hod/workload.php'=>'Faculty Workload','/admin/entity.php?e=subjects'=>'Subjects','/admin/entity.php?e=applications'=>'Approvals'],
        'Faculty'=>['/faculty/index.php'=>'Faculty Dashboard','/faculty/attendance.php'=>'Smart Attendance','/faculty/assignments.php'=>'Assignments','/faculty/materials.php'=>'Materials','/faculty/quiz.php'=>'Quiz Studio','/faculty/projects.php'=>'Projects','/faculty/exams.php'=>'Examination'],
        'Examination Officer'=>['/examination/index.php'=>'Exam Office','/examination/exams.php'=>'Exam Scheduler','/examination/marks.php'=>'Marks & Results','/admin/entity.php?e=exam_registrations'=>'Registrations'],
        'Accounts Officer'=>['/accounts/index.php'=>'Accounts Dashboard','/accounts/fees.php'=>'Fees','/accounts/payments.php'=>'Payments','/accounts/scholarships.php'=>'Scholarships'],
        'Librarian'=>['/library/index.php'=>'Library Dashboard','/library/books.php'=>'Book Catalog','/library/transactions.php'=>'Circulation','/library/reservations.php'=>'Reservations'],
        'Placement Officer'=>['/placement/index.php'=>'Placement Dashboard','/placement/companies.php'=>'Companies','/placement/jobs.php'=>'Jobs','/placement/internships.php'=>'Internships'],
        'Student'=>['/student/index.php'=>'My Campus','/student/profile.php'=>'My Digital ID','/student/academics.php'=>'Academics','/student/attendance.php'=>'Attendance','/student/assignments.php'=>'Assignments','/student/quiz.php'=>'Quizzes','/student/exams.php'=>'Exams','/student/results.php'=>'Results','/student/fees.php'=>'Fees','/student/payments.php'=>'Payments','/student/applications.php'=>'Applications','/student/certificates.php'=>'Certificates','/student/documents.php'=>'Documents','/student/library.php'=>'Library','/student/career.php'=>'Career','/placement/jobs.php'=>'Job Marketplace','/placement/internships.php'=>'Internships','/student/notifications.php'=>'Notifications','/student/calendar.php'=>'Calendar','/student/leave.php'=>'Leave','/student/ai.php'=>'AI Assistant'],
        'Parent/Guardian'=>['/parent/index.php'=>'Parent Portal','/parent/attendance.php'=>'Attendance','/parent/results.php'=>'Results','/parent/fees.php'=>'Fees','/parent/notifications.php'=>'Alerts'],
        'Reception/Operator'=>['/reception/index.php'=>'Front Desk','/admin/admissions.php'=>'Admissions & Enrollment','/admin/entity.php?e=applications'=>'Applications'],
        'IT/System Administrator'=>['/it/index.php'=>'System Health','/admin/entity.php?e=users'=>'Users','/admin/entity.php?e=activity_logs'=>'Audit Logs','/admin/backup.php'=>'Backup & Restore'],
    ];
    $items = $sets[$role] ?? ['/dashboard.php'=>'Dashboard']; $current = (string)($_SERVER['REQUEST_URI'] ?? '');
    foreach ($items as $path=>$label) { $active = str_contains($current,$path) ? ' active' : ''; echo '<a class="nav-item'.$active.'" href="'.e($base.$path).'"><i class="fa-solid fa-circle" aria-hidden="true"></i><span>'.e($label).'</span></a>'; }
    echo '<div class="nav-divider"></div><a class="nav-item" href="'.e($base.'/public/verification.php').'"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Certificate Verification</span></a>';
}

function kpi(string $label, string|int|float $value, string $icon = '◉'): void { echo '<div class="col-sm-6 col-xl-3"><div class="kpi-card"><div><span class="small text-muted">'.e($label).'</span><div class="kpi-value">'.e((string)$value).'</div></div><div class="kpi-icon">'.e($icon).'</div></div></div>'; }
function flash(): void { if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) return; $flash=$_SESSION['flash']; unset($_SESSION['flash']); $type=in_array((string)($flash[0]??''),['success','danger','warning','info'],true)?(string)$flash[0]:'info'; echo '<div class="alert alert-'.e($type).'" role="alert">'.e((string)($flash[1]??'')).'</div>'; }
