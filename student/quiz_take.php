<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$qid = safe_int($_GET['id'] ?? $_POST['id'] ?? 0);
if ($qid < 1) {
    http_response_code(400);
    exit('Invalid quiz.');
}

$studentStmt = db()->prepare('SELECT id, status FROM students WHERE user_id = ? LIMIT 1');
$studentStmt->execute([actor_id()]);
$student = $studentStmt->fetch();
if (!$student || ($student['status'] ?? 'Active') !== 'Active') {
    http_response_code(403);
    exit('Active student profile not found.');
}
$studentId = (int) $student['id'];

$quizStmt = db()->prepare(
    'SELECT q.id, q.title, q.duration_minutes, q.open_at, q.close_at, s.name AS subject_name
     FROM quizzes q
     INNER JOIN subjects s ON s.id = q.subject_id
     INNER JOIN student_subjects ss ON ss.subject_id = q.subject_id AND ss.student_id = ?
     WHERE q.id = ? AND q.status = "Published"
     LIMIT 1'
);
$quizStmt->execute([$studentId, $qid]);
$quiz = $quizStmt->fetch();

if (!$quiz) {
    http_response_code(404);
    exit('Quiz not found or you are not enrolled in this subject.');
}

$now = time();
$openAt = strtotime((string) $quiz['open_at']);
$closeAt = strtotime((string) $quiz['close_at']);
if ($openAt === false || $closeAt === false) {
    http_response_code(500);
    exit('Quiz schedule is invalid.');
}

$attemptStmt = db()->prepare(
    'SELECT id, started_at, submitted_at, score, status
     FROM quiz_attempts
     WHERE quiz_id = ? AND student_id = ?
     ORDER BY id DESC LIMIT 1'
);
$attemptStmt->execute([$qid, $studentId]);
$existingAttempt = $attemptStmt->fetch();

if ($existingAttempt && strtolower((string) $existingAttempt['status']) === 'submitted') {
    $_SESSION['flash'] = ['info', 'You have already submitted this quiz.'];
    redirect('quiz.php');
}

if ($now < $openAt) {
    http_response_code(403);
    exit('This quiz has not opened yet.');
}
if ($now >= $closeAt) {
    http_response_code(403);
    exit('The quiz submission window has closed.');
}

$questionsStmt = db()->prepare(
    'SELECT id, question, type, options_json, correct_answer, marks
     FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC'
);
$questionsStmt->execute([$qid]);
$questions = $questionsStmt->fetchAll();

