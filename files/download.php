<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = safe_int($_GET['id'] ?? null);
if ($id === null || $id < 1) {
    http_response_code(404);
    exit('Not found');
}

$stmt = db()->prepare(
    'SELECT id, owner_user_id, title, file_path, mime_type, file_size
     FROM documents
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$id]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    exit('Not found');
}

$user = current_user();
$allowed = has_role('Super Admin', 'College Admin', 'IT/System Administrator')
    || (int)$document['owner_user_id'] === (int)$user['id']
    || can('documents.manage');

if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

$storedPath = str_replace('\\', '/', trim((string)$document['file_path']));
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false || $storedPath === '' || str_contains($storedPath, '..')) {
    http_response_code(404);
    exit('File missing');
}

$relativePath = ltrim($storedPath, '/');
$candidates = [];

// Current FileService storage.
if (str_starts_with($relativePath, 'uploads/')) {
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

// Legacy private-document storage remains supported for existing records.
if (str_starts_with($relativePath, 'storage/private/')) {
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

// Older records may contain only a filename and were stored in storage/private.
if (!str_contains($relativePath, '/')) {
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . basename($relativePath);
}

$path = null;
$allowedRoots = [
    realpath($projectRoot . '/uploads'),
    realpath($projectRoot . '/storage/private'),
];
$allowedRoots = array_values(array_filter($allowedRoots, static fn ($root): bool => is_string($root) && $root !== ''));

foreach ($candidates as $candidate) {
    $realFile = realpath($candidate);
    if ($realFile === false || !is_file($realFile)) {
        continue;
    }

    foreach ($allowedRoots as $root) {
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($realFile === $root || str_starts_with($realFile, $prefix)) {
            $path = $realFile;
            break 2;
        }
    }
}

if ($path === null) {
    http_response_code(404);
    exit('File missing');
}

$mime = trim((string)($document['mime_type'] ?? ''));
if ($mime === '' || !preg_match('/^[A-Za-z0-9.+-]+\/[A-Za-z0-9.+-]+$/', $mime)) {
    $mime = 'application/octet-stream';
}

$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($path)) ?: 'document';
$fileSize = filesize($path);

if ($fileSize === false) {
    http_response_code(404);
    exit('File missing');
}

AuditService::log('DOWNLOAD', 'documents', $id, 'Authorized document download');

header('Content-Type: ' . $mime);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

readfile($path);
exit;
