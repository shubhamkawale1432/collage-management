<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Faculty');

$facultyQuery = db()->prepare('SELECT id FROM faculty WHERE user_id = ? AND status = "active" LIMIT 1');
$facultyQuery->execute([actor_id()]);
$facultyId = (int) $facultyQuery->fetchColumn();
if ($facultyId < 1) { http_response_code(403); exit('Faculty profile is not active.'); }

$subjectQuery = db()->prepare('SELECT s.id, s.code, s.name FROM subjects s INNER JOIN faculty_subjects fs ON fs.subject_id = s.id WHERE fs.faculty_id = ? AND s.status = 1 ORDER BY s.code');
$subjectQuery->execute([$facultyId]);
$subjects = $subjectQuery->fetchAll();
$divisions = db()->query('SELECT id, name FROM divisions ORDER BY name')->fetchAll();

$selectedSubject = safe_int($_GET['subject_id'] ?? null) ?? (int) ($subjects[0]['id'] ?? 0);
$selectedDivision = safe_int($_GET['division_id'] ?? null) ?? (int) ($divisions[0]['id'] ?? 0);
$selectedDate = (string) ($_GET['date'] ?? date('Y-m-d'));
$dateObject = DateTime::createFromFormat('Y-m-d', $selectedDate);
if (!$dateObject || $dateObject->format('Y-m-d') !== $selectedDate) $selectedDate = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $subjectId = safe_int($_POST['subject_id'] ?? null);
    $divisionId = safe_int($_POST['division_id'] ?? null);
    $date = trim((string) ($_POST['date'] ?? ''));
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);
    $errors = [];
    if (!$subjectId || !$divisionId) $errors[] = 'Select a valid subject and division.';
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) $errors[] = 'Enter a valid attendance date.';

    if (!$errors) {
        $check = db()->prepare('SELECT 1 FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ? LIMIT 1');
        $check->execute([$facultyId, $subjectId]);
        if (!$check->fetchColumn()) $errors[] = 'You can only mark attendance for your assigned subjects.';
    }

    if (!$errors) {
        $studentQuery = db()->prepare('SELECT id FROM students WHERE division_id = ? AND status = "active" ORDER BY id');
        $studentQuery->execute([$divisionId]);
        $studentRows = $studentQuery->fetchAll();
        try {
            db()->beginTransaction();
            $sessionQuery = db()->prepare('SELECT id FROM attendance_sessions WHERE subject_id = ? AND date = ? AND division_id = ? AND faculty_id = ? LIMIT 1');
            $sessionQuery->execute([$subjectId, $date, $divisionId, $facultyId]);
            $sessionId = (int) $sessionQuery->fetchColumn();
            if ($sessionId < 1) {
                $insertSession = db()->prepare('INSERT INTO attendance_sessions(subject_id, faculty_id, date, start_time, end_time, division_id, mode, class_code) VALUES (?, ?, ?, CURTIME(), CURTIME(), ?, "manual", ?)');
                $insertSession->execute([$subjectId, $facultyId, $date, $divisionId, secure_random(6)]);
                $sessionId = (int) db()->lastInsertId();
            }

            $allowedStatuses = ['Present', 'Absent', 'Late', 'Leave'];
            $attendance = db()->prepare('INSERT INTO attendance(session_id, student_id, status, remarks) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)');
            foreach ($studentRows as $student) {
                $studentId = (int) $student['id'];
                $status = (string) ($_POST['status_' . $studentId] ?? 'Absent');
                if (!in_array($status, $allowedStatuses, true)) $status = 'Absent';
                $remarks = mb_substr(trim((string) ($_POST['remarks_' . $studentId] ?? '')), 0, 255);
                $attendance->execute([$sessionId, $studentId, $status, $remarks]);
            }
            db()->commit();
            AuditService::log('MARK', 'attendance', $sessionId, 'Attendance session saved');
            $_SESSION['flash'] = ['success', 'Attendance saved successfully.'];
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $_SESSION['flash'] = ['danger', 'Attendance could not be saved. Please try again.'];
        }
    } else {
        $_SESSION['flash'] = ['danger', implode(' ', $errors)];
    }
    redirect('attendance.php?subject_id=' . (int) $selectedSubject . '&division_id=' . (int) $selectedDivision . '&date=' . rawurlencode($selectedDate));
}

$students = [];
if ($selectedDivision > 0) {
    $studentQuery = db()->prepare('SELECT s.id, s.enrollment_no, u.name FROM students s INNER JOIN users u ON u.id = s.user_id WHERE s.division_id = ? AND s.status = "active" ORDER BY u.name');
    $studentQuery->execute([$selectedDivision]);
    $students = $studentQuery->fetchAll();
}

page_top('Smart Attendance Studio'); flash();
?>
<div class="hero"><div><span class="pill">FACULTY · ATTENDANCE</span><h2>Smart Attendance Studio</h2><p class="mb-0">Record attendance only for subjects assigned to your faculty profile.</p></div></div>
<div class="card p-4">
<form method="get" class="row g-3">
<div class="col-md-4"><label class="form-label">Subject</label><select name="subject_id" class="form-select" required><?php foreach ($subjects as $subject): ?><option value="<?= e($subject['id']) ?>" <?= $selectedSubject === (int) $subject['id'] ? 'selected' : '' ?>><?= e($subject['code'].' · '.$subject['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Division</label><select name="division_id" class="form-select" required><?php foreach ($divisions as $division): ?><option value="<?= e($division['id']) ?>" <?= $selectedDivision === (int) $division['id'] ? 'selected' : '' ?>><?= e($division['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= e($selectedDate) ?>" required></div>
<div class="col-md-2 align-self-end"><button class="btn btn-outline-primary w-100">Load students</button></div>
</form>
</div>

<form method="post" class="card p-4 mt-3">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="subject_id" value="<?= e($selectedSubject) ?>"><input type="hidden" name="division_id" value="<?= e($selectedDivision) ?>"><input type="hidden" name="date" value="<?= e($selectedDate) ?>">
<div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Student attendance</h5><span class="badge bg-light text-dark"><?= e(count($students)) ?> students</span></div>
<?php if (!$students): ?><div class="alert alert-light border mb-0">No active students were found for this division.</div>
<?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Enrollment</th><th>Student</th><th>Status</th><th>Remarks</th></tr></thead><tbody><?php foreach ($students as $student): $id = (int) $student['id']; ?><tr><td><?= e((string) $student['enrollment_no']) ?></td><td><?= e((string) $student['name']) ?></td><td><select name="status_<?= $id ?>" class="form-select"><option>Present</option><option>Absent</option><option>Late</option><option>Leave</option></select></td><td><input name="remarks_<?= $id ?>" class="form-control" maxlength="255"></td></tr><?php endforeach; ?></tbody></table></div><button class="btn btn-primary">Save attendance</button><?php endif; ?>
</form>
<?php page_bottom();
