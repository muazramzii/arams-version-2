<?php
// ============================================================
//  ARAMS — Manage Research Groups API (Task 2b)
//  Actions: list | add | edit | toggle   (Admin only)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');

header('Content-Type: application/json');

$db     = getDB();
$action = $_POST['action'] ?? ($_GET['action'] ?? 'list');

switch ($action) {

    // ── List all groups (active + inactive) ──────────────────
    case 'list':
        $rows = $db->query(
            "SELECT g.group_id, g.group_code, g.group_name, g.faculty_id,
                    g.is_active, f.faculty_code
             FROM tbl_research_group g
             LEFT JOIN tbl_faculty f ON f.faculty_id = g.faculty_id
             ORDER BY g.is_active DESC, g.group_name"
        )->fetchAll();
        jsonResponse(true, 'OK', ['groups' => $rows]);
        break;

    // ── Add a new group ──────────────────────────────────────
    case 'add':
        $name    = sanitize($_POST['group_name'] ?? '');
        $code    = sanitize($_POST['group_code'] ?? '');
        $facId   = ($_POST['faculty_id'] ?? '') !== '' ? (int)$_POST['faculty_id'] : null;
        if (!$name) jsonResponse(false, 'Group name is required.');

        $dup = $db->prepare("SELECT group_id FROM tbl_research_group WHERE group_name = ?");
        $dup->execute([$name]);
        if ($dup->fetch()) jsonResponse(false, 'A group with this name already exists.');

        $db->prepare(
            "INSERT INTO tbl_research_group (group_code, group_name, faculty_id, is_active)
             VALUES (?,?,?,1)"
        )->execute([$code ?: null, $name, $facId]);
        jsonResponse(true, 'Research group added.');
        break;

    // ── Edit an existing group ───────────────────────────────
    case 'edit':
        $gid   = (int)($_POST['group_id'] ?? 0);
        $name  = sanitize($_POST['group_name'] ?? '');
        $code  = sanitize($_POST['group_code'] ?? '');
        $facId = ($_POST['faculty_id'] ?? '') !== '' ? (int)$_POST['faculty_id'] : null;
        if (!$gid || !$name) jsonResponse(false, 'Group and name are required.');

        $dup = $db->prepare("SELECT group_id FROM tbl_research_group WHERE group_name = ? AND group_id <> ?");
        $dup->execute([$name, $gid]);
        if ($dup->fetch()) jsonResponse(false, 'Another group already uses this name.');

        $db->prepare(
            "UPDATE tbl_research_group SET group_code=?, group_name=?, faculty_id=? WHERE group_id=?"
        )->execute([$code ?: null, $name, $facId, $gid]);
        jsonResponse(true, 'Research group updated.');
        break;

    // ── Activate / deactivate (soft) ─────────────────────────
    case 'toggle':
        $gid    = (int)($_POST['group_id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 0);
        if (!$gid) jsonResponse(false, 'Missing group id.');
        $db->prepare("UPDATE tbl_research_group SET is_active=? WHERE group_id=?")
           ->execute([$active, $gid]);
        jsonResponse(true, $active ? 'Group activated.' : 'Group deactivated.');
        break;

    default:
        jsonResponse(false, 'Unknown action.');
}