<?php
/**
 * One-time migration for Phase 1 (Student Profile):
 *   - students: father_name, mother_name, date_of_birth, photo_path, display_id
 *   - student_id_sequences: a pure auto-increment counter. INSERT is atomic under InnoDB,
 *     so concurrent requests can never be handed the same sequence number — this is what
 *     makes display_id generation duplicate-proof under concurrent admins.
 *   - backfills course_code for the legacy courses that predate Course Management, so every
 *     course has a code to build a display_id from.
 *   - backfills display_id for existing students, oldest first, so numbering reflects
 *     actual enrollment order.
 * Additive only. No existing column, row or relationship is changed or removed.
 * Safe to re-run. Run from the project root:  php migrations/004_student_profile_fields.php
 */

declare(strict_types=1);
require __DIR__ . '/../api/student_id.php';

$pdo = new PDO(
    'mysql:host=localhost;dbname=liberty_tms;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (bool) $stmt->fetchColumn();
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = :t AND INDEX_NAME = :i"
    );
    $stmt->execute([':t' => $table, ':i' => $index]);
    return (bool) $stmt->fetchColumn();
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (bool) $stmt->fetchColumn();
}

// --- 1. student_id_sequences ---------------------------------------------------
if (!table_exists($pdo, 'student_id_sequences')) {
    $pdo->exec(
        "CREATE TABLE student_id_sequences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );
    echo "Created table student_id_sequences\n";
}

// --- 2. new students columns -----------------------------------------------------
$columns = [
    'father_name' => "ADD COLUMN father_name VARCHAR(100) NULL AFTER last_name",
    'mother_name' => "ADD COLUMN mother_name VARCHAR(100) NULL AFTER father_name",
    'date_of_birth' => "ADD COLUMN date_of_birth DATE NULL AFTER mother_name",
    'photo_path' => "ADD COLUMN photo_path VARCHAR(255) NULL AFTER address",
    'display_id' => "ADD COLUMN display_id VARCHAR(30) NULL AFTER student_code",
];
$added = [];
foreach ($columns as $name => $ddl) {
    if (!column_exists($pdo, 'students', $name)) {
        $pdo->exec("ALTER TABLE students $ddl");
        $added[] = $name;
    }
}
echo $added ? 'Added students columns: ' . implode(', ', $added) . "\n" : "students columns already present\n";

if (!index_exists($pdo, 'students', 'uq_students_display_id')) {
    $pdo->exec("ALTER TABLE students ADD UNIQUE KEY uq_students_display_id (display_id)");
    echo "Added unique index uq_students_display_id\n";
}
// Performance (audit §8/§9.6): supports "recent enrollments per branch" and status/search scans.
if (!index_exists($pdo, 'students', 'idx_students_branch_created')) {
    $pdo->exec("ALTER TABLE students ADD KEY idx_students_branch_created (branch_id, created_at)");
    echo "Added index idx_students_branch_created\n";
}
if (!index_exists($pdo, 'students', 'idx_students_phone')) {
    $pdo->exec("ALTER TABLE students ADD KEY idx_students_phone (phone)");
    echo "Added index idx_students_phone\n";
}

// --- 3. backfill course_code for legacy courses created before Course Management -----
$legacyCodes = [
    'Diploma in Computer Applications' => 'DCA',
    'Web Development Bootcamp' => 'WEB',
    'Tally with GST' => 'TALLY',
    'Spoken English' => 'ENGLISH',
    'Graphic Design' => 'GFX',
];
$codeStmt = $pdo->prepare("UPDATE courses SET course_code = :code WHERE course_name = :name AND course_code IS NULL");
$codedCount = 0;
foreach ($legacyCodes as $name => $code) {
    $codeStmt->execute([':code' => $code, ':name' => $name]);
    $codedCount += $codeStmt->rowCount();
}
echo "Backfilled course_code on $codedCount legacy course(s)\n";

$stillMissing = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE course_code IS NULL")->fetchColumn();
if ($stillMissing > 0) {
    echo "WARNING: $stillMissing course(s) still have no course_code; their students cannot get a display_id yet.\n";
}

// --- 4. backfill display_id for existing students, oldest first ---------------------
$missing = $pdo->query(
    "SELECT s.id, c.course_code
     FROM students s
     JOIN courses c ON c.id = s.course_id
     WHERE s.display_id IS NULL AND c.course_code IS NOT NULL
     ORDER BY s.created_at ASC, s.id ASC"
)->fetchAll();

$updateStmt = $pdo->prepare("UPDATE students SET display_id = :display_id WHERE id = :id");
foreach ($missing as $row) {
    $displayId = generate_unique_display_id($pdo, $row['course_code']);
    $updateStmt->execute([':display_id' => $displayId, ':id' => $row['id']]);
}
echo 'Assigned display_id to ' . count($missing) . " existing student(s)\n";

$stillUnassigned = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE display_id IS NULL")->fetchColumn();
if ($stillUnassigned > 0) {
    echo "WARNING: $stillUnassigned student(s) still have no display_id (their course has no course_code). Fix the course, then re-run this migration.\n";
}

echo "Migration complete\n";
