<?php
/**
 * Storage abstraction used by every upload/stream endpoint. Two backends:
 *   'local' — identical to the original behaviour: files under uploads/<bucket>/, blocked
 *             from direct web access by uploads/.htaccess.
 *   's3'    — the AWS SDK, against a private bucket (any S3-compatible provider via a
 *             custom endpoint). The AWS SDK classes are only touched when the driver is
 *             actually 's3', so nothing here requires cloud credentials to exist for the
 *             app to run.
 *
 * storage_key is namespaced as "<bucket>/<filename>" for both drivers so one uploaded_files
 * table can hold rows from either backend without ambiguity.
 */

declare(strict_types=1);

require_once __DIR__ . '/cloud_storage_config.php';

/** @return \Aws\S3\S3Client */
function s3_client(): object
{
    static $client = null;
    if ($client === null) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $config = [
            'version' => 'latest',
            'region' => s3_region(),
            'credentials' => [
                'key' => s3_access_key_id(),
                'secret' => s3_secret_access_key(),
            ],
        ];
        $endpoint = s3_endpoint();
        if ($endpoint !== '') {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = true;
        }
        $client = new \Aws\S3\S3Client($config);
    }
    return $client;
}

/**
 * Saves an uploaded temp file under the given logical bucket ("students" or "certificates")
 * with a server-generated filename, using whichever driver is currently configured.
 *
 * @return array{provider:string,key:string}
 */
function storage_put(string $tmpPath, string $bucket, string $filename): array
{
    $driver = storage_driver();
    $key = $bucket . '/' . $filename;

    if ($driver === 's3') {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $client = s3_client();
        $client->putObject([
            'Bucket' => s3_bucket(),
            'Key' => $key,
            'SourceFile' => $tmpPath,
            'ContentType' => $finfo->file($tmpPath) ?: 'application/octet-stream',
            // No ACL set: the bucket stays private. Files are only ever read back through
            // our own authenticated endpoints via getObject(), never a public URL.
        ]);
        return ['provider' => 's3', 'key' => $key];
    }

    $destDir = __DIR__ . '/../uploads/' . $bucket;
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException('Could not prepare the upload directory');
    }
    if (!move_uploaded_file($tmpPath, $destDir . '/' . $filename)) {
        throw new RuntimeException('Could not save the file');
    }
    return ['provider' => 'local', 'key' => $key];
}

/** Streams a stored object to the current HTTP response and exits. Caller sets no headers first. */
function storage_stream(string $provider, string $key, string $mimeType, ?string $downloadName = null): void
{
    $basename = basename($key);

    if ($provider === 's3') {
        try {
            $result = s3_client()->getObject([
                'Bucket' => s3_bucket(),
                'Key' => $key,
            ]);
        } catch (\Throwable $e) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
        header('Content-Type: ' . ($result['ContentType'] ?? $mimeType));
        header('Content-Length: ' . (string) ($result['ContentLength'] ?? strlen((string) $result['Body'])));
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="' . ($downloadName ?: $basename) . '"');
        echo (string) $result['Body'];
        exit;
    }

    // local: key is "<bucket>/<filename>" — split so we only ever read inside uploads/.
    $slash = strpos($key, '/');
    $bucket = $slash === false ? '' : substr($key, 0, $slash);
    $uploadDir = realpath(__DIR__ . '/../uploads/' . $bucket);
    $path = $uploadDir === false ? false : $uploadDir . DIRECTORY_SEPARATOR . $basename;

    if ($uploadDir === false || $path === false || !is_file($path)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }

    $detected = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    header('Content-Type: ' . ($detected ?: $mimeType));
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, max-age=3600');
    header('Content-Disposition: inline; filename="' . ($downloadName ?: $basename) . '"');
    readfile($path);
    exit;
}

/** Best-effort delete; a failure here should never block the operation that called it. */
function storage_delete(string $provider, string $key): void
{
    try {
        if ($provider === 's3') {
            s3_client()->deleteObject(['Bucket' => s3_bucket(), 'Key' => $key]);
            return;
        }
        $slash = strpos($key, '/');
        $bucket = $slash === false ? '' : substr($key, 0, $slash);
        $path = __DIR__ . '/../uploads/' . $bucket . '/' . basename($key);
        if (is_file($path)) {
            @unlink($path);
        }
    } catch (\Throwable $e) {
        // Swallow: losing track of an old file is far less harmful than failing the request.
    }
}

/**
 * Records a new upload in uploaded_files and marks any previously-active record for the
 * same student/course inactive, so there is always exactly one "active" row per reference.
 * The caller decides separately whether to also delete the old file from storage (photos)
 * or keep it in place as history (certificates) — this function only ever touches rows.
 */
function storage_record_upload(
    PDO $pdo,
    string $fileType,
    int $referenceId,
    string $provider,
    string $key,
    string $mimeType,
    int $sizeBytes,
    ?string $uploadedByRole,
    ?int $uploadedById
): int {
    $deactivate = $pdo->prepare(
        'UPDATE uploaded_files SET is_active = 0 WHERE file_type = :type AND reference_id = :ref AND is_active = 1'
    );
    $deactivate->execute([':type' => $fileType, ':ref' => $referenceId]);

    $insert = $pdo->prepare(
        'INSERT INTO uploaded_files
            (file_type, reference_id, storage_provider, storage_key, mime_type, file_size_bytes, is_active, uploaded_by_role, uploaded_by_id)
         VALUES (:type, :ref, :provider, :key, :mime, :size, 1, :role, :uid)'
    );
    $insert->execute([
        ':type' => $fileType,
        ':ref' => $referenceId,
        ':provider' => $provider,
        ':key' => $key,
        ':mime' => $mimeType,
        ':size' => $sizeBytes,
        ':role' => $uploadedByRole,
        ':uid' => $uploadedById,
    ]);
    return (int) $pdo->lastInsertId();
}

/** The current active uploaded_files row for a student/course, or null. */
function storage_current(PDO $pdo, string $fileType, int $referenceId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, file_type, reference_id, storage_provider, storage_key, url, mime_type,
                file_size_bytes, is_active, uploaded_by_role, uploaded_by_id, created_at
         FROM uploaded_files WHERE file_type = :type AND reference_id = :ref AND is_active = 1
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':type' => $fileType, ':ref' => $referenceId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
