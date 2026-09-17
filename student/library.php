<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$search = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));

$categories = db()->query(
    "SELECT DISTINCT category
     FROM books
     WHERE category IS NOT NULL AND category <> ''
     ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

$sql =
    "SELECT b.id, b.isbn, b.title, b.author, b.category,
            COUNT(bc.id) AS copies,
            COALESCE(SUM(CASE WHEN bc.status = 'Available' THEN 1 ELSE 0 END), 0) AS available
     FROM books b
     LEFT JOIN book_copies bc ON bc.book_id = b.id
     WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= ' AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($category !== '') {
    $sql .= ' AND b.category = ?';
    $params[] = $category;
}

$sql .= ' GROUP BY b.id, b.isbn, b.title, b.author, b.category ORDER BY b.title ASC';

$booksStmt = db()->prepare($sql);
$booksStmt->execute($params);
$books = $booksStmt->fetchAll();

$activeStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM library_transactions
     WHERE student_id = (SELECT id FROM students WHERE user_id = ? LIMIT 1)
       AND status = 'Issued'"
);
$activeStmt->execute([actor_id()]);
$issuedCount = (int) $activeStmt->fetchColumn();

$overdueStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM library_transactions
     WHERE student_id = (SELECT id FROM students WHERE user_id = ? LIMIT 1)
       AND status = 'Issued'
       AND return_due < CURDATE()"
);
$overdueStmt->execute([actor_id()]);
$overdueCount = (int) $overdueStmt->fetchColumn();

$totalBooks = (int) db()->query('SELECT COUNT(*) FROM books')->fetchColumn();
$totalAvailable = (int) db()->query("SELECT COUNT(*) FROM book_copies WHERE status = 'Available'")->fetchColumn();

page_top('Digital Library');
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-muted fw-semibold">Student services</div>
            <h1 class="h3 mb-1">Digital Library</h1>
            <p class="text-muted mb-0">Search the college catalogue and check current availability.</p>
        </div>
        <a href="<?= e(BASE_URL . '/library/') ?>" class="btn btn-outline-primary">Library Center</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Books in catalogue</div>
            <div class="h3 fw-bold mb-0"><?= $totalBooks ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">Available copies</div>
            <div class="h3 fw-bold mb-0"><?= $totalAvailable ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">My issued books</div>
            <div class="h3 fw-bold mb-0"><?= $issuedCount ?></div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted">My overdue books</div>
            <div class="h3 fw-bold mb-0"><?= $overdueCount ?></div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-7">
                    <label for="library-search" class="form-label">Search catalogue</label>
                    <input id="library-search" name="q" class="form-control"
                           value="<?= e($search) ?>"
                           placeholder="Search by title, author or ISBN">
                </div>
                <div class="col-md-3">
                    <label for="library-category" class="form-label">Category</label>
                    <select id="library-category" name="category" class="form-select">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $item): ?>
                            <option value="<?= e((string) $item) ?>" <?= $category === (string) $item ? 'selected' : '' ?>><?= e((string) $item) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Search</button>
                    <a class="btn btn-outline-secondary" href="library.php" aria-label="Clear search">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-1">Book Catalogue</h2>
            <div class="small text-muted"><?= count($books) ?> result(s) found</div>
        </div>

        <?php if (!$books): ?>
            <div class="card-body text-center py-5">
                <div class="display-6 mb-3">📚</div>
                <h3 class="h5">No books found</h3>
                <p class="text-muted mb-0">Try another title, author, ISBN or category.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3">ISBN</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Availability</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($books as $book):
                        $available = (int) $book['available'];
                        $copies = (int) $book['copies'];
                        $badge = $available > 0 ? 'text-bg-success' : 'text-bg-secondary';
                    ?>
                        <tr>
                            <td class="px-3"><code><?= e($book['isbn'] ?: '—') ?></code></td>
                            <td class="fw-semibold"><?= e($book['title']) ?></td>
                            <td><?= e($book['author'] ?: '—') ?></td>
                            <td><?= e($book['category'] ?: 'General') ?></td>
                            <td>
                                <span class="badge <?= $badge ?>">
                                    <?= $available ?> available
                                </span>
                                <div class="small text-muted mt-1"><?= $copies ?> total copies</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php page_bottom(); ?>
