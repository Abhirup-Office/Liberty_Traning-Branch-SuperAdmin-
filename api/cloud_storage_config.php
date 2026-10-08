<?php
/**
 * File storage configuration.
 *
 * STORAGE_DRIVER controls where new uploads are written:
 *   'local' (default) — saved under uploads/ on this server, exactly as before. Requires
 *                        no setup and is what runs until real cloud credentials are supplied.
 *   's3'               — uploaded to an S3-compatible bucket (AWS S3, Cloudflare R2,
 *                        Backblaze B2, DigitalOcean Spaces, MinIO, …). The bucket is kept
 *                        PRIVATE — objects are never given a public URL. Files are still
 *                        only readable through our own authenticated streaming endpoints
 *                        (api/get_student_photo.php, api/student_photo.php,
 *                        api/get_course_certificate.php, api/student_certificate.php),
 *                        which fetch the object server-side and check scope first, exactly
 *                        like the local driver does today.
 *
 * To switch to S3: set the AWS_S3_* environment variables below (or edit the fallback
 * values directly in this file, same pattern as api/google_config.php), then set the
 * STORAGE_DRIVER environment variable to "s3". Nothing else in the codebase needs to change.
 *
 * Never commit real secrets. Prefer environment variables in production.
 */

declare(strict_types=1);

const STORAGE_DRIVER = null; // unused marker; real value is read from getenv() at call time

/** Which backend new uploads use: 'local' or 's3'. */
function storage_driver(): string
{
    return getenv('STORAGE_DRIVER') ?: 'local';
}

/** S3 bucket name. */
function s3_bucket(): string
{
    return getenv('AWS_S3_BUCKET') ?: 'YOUR_BUCKET_NAME';
}

/** S3 region. */
function s3_region(): string
{
    return getenv('AWS_S3_REGION') ?: 'us-east-1';
}

/** Custom endpoint for S3-compatible providers (R2, Backblaze, Spaces, MinIO). Empty = real AWS. */
function s3_endpoint(): string
{
    return getenv('AWS_S3_ENDPOINT') ?: '';
}

function s3_access_key_id(): string
{
    return getenv('AWS_ACCESS_KEY_ID') ?: 'YOUR_ACCESS_KEY_ID';
}

function s3_secret_access_key(): string
{
    return getenv('AWS_SECRET_ACCESS_KEY') ?: 'YOUR_SECRET_ACCESS_KEY';
}
