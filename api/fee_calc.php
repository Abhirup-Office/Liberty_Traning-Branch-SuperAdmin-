<?php
/**
 * Single source of truth for student fee figures.
 *   Student fee = students.total_fee — defaulted from the course's listed fee at enrollment
 *                 time, but then an independent, per-student value from that point on. A
 *                 Branch Admin or Super Admin can edit it (e.g. a negotiated discount), and it
 *                 deliberately does NOT track courses.total_fee afterward — if the course's
 *                 listed price changes later, already-enrolled students keep their own figure.
 *   Total paid  = SUM(transactions.amount) for the student
 *   Pending     = MAX(student's fee - total paid, 0)
 *   Status      = Pending (paid <= 0), Paid in Full (paid >= fee), otherwise Partial
 * Every query that shows fees, counts payments or checks balances uses these fragments,
 * so screens cannot drift apart. The stored students.amount_paid/balance_due columns are
 * kept in sync on writes but are not trusted for reads.
 */

declare(strict_types=1);

const FEE_PAID_SQL = 'COALESCE(tx.paid, 0)';
const FEE_STATUS_SQL = "CASE
    WHEN COALESCE(tx.paid, 0) <= 0 THEN 'Pending'
    WHEN COALESCE(tx.paid, 0) >= s.total_fee THEN 'Paid in Full'
    ELSE 'Partial' END";

/** Joins the payment total for each student. Use with a students alias s. */
const FEE_JOIN_SQL = 'LEFT JOIN (SELECT student_id, SUM(amount) AS paid FROM transactions GROUP BY student_id) tx ON tx.student_id = s.id';

/** Fee columns for a SELECT over students s. */
const FEE_SELECT_SQL = 's.total_fee AS total_fee,
    ' . FEE_PAID_SQL . ' AS amount_paid,
    GREATEST(s.total_fee - ' . FEE_PAID_SQL . ', 0) AS balance_due,
    ' . FEE_STATUS_SQL . ' AS status';

/**
 * Same aggregation as FEE_JOIN_SQL, but pre-filtered to one branch's transactions. The plain
 * FEE_JOIN_SQL aggregates every transaction system-wide before any outer WHERE/LIMIT is
 * applied (it's a GROUP BY subquery, so MySQL must materialize it in full); once a request is
 * already scoped to a single branch (a Branch Admin's own directory, or a Super Admin
 * filtering to one branch), there's no reason to pay for every other branch's transactions
 * too. Callers using this must bind :fee_scope_branch_id in their params.
 */
function fee_join_sql(): string
{
    return 'LEFT JOIN (SELECT t.student_id, SUM(t.amount) AS paid
                        FROM transactions t
                        JOIN students st ON st.id = t.student_id
                        WHERE st.branch_id = :fee_scope_branch_id
                        GROUP BY t.student_id) tx ON tx.student_id = s.id';
}

/** Outstanding amount for one student, computed in SQL. Negative values never reach callers. */
function outstanding_for_student(PDO $pdo, int $studentId): float
{
    $stmt = $pdo->prepare(
        "SELECT GREATEST(s.total_fee - " . FEE_PAID_SQL . ", 0)
         FROM students s
         " . FEE_JOIN_SQL . "
         WHERE s.id = :id"
    );
    $stmt->execute([':id' => $studentId]);
    $value = $stmt->fetchColumn();
    return $value === false ? 0.0 : (float) $value;
}
