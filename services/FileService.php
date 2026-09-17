<?php
declare(strict_types=1);

final class FileService
{
    /**
     * Store a validated uploaded file inside the application's uploads directory.
     *
     * @param array<string, mixed> $file
     * @param array<string, string[]> $allowed Extension => allowed MIME types.
     * @return array{path:string,name:string,mime:string,size:int}
     */
    public static function save(
        array $file,
        string $subdir,
        array $allowed,
        ?int $maxBytes = null
    ): array {
        $maxBytes ??= (int)($GLOBALS['app']['upload_max_bytes'] ?? 20 * 1024 * 1024);

        if ($maxBytes <= 0) {
            throw new RuntimeException('Invalid upload size limit.');
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::uploadErrorMessage((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $originalName = (string)($file['name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        if ($size <= 0) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        if ($size > $maxBytes) {
            throw new RuntimeException('File too large.');
        }

        if ($originalName === '') {
            throw new RuntimeException('Invalid file name.');
        }

        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '' || !array_key_exists($ext, $allowed)) {
            throw new RuntimeException('File type not allowed.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmpName);
        if ($mime === '') {
            throw new RuntimeException('Unable to determine file type.');
        }

        $allowedMimes = array_map('strval', (array)$allowed[$ext]);
        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('File type not allowed.');
        }

        // Only allow simple relative upload folders; never accept path traversal.
        $subdir = trim(str_replace('\\', '/', $subdir), '/');
        if ($subdir === '' || str_contains($subdir, '..')) {
            throw new RuntimeException('Invalid upload directory.');
        }

        foreach (explode('/', $subdir) as $segment) {
            if ($segment === '' || $segment === '.' || !preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
                throw new RuntimeException('Invalid upload directory.');
            }
        }

        $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
        $dir = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);

        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create upload directory.');
        }

        $name = secure_random(24) . '.' . $ext;
        $destination = $dir . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Could not store file.');
        }

        @chmod($destination, 0640);

        return [
            'path' => 'uploads/' . $subdir . '/' . $name,
            'name' => $name,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large.',
            UPLOAD_ERR_PARTIAL => 'The file upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Please select a file.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload directory is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'The upload was blocked by a server extension.',
            default => 'Upload failed.',
        };
    }
}
