<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$user = current_user();
$answer = null;
$error = null;
$question = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();

        $question = trim((string)($_POST['question'] ?? ''));

        if ($question === '') {
            throw new InvalidArgumentException('Please enter a question.');
        }

        if (mb_strlen($question) > 500) {
            throw new InvalidArgumentException('Question must be 500 characters or less.');
        }

        $answer = AIService::answer($question);
        AuditService::log('AI_QUERY', 'assistant', null, 'Student AI query');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $error = 'The assistant is temporarily unavailable. Please try again.';
    }
}

page_top('College AI Assistant');
?>

<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="p-4 p-lg-5 bg-body-tertiary">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:64px;height:64px;font-size:1.2rem;">AI</div>
                    <div>
                        <span class="badge text-bg-primary mb-2">Student Assistant</span>
                        <h2 class="mb-1">Campus AI Assistant</h2>
                        <p class="text-secondary mb-0">Ask questions about your authorized college information and services.</p>
                    </div>
                </div>
                <div class="text-lg-end small text-secondary">
                    <div><strong><?= e((string)($user['name'] ?? 'Student')) ?></strong></div>
                    <div>Role-scoped access</div>
                </div>
            </div>
        </div>

        <div class="p-4 p-lg-5">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <label for="question" class="form-label fw-semibold">Your question</label>
                <textarea
                    id="question"
                    name="question"
                    class="form-control form-control-lg"
                    rows="4"
                    maxlength="500"
                    required
                    placeholder="Example: What is my current attendance?"
                ><?= e($question) ?></textarea>
                <div class="form-text">Maximum 500 characters. Do not enter passwords, OTPs, or other sensitive credentials.</div>
                <button type="submit" class="btn btn-primary mt-3 px-4">
                    Ask Assistant
                </button>
            </form>

            <?php if ($answer !== null): ?>
                <div class="mt-4">
                    <div class="card border-primary-subtle bg-primary-subtle">
                        <div class="card-body">
                            <div class="fw-semibold mb-2">Assistant response</div>
                            <div class="mb-0"><?= e($answer) ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-5">
                <h5 class="mb-3">Try asking</h5>
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-semibold">Attendance</div>
                            <small class="text-secondary">“What is my attendance?”</small>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-semibold">Fees</div>
                            <small class="text-secondary">“What is my fee balance?”</small>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-semibold">Exams</div>
                            <small class="text-secondary">“How many exams are upcoming?”</small>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-semibold">Campus services</div>
                            <small class="text-secondary">Ask about available college services.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-light border mt-4 mb-0">
                <strong>Privacy:</strong> The assistant uses your signed-in role and authorized student scope. It does not provide access to another student's private information.
            </div>
        </div>
    </div>
</div>

<?php page_bottom(); ?>
