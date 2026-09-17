<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$studentStmt = db()->prepare('SELECT id, branch, semester FROM students WHERE user_id = ? AND status = \'Active\' LIMIT 1');
$studentStmt->execute([actor_id()]);
$student = $studentStmt->fetch();

if (!$student) {
    $_SESSION['flash'] = ['danger', 'Active student profile not found.'];
    redirect(BASE_URL . '/student/index.php');
}

$skillsStmt = db()->prepare(
    "SELECT s.name, ss.level, ss.verified
     FROM student_skills ss
     JOIN skills s ON s.id = ss.skill_id
     WHERE ss.student_id = ?
     ORDER BY ss.level DESC, s.name ASC"
);
$skillsStmt->execute([(int) $student['id']]);
$skills = $skillsStmt->fetchAll();

$jobsStmt = db()->prepare(
    "SELECT j.id, j.title, j.location, j.package_amount, j.deadline,
            j.eligibility, c.name AS company,
            CASE WHEN ja.id IS NULL THEN 0 ELSE 1 END AS applied
     FROM jobs j
     JOIN companies c ON c.id = j.company_id
     LEFT JOIN job_applications ja
       ON ja.job_id = j.id AND ja.student_id = ?
     WHERE j.status = 'Open'
       AND (j.deadline IS NULL OR j.deadline >= CURDATE())
     ORDER BY (j.deadline IS NULL), j.deadline ASC, j.title ASC"
);
$jobsStmt->execute([(int) $student['id']]);
$jobs = $jobsStmt->fetchAll();

$applicationsStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM job_applications
     WHERE student_id = ?"
);
$applicationsStmt->execute([(int) $student['id']]);
$totalApplications = (int) $applicationsStmt->fetchColumn();

$verifiedSkills = 0;
$totalSkillLevel = 0;
foreach ($skills as $skill) {
    $totalSkillLevel += max(0, min(10, (int) $skill['level']));
    if ((int) $skill['verified'] === 1) {
        $verifiedSkills++;
    }
}
$averageSkill = $skills ? round($totalSkillLevel / count($skills), 1) : 0;

page_top('Career Center');
flash();
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Career & placement</div>
            <h1 class="h3 mb-1">Career Center</h1>
            <p class="text-muted mb-0">Track your skills and discover current placement opportunities.</p>
        </div>
        <a href="<?= e(BASE_URL . '/placement/jobs.php') ?>" class="btn btn-primary">Browse All Jobs</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Skills listed</div>
            <div class="h3 fw-bold mb-0"><?= count($skills) ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Verified skills</div>
            <div class="h3 fw-bold mb-0"><?= $verifiedSkills ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Average skill level</div>
            <div class="h3 fw-bold mb-0"><?= e((string) $averageSkill) ?><span class="fs-6 text-muted"> / 10</span></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Applications submitted</div>
            <div class="h3 fw-bold mb-0"><?= $totalApplications ?></div>
        </div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-1">My Skill Profile</h2>
                    <div class="small text-muted">Skills recorded by the placement team</div>
                </div>
                <div class="card-body">
                    <?php if (!$skills): ?>
                        <div class="text-center py-5">
                            <div class="display-6 mb-3">🧑‍💻</div>
                            <h3 class="h6">No skills added yet</h3>
                            <p class="text-muted small mb-0">Ask the placement team to add or verify your skills.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($skills as $skill):
                            $level = max(0, min(10, (int) $skill['level']));
                            $percent = $level * 10;
                        ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold"><?= e($skill['name']) ?></span>
                                    <span class="small text-muted">
                                        <?= $level ?>/10
                                        <?php if ((int) $skill['verified'] === 1): ?>
                                            <span class="badge text-bg-success ms-1">Verified</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="progress" role="progressbar" aria-valuenow="<?= $level ?>" aria-valuemin="0" aria-valuemax="10" aria-label="<?= e($skill['name']) ?> skill level">
                                    <div class="progress-bar" style="width: <?= $percent ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-1">Open Opportunities</h2>
                        <div class="small text-muted">Current jobs listed for students</div>
                    </div>
                    <span class="badge text-bg-primary"><?= count($jobs) ?> open</span>
                </div>

                <?php if (!$jobs): ?>
                    <div class="card-body text-center py-5">
                        <div class="display-6 mb-3">💼</div>
                        <h3 class="h5">No open opportunities</h3>
                        <p class="text-muted mb-0">Check the placement section again when new jobs are posted.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($jobs as $job): ?>
                            <div class="list-group-item p-4">
                                <div class="d-flex flex-wrap justify-content-between gap-3">
                                    <div>
                                        <div class="small text-muted mb-1"><?= e($job['company']) ?></div>
                                        <h3 class="h6 mb-2"><?= e($job['title']) ?></h3>
                                        <div class="small text-muted">
                                            <?= e($job['location'] ?: 'Location not specified') ?>
                                            · ₹<?= e(number_format((float) $job['package_amount'])) ?>
                                            <?php if (!empty($job['deadline'])): ?>
                                                · Deadline <?= e($job['deadline']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php if ((int) $job['applied'] === 1): ?>
                                            <span class="badge text-bg-success">Applied</span>
                                        <?php else: ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL . '/placement/jobs.php') ?>">View & Apply</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($job['eligibility'])): ?>
                                    <div class="small text-muted mt-3"><strong>Eligibility:</strong> <?= e($job['eligibility']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php page_bottom(); ?>