if (!$questions) {
    http_response_code(422);
    exit('This quiz does not contain any questions yet.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $answers = [];
    $score = 0.0;

    foreach ($questions as $question) {
        $questionId = (int) $question['id'];
        $answer = trim((string) ($_POST['a_' . $questionId] ?? ''));
        $options = json_decode((string) $question['options_json'], true);
        $options = is_array($options) ? $options : [];

        $validAnswer = $answer === '' || array_key_exists($answer, $options);
        if (!$validAnswer) {
            http_response_code(422);
            exit('Invalid answer submitted.');
        }

        $marks = (float) $question['marks'];
        $earned = ($answer !== '' && hash_equals((string) $question['correct_answer'], $answer)) ? $marks : 0.0;
        $score += $earned;
        $answers[] = [$questionId, $answer, $earned];
    }

    try {
        db()->beginTransaction();

        $checkAttempt = db()->prepare(
            'SELECT id, status FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );
        $checkAttempt->execute([$qid, $studentId]);
        $lockedAttempt = $checkAttempt->fetch();

        if ($lockedAttempt && strtolower((string) $lockedAttempt['status']) === 'submitted') {
            db()->rollBack();
            $_SESSION['flash'] = ['info', 'This quiz has already been submitted.'];
            redirect('quiz.php');
        }

        if (time() >= $closeAt) {
            db()->rollBack();
            $_SESSION['flash'] = ['danger', 'The quiz submission window has closed.'];
            redirect('quiz.php');
        }

        if ($lockedAttempt) {
            $attemptId = (int) $lockedAttempt['id'];
            db()->prepare(
                'UPDATE quiz_attempts SET submitted_at = NOW(), score = ?, status = "Submitted" WHERE id = ?'
            )->execute([$score, $attemptId]);
            db()->prepare('DELETE FROM quiz_answers WHERE attempt_id = ?')->execute([$attemptId]);
        } else {
            $insertAttempt = db()->prepare(
                'INSERT INTO quiz_attempts (quiz_id, student_id, started_at, submitted_at, score, status)
                 VALUES (?, ?, NOW(), NOW(), ?, "Submitted")'
            );
            $insertAttempt->execute([$qid, $studentId, $score]);
            $attemptId = (int) db()->lastInsertId();
        }

        $answerStmt = db()->prepare(
            'INSERT INTO quiz_answers (attempt_id, question_id, answer, marks) VALUES (?, ?, ?, ?)'
        );
        foreach ($answers as [$questionId, $answer, $earned]) {
            $answerStmt->execute([$attemptId, $questionId, $answer, $earned]);
        }

        db()->commit();
        AuditService::log('SUBMIT', 'quiz_attempts', $attemptId, 'Student submitted quiz');
        $_SESSION['flash'] = ['success', 'Quiz submitted successfully.'];
        redirect('quiz.php');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $_SESSION['flash'] = ['danger', 'Unable to submit the quiz. Please try again.'];
        redirect('quiz_take.php?id=' . $qid);
    }
}

$totalMarks = 0.0;
foreach ($questions as $question) {
    $totalMarks += (float) $question['marks'];
}
$remainingSeconds = max(0, $closeAt - time());

page_top('Quiz Attempt');
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="small text-muted mb-1"><?= e($quiz['subject_name']) ?></div>
                    <h2 class="mb-1"><?= e($quiz['title']) ?></h2>
                    <div class="text-muted">Questions: <?= count($questions) ?> · Total marks: <?= e(number_format($totalMarks, 2)) ?></div>
                </div>
                <div class="text-end">
                    <div class="small text-muted">Time remaining</div>
                    <div id="quizTimer" class="fs-3 fw-bold" data-seconds="<?= e($remainingSeconds) ?>">--:--</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <form method="post" id="quizForm" class="card border-0 shadow-sm p-4">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e($qid) ?>">

            <?php foreach ($questions as $index => $question):
                $options = json_decode((string) $question['options_json'], true);
                $options = is_array($options) ? $options : [];
                ?>
                <section class="border rounded-3 p-3 p-md-4 mb-3">
                    <div class="d-flex justify-content-between gap-3 mb-3">
                        <strong><?= e($index + 1) ?>. <?= e($question['question']) ?></strong>
                        <span class="badge text-bg-light"><?= e(number_format((float) $question['marks'], 2)) ?> marks</span>
                    </div>
                    <?php foreach ($options as $key => $value): ?>
                        <label class="d-block border rounded-3 p-3 mb-2 option-row">
                            <input class="form-check-input me-2" type="radio" name="a_<?= e($question['id']) ?>" value="<?= e($key) ?>">
                            <strong><?= e($key) ?></strong> — <?= e($value) ?>
                        </label>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="quiz.php">Cancel</a>
                <button class="btn btn-primary px-4" type="submit" id="submitQuiz">Submit Quiz</button>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const timer = document.getElementById('quizTimer');
    const form = document.getElementById('quizForm');
    const button = document.getElementById('submitQuiz');
    let remaining = Number(timer?.dataset.seconds || 0);

    const render = () => {
        const min = Math.floor(remaining / 60);
        const sec = remaining % 60;
        timer.textContent = String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        if (remaining <= 60) timer.classList.add('text-danger');
    };

    render();
    const interval = setInterval(() => {
        remaining--;
        render();
        if (remaining <= 0) {
            clearInterval(interval);
            button.disabled = true;
            button.textContent = 'Time expired';
            form.submit();
        }
    }, 1000);
})();
</script>
<?php page_bottom(); ?>