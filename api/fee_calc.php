<?php
/**
 * Single source of truth for student fee figures.
 *   Course fee  = courses.total_fee (the course's current configured fee)
 *   Total paid  = SUM(transactions.amount) for the student
 *   Pending     = MAX(course fee - total paid, 0)
 *   Status      = Pending (paid <= 0), Paid in Full (paid >= fee), otherwise Partial
 * Every query that shows fees, counts payments or checks balances uses these fragments,
 * so screens cannot drift apart. The stored students.amount_paid/balance_due columns are
 * kept in sync on writes but are not trusted for reads.
 */

declare(strict_types=1);

const FEE_PAID_SQL = 'COALESCE(tx.paid, 0)';
const FEE_STATUS_SQL = "CASE
    WHEN COALESCE(tx.paid, 0) <= 0 THEN 'Pending'
    WHEN COALESCE(tx.paid, 0) >= c.total_fee THEN 'Paid in Full'
    ELSE 'Partial' END";

/** Joins the payment total for each student. Use with a students alias s and courses alias c. */
const FEE_JOIN_SQL = 'LEFT JOIN (SELECT student_id, SUM(amount) AS paid FROM transactions GROUP BY student_id) tx ON tx.student_id = s.id';

/** Fee columns for a SELECT over students s and courses c. */
const FEE_SELECT_SQL = 'c.total_fee AS total_fee,
    ' . FEE_PAID_SQL . ' AS amount_paid,
    GREATEST(c.total_fee - ' . FEE_PAID_SQL . ', 0) AS balance_due,
    ' . FEE_STATUS_SQL . ' AS status';

/** Outstanding amount for one student, computed in SQL. Negative values never reach callers. */
function outstanding_for_student(PDO $pdo, int $studentId): float
{
    $stmt = $pdo->prepare(
        "SELECT GREATEST(c.total_fee - " . FEE_PAID_SQL . ", 0)
         FROM students s
         JOIN courses c ON c.id = s.course_id
         " . FEE_JOIN_SQL . "
         WHERE s.id = :id"
    );
    $stmt->execute([':id' => $studentId]);
    $value = $stmt->fetchColumn();
    return $value === false ? 0.0 : (float) $value;
}
