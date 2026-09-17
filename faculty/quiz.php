<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Faculty');

$facultyStmt = db()->prepare('SELECT id FROM faculty WHERE user_id = ? AND status = ? LIMIT 1');
$facultyStmt->execute([actor_id(), 'active']);
$facultyId = safe_int($facultyStmt->fetchColumn());

if ($facultyId === null) {
    http_response_code(403);
    exit('Faculty profile is not active.');
}

$subjectStmt = db()->prepare(
    'SELECT s.id, s.code, s.name
     FROM subjects s
     INNER JOIN faculty_subjects fs ON fs.subject_id = s.id
     WHERE fs.faculty_id = ?
     ORDER BY s.name'
);
$subjectStmt->execute([$facultyId]);
$subjects = $subjectStmt->fetchAll();

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();

    $subjectId = safe_int($_POST['subject_id'] ?? null);
    $title = trim((string) ($_POST['title'] ?? ''));
    $duration = safe_int($_POST['duration'] ?? null);
    $openAt = trim((string) ($_POST['open_at'] ?? ''));
    $closeAt = trim((string) ($_POST['close_at'] ?? ''));
    $questions = [];

    if ($subjectId === null || $subjectId < 1) {
        $errors[] = 'Please select a valid subject.';
    }
    if ($title === '' || mb_strlen($title) > 150) {
        $errors[] = 'Quiz title is required and must be 150 characters or fewer.';
    }
    if ($duration === null || $duration < 1 || $duration > 300) {
        $errors[] = 'Duration must be between 1 and 300 minutes.';
    }

    for ($i = 1; $i <= 5; $i++) {
        $question = trim((string) ($_POST['q' . $i] ?? ''));
        $answer = strtoupper(trim((string) ($_POST['a' . $i] ?? '')));
        if ($question === '') {
            continue;
        }
        if (mb_strlen($question) > 1000) {
            $errors[] = "Question {$i} must be 1000 characters or fewer.";
            continue;
        }
        if (!in_array($answer, ['A', 'B', 'C', 'D'], true)) {
            $errors[] = "Question {$i} has an invalid correct answer.";
            continue;
        }
        $questions[] = ['question' => $question, 'answer' => $answer];
    }

    if (!$questions) {
        $errors[] = 'Add at least one question.';
    }

    $openValue = null;
    $closeValue = null;
    if ($openAt !== '') {
        $openObject = DateTime::createFromFormat('Y-m-d\\TH:i', $openAt);
        if (!$openObject || $openObject->format('Y-m-d\\TH:i') !== $openAt) {
            $errors[] = 'Please enter a valid opening time.';
        } else {
            $openValue = $openObject->format('Y-m-d H:i:s');
        }
    }
    if ($closeAt !== '') {
        $closeObject = DateTime::createFromFormat('Y-m-d\\TH:i', $closeAt);
        if (!$closeObject || $closeObject->format('Y-m-d\\TH:i') !== $closeAt) {
            $errors[] = 'Please enter a valid closing time.';
        } else {
            $closeValue = $closeObject->format('Y-m-d H:i:s');
        }
    }
    if ($openValue !== null && $closeValue !== null && $closeValue <= $openValue) {
        $errors[] = 'Closing time must be after opening time.';
    }

    if (!$errors) {
        $ownership = db()->prepare('SELECT 1 FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ? LIMIT 1');
        $ownership->execute([$facultyId, $subjectId]);
        if (!$ownership->fetchColumn()) {
            $errors[] = 'You can only create quizzes for subjects assigned to you.';
        }
    }

    if (!$errors) {
        try {
            db()->beginTransaction();

            $insertQuiz = db()->prepare(
                'INSERT INTO quizzes (subject_id, faculty_id, title, duration_minutes, open_at, close_at, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insertQuiz->execute([$subjectId, $facultyId, $title, $duration, $openValue, $closeValue, 'Published']);
            $quizId = (int) db()->lastInsertId();

            $insertQuestion = db()->prepare(
                'INSERT INTO quiz_questions (quiz_id, question, type, options_json, correct_answer, marks)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $options = json_encode(['A' => 'Option A', 'B' => 'Option B', 'C' => 'Option C', 'D' => 'Option D'], JSON_THROW_ON_ERROR);
            foreach ($questions as $item) {
                $insertQuestion->execute([$quizId, $item['question'], 'MCQ', $options, $item['answer'], 1]);
            }

            db()->commit();
            AuditService::log('CREATE', 'quizzes', $quizId, 'Quiz published by faculty');
            $_SESSION['flash'] = ['success', 'Quiz published successfully.'];
            redirect('quiz.php');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = 'The quiz could not be published. Please try again.';
        }
    }
}

$listStmt = db()->prepare(
    'SELECT q.id, q.title, q.duration_minutes, q.open_at, q.close_at, q.status,
            s.code AS subject_code, s.name AS subject_name,
            COUNT(qq.id) AS question_count
     FROM quizzes q
     INNER JOIN subjects s ON s.id = q.subject_id
     LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
     WHERE q.faculty_id = ?
     GROUP BY q.id, q.title, q.duration_minutes, q.open_at, q.close_at, q.status, s.code, s.name
     ORDER BY q.id DESC'
);
$listStmt->execute([$facultyId]);
$quizzes = $listStmt->fetchAll();

page_top('Quiz Studio');
flash();
?>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card p-4">
            <span class="badge text-bg-primary mb-2">Faculty Workspace</span>
            <h2 class="h4 mb-1">Create quiz</h2>
            <p class="text-muted">Build a short MCQ quiz for one of your assigned subjects.</p>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="subject_id" class="form-label">Subject</label>
                        <select id="subject_id" name="subject_id" class="form-select" required>
                            <option value="">Select subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= e($subject['id']) ?>" <?= ((string) ($_POST['subject_id'] ?? '') === (string) $subject['id']) ? 'selected' : '' ?>><?= e($subject['code'] . ' · ' . $subject['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="title" class="form-label">Quiz title</label>
                        <input id="title" name="title" maxlength="150" class="form-control" value="<?= e($_POST['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="duration" class="form-label">Duration (minutes)</label>
                        <input id="duration" name="duration" type="number" min="1" max="300" class="form-control" value="<?= e($_POST['duration'] ?? '20') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="open_at" class="form-label">Open at</label>
                        <input id="open_at" name="open_at" type="datetime-local" class="form-control" value="<?= e($_POST['open_at'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="close_at" class="form-label">Close at</label>
                        <input id="close_at" name="close_at" type="datetime-local" class="form-control" value="<?= e($_POST['close_at'] ?? '') ?>">
                    </div>
                </div>

                <hr class="my-4">
                <h3 class="h6">Questions</h3>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="border rounded p-3 mb-3">
                        <label for="q<?= $i ?>" class="form-label">Question <?= $i ?></label>
                        <textarea id="q<?= $i ?>" name="q<?= $i ?>" maxlength="1000" rows="2" class="form-control mb-2" placeholder="Enter question text"><?= e($_POST['q' . $i] ?? '') ?></textarea>
                        <label for="a<?= $i ?>" class="form-label small">Correct option</label>
                        <select id="a<?= $i ?>" name="a<?= $i ?>" class="form-select" style="max-width:180px">
                            <?php foreach (['A', 'B', 'C', 'D'] as $option): ?>
                                <option value="<?= $option ?>" <?= (($_POST['a' . $i] ?? 'A') === $option) ? 'selected' : '' ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endfor; ?>

                <button type="submit" class="btn btn-primary">Publish Quiz</button>
            </form>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div><h2 class="h5 mb-1">Your quizzes</h2><p class="text-muted small mb-0">Recently created assessments.</p></div>
                <span class="badge text-bg-light"><?= e(count($quizzes)) ?></span>
            </div>
            <?php if (!$quizzes): ?>
                <div class="text-muted text-center py-4">No quizzes created yet.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($quizzes as $quiz): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold"><?= e($quiz['title']) ?></div>
                                    <div class="small text-muted"><?= e($quiz['subject_code']) ?> · <?= e($quiz['question_count']) ?> questions · <?= e($quiz['duration_minutes']) ?> min</div>
                                </div>
                                <span class="badge text-bg-primary align-self-start"><?= e($quiz['status']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php page_bottom();
