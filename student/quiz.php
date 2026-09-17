<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$studentStmt = db()->prepare('SELECT s.id FROM students s WHERE s.user_id = ? AND s.status = "active" LIMIT 1');
$studentStmt->execute([actor_id()]);
$studentId = (int) $studentStmt->fetchColumn();
if ($studentId <= 0) { exit('Active student profile not found.'); }

$quizStmt = db()->prepare(
    'SELECT q.id,q.title,q.duration_minutes,q.open_at,q.close_at,s.name AS subject,s.code AS subject_code,
        (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id=q.id) AS question_count,
        (SELECT qa.status FROM quiz_attempts qa WHERE qa.quiz_id=q.id AND qa.student_id=? ORDER BY qa.id DESC LIMIT 1) AS attempt_status,
        (SELECT qa.score FROM quiz_attempts qa WHERE qa.quiz_id=q.id AND qa.student_id=? ORDER BY qa.id DESC LIMIT 1) AS attempt_score
     FROM quizzes q
     INNER JOIN subjects s ON s.id=q.subject_id
     INNER JOIN student_subjects ss ON ss.subject_id=q.subject_id AND ss.student_id=?
     WHERE q.status="Published"
     ORDER BY CASE WHEN q.open_at IS NOT NULL AND q.open_at<=NOW() AND (q.close_at IS NULL OR q.close_at>=NOW()) THEN 0 WHEN q.open_at IS NOT NULL AND q.open_at>NOW() THEN 1 ELSE 2 END,
              COALESCE(q.open_at,q.close_at) ASC,q.id DESC'
);
$quizStmt->execute([$studentId,$studentId,$studentId]);
$quizzes = $quizStmt->fetchAll();

$total=count($quizzes); $completed=0; $available=0; $upcoming=0;
foreach ($quizzes as $quiz) {
    $status=strtolower((string)($quiz['attempt_status']??''));
    $openAt=$quiz['open_at']?strtotime((string)$quiz['open_at']):null;
    $closeAt=$quiz['close_at']?strtotime((string)$quiz['close_at']):null;
    $now=time();
    if ($status==='submitted') $completed++;
    elseif (($openAt===null||$openAt<=$now)&&($closeAt===null||$closeAt>=$now)) $available++;
    elseif ($openAt!==null&&$openAt>$now) $upcoming++;
}

page_top('Quiz Center');
?>
<style>
.quiz-hero{border:1px solid rgba(108,117,125,.16);border-radius:18px;padding:24px;background:linear-gradient(135deg,rgba(13,110,253,.08),rgba(111,66,193,.06))}.quiz-stat{height:100%;border:1px solid rgba(108,117,125,.16);border-radius:16px;padding:18px;background:var(--bs-body-bg,#fff)}.quiz-stat .value{font-size:1.65rem;font-weight:700;line-height:1.1}.quiz-stat .label{font-size:.82rem;color:var(--bs-secondary-color,#6c757d);margin-top:5px}.quiz-card{border:1px solid rgba(108,117,125,.16);border-radius:16px;padding:18px;height:100%;transition:transform .15s ease,box-shadow .15s ease}.quiz-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.07)}.quiz-meta{font-size:.84rem;color:var(--bs-secondary-color,#6c757d)}
</style>
<div class="quiz-hero mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><div class="text-primary fw-semibold mb-1">STUDENT ACADEMICS</div><h2 class="mb-1">Quiz Center</h2><p class="mb-0 text-body-secondary">View quizzes for your enrolled subjects and track your attempts.</p></div><a href="index.php" class="btn btn-outline-secondary">Back to Dashboard</a></div></div>
<div class="row g-3 mb-4">
<div class="col-6 col-lg-3"><div class="quiz-stat"><div class="value"><?=e($total)?></div><div class="label">Total Quizzes</div></div></div>
<div class="col-6 col-lg-3"><div class="quiz-stat"><div class="value"><?=e($available)?></div><div class="label">Available Now</div></div></div>
<div class="col-6 col-lg-3"><div class="quiz-stat"><div class="value"><?=e($upcoming)?></div><div class="label">Upcoming</div></div></div>
<div class="col-6 col-lg-3"><div class="quiz-stat"><div class="value"><?=e($completed)?></div><div class="label">Completed</div></div></div>
</div>
<?php if (!$quizzes): ?>
<div class="card border-0 shadow-sm p-5 text-center"><div class="fs-1 mb-2">📝</div><h5>No quizzes available</h5><p class="text-body-secondary mb-0">Published quizzes for your enrolled subjects will appear here.</p></div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($quizzes as $quiz):
$status=strtolower((string)($quiz['attempt_status']??'')); $openAt=$quiz['open_at']?strtotime((string)$quiz['open_at']):null; $closeAt=$quiz['close_at']?strtotime((string)$quiz['close_at']):null; $now=time(); $isAvailable=($openAt===null||$openAt<=$now)&&($closeAt===null||$closeAt>=$now); $isUpcoming=$openAt!==null&&$openAt>$now; $isCompleted=$status==='submitted';
?>
<div class="col-12 col-md-6 col-xl-4"><div class="quiz-card">
<div class="d-flex justify-content-between align-items-start gap-2 mb-3"><span class="badge text-bg-light border"><?=e($quiz['subject_code']?:$quiz['subject'])?></span><?php if($isCompleted):?><span class="badge text-bg-success">Completed</span><?php elseif($isAvailable):?><span class="badge text-bg-primary">Available</span><?php elseif($isUpcoming):?><span class="badge text-bg-warning">Upcoming</span><?php else:?><span class="badge text-bg-secondary">Closed</span><?php endif;?></div>
<h5 class="mb-1"><?=e($quiz['title'])?></h5><div class="quiz-meta mb-3"><?=e($quiz['subject'])?></div>
<div class="row g-2 small mb-3"><div class="col-6"><strong><?=e((int)$quiz['question_count'])?></strong><br><span class="quiz-meta">Questions</span></div><div class="col-6"><strong><?=e((int)$quiz['duration_minutes'])?> min</strong><br><span class="quiz-meta">Duration</span></div></div>
<div class="quiz-meta mb-3"><?php if($quiz['open_at']):?>Open: <?=e(date('d M Y, h:i A',strtotime((string)$quiz['open_at'])))?><br><?php endif;?><?php if($quiz['close_at']):?>Closes: <?=e(date('d M Y, h:i A',strtotime((string)$quiz['close_at'])))?><?php endif;?></div>
<?php if($isCompleted):?><div class="alert alert-success py-2 mb-0">Submitted<?php if($quiz['attempt_score']!==null):?> · Score: <strong><?=e($quiz['attempt_score'])?></strong><?php endif;?></div><?php elseif($isAvailable):?><a class="btn btn-primary w-100" href="quiz_take.php?id=<?=e((int)$quiz['id'])?>">Attempt Quiz</a><?php elseif($isUpcoming):?><button class="btn btn-outline-secondary w-100" type="button" disabled>Not Open Yet</button><?php else:?><button class="btn btn-outline-secondary w-100" type="button" disabled>Quiz Closed</button><?php endif;?>
</div></div>
<?php endforeach;?></div>
<?php endif;?>
<?php page_bottom(); ?>