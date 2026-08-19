<?php
// ============================================================
//  ARAMS — KPI Auto-Completion Engine
//  Called after a research submission is APPROVED.
//  Checks all of a lecturer's pending KPI tasks and
//  auto-completes any whose type + criteria are now met.
// ============================================================

function runKpiAutoComplete(PDO $db, int $lecturerId): void
{
    // Get all active (not completed) tasks for this lecturer
    $tasks = $db->prepare(
        "SELECT * FROM tbl_kpi_task
         WHERE lecturer_id = ?
           AND status IN ('Pending','In Progress','Overdue')"
    );
    $tasks->execute([$lecturerId]);
    $tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tasks as $task) {
        $count = countMatchingItems($db, $lecturerId, $task);

        if ($count <= 0) continue;

        // Update progress
        $newStatus = 'In Progress';
        $completedDate = null;

        if ($count >= (int)$task['target_count']) {
            // Task is fully met — check if late
            $isLate = strtotime(date('Y-m-d')) > strtotime($task['deadline']);
            $newStatus = $isLate ? 'Completed (Late)' : 'Completed';
            $completedDate = date('Y-m-d');
        }

        $upd = $db->prepare(
            "UPDATE tbl_kpi_task
             SET progress_count=?, status=?, completed_date=?
             WHERE task_id=?"
        );
        $upd->execute([$count, $newStatus, $completedDate, $task['task_id']]);

        // Notify lecturer + TDPP when fully completed
        if ($completedDate) {
            notifyTaskCompletion($db, $task, $newStatus);
        }
    }
}

// Count how many APPROVED items match this task's type + criteria
function countMatchingItems(PDO $db, int $lecturerId, array $task): int
{
    switch ($task['task_type']) {
        case 'Publication':
            $sql = "SELECT COUNT(*) FROM tbl_publication p
                    JOIN tbl_research_data rd ON p.data_id=rd.data_id
                    WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0";
            $params = [$lecturerId];
            if ($task['criteria_quartile'] !== 'Any') {
                $sql .= " AND p.quartile=?"; $params[] = $task['criteria_quartile'];
            }
            if ($task['criteria_indexing'] !== 'Any') {
                $sql .= " AND p.indexing_type=?"; $params[] = $task['criteria_indexing'];
            }
            break;

        case 'Grant':
            $sql = "SELECT COUNT(*) FROM tbl_grant g
                    JOIN tbl_research_data rd ON g.data_id=rd.data_id
                    WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0";
            $params = [$lecturerId];
            if ($task['criteria_grant_level'] !== 'Any') {
                $sql .= " AND g.grant_level=?"; $params[] = $task['criteria_grant_level'];
            }
            if ((float)$task['criteria_min_amount'] > 0) {
                $sql .= " AND g.amount >= ?"; $params[] = $task['criteria_min_amount'];
            }
            break;

        case 'H-Index':
            $sql = "SELECT COUNT(*) FROM tbl_hindex h
                    JOIN tbl_research_data rd ON h.data_id=rd.data_id
                    WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0";
            $params = [$lecturerId];
            break;

        case 'Research Income':
            $sql = "SELECT COUNT(*) FROM tbl_research_income i
                    JOIN tbl_research_data rd ON i.data_id=rd.data_id
                    WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0";
            $params = [$lecturerId];
            if ((float)$task['criteria_min_amount'] > 0) {
                $sql .= " AND i.amount >= ?"; $params[] = $task['criteria_min_amount'];
            }
            break;

        case 'IP':
            $sql = "SELECT COUNT(*) FROM tbl_ip_record ip
                    JOIN tbl_research_data rd ON ip.data_id=rd.data_id
                    WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0";
            $params = [$lecturerId];
            break;

        case 'Award':
            $sql = "SELECT COUNT(*) FROM tbl_award WHERE lecturer_id=?";
            $params = [$lecturerId];
            break;

        default:
            return 0;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function notifyTaskCompletion(PDO $db, array $task, string $status): void
{
    // Notify lecturer
    $lecUser = $db->prepare("SELECT user_id FROM tbl_lecturer WHERE lecturer_id=?");
    $lecUser->execute([$task['lecturer_id']]);
    $luid = $lecUser->fetchColumn();
    if ($luid) {
        $db->prepare("INSERT INTO tbl_notification (user_id, message, is_read, created_at) VALUES (?,?,0,NOW())")
           ->execute([$luid, "KPI task auto-completed ($status): " . $task['task_title']]);
    }
    // Notify TDPP
    $tdppUser = $db->prepare("SELECT user_id FROM tbl_tdpp WHERE tdpp_id=?");
    $tdppUser->execute([$task['tdpp_id']]);
    $tuid = $tdppUser->fetchColumn();
    if ($tuid) {
        $db->prepare("INSERT INTO tbl_notification (user_id, message, is_read, created_at) VALUES (?,?,0,NOW())")
           ->execute([$tuid, "Lecturer completed KPI ($status): " . $task['task_title']]);
    }
}