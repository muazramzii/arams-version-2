<?php
// ============================================================
//  ARAMS — TDPP KPI Task Management
//  Create, assign, and monitor lecturer KPI tasks
// ============================================================
$pageTitle  = 'KPI Tasks';
$activePage = 'kpi';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Get TDPP + faculty
$tdpp = $db->prepare(
    "SELECT t.*, f.faculty_code, f.faculty_name
     FROM tbl_tdpp t JOIN tbl_faculty f ON f.faculty_id=t.faculty_id
     WHERE t.user_id=?"
);
$tdpp->execute([$_SESSION['user_id']]);
$tdpp = $tdpp->fetch();
$facId  = $tdpp['faculty_id'];
$tdppId = $tdpp['tdpp_id'];

// Lecturers in this faculty (for assign dropdown)
$lecturers = $db->prepare(
    "SELECT lecturer_id, full_name, staff_no FROM tbl_lecturer
     WHERE faculty_id=? ORDER BY full_name"
);
$lecturers->execute([$facId]);
$lecturers = $lecturers->fetchAll();

// Refresh overdue status before display
$db->prepare(
    "UPDATE tbl_kpi_task SET status='Overdue'
     WHERE status IN ('Pending','In Progress')
       AND deadline < CURDATE()
       AND tdpp_id=?"
)->execute([$tdppId]);

// All tasks
$tasks = $db->prepare(
    "SELECT kt.*, l.full_name AS lecturer_name, l.staff_no
     FROM tbl_kpi_task kt
     JOIN tbl_lecturer l ON l.lecturer_id=kt.lecturer_id
     WHERE kt.tdpp_id=?
     ORDER BY
        FIELD(kt.status,'Overdue','In Progress','Pending','Completed (Late)','Completed'),
        kt.deadline ASC"
);
$tasks->execute([$tdppId]);
$tasks = $tasks->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:1rem">
    <div>
        <h2 style="margin:0;font-size:20px">KPI Task Management</h2>
        <p style="margin:4px 0 0;color:var(--muted);font-size:13px">
            Assign and monitor research KPIs for <?= htmlspecialchars($tdpp['faculty_code']) ?> lecturers.
            Tasks auto-complete when matching research is approved.
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <button class="btn btn-outline" onclick="document.getElementById('bulkModal').style.display='flex'">
            <i class="fas fa-users"></i> Bulk Assign
        </button>
        <button class="btn btn-teal" onclick="resetModalToCreate();document.getElementById('taskModal').style.display='flex'">
            <i class="fas fa-plus"></i> Assign New KPI
        </button>
    </div>
</div>

<!-- Tasks Table -->
<div class="card">
    <div class="card-title"><i class="fas fa-list-check" style="color:var(--teal)"></i> All Assigned KPIs (<?= count($tasks) ?>)</div>
    <div class="table-wrap">
        <table class="arams-table">
            <thead>
                <tr><th>Lecturer</th><th>Task</th><th>Type</th><th>Criteria</th>
                    <th>Progress</th><th>Deadline</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t):
                $badge = match($t['status']) {
                    'Completed'        => 'badge-green',
                    'Completed (Late)' => 'badge-yellow',
                    'Overdue'          => 'badge-red',
                    'In Progress'      => 'badge-blue',
                    default            => 'badge-grey'
                };
                $crit = [];
                if ($t['criteria_quartile']     !== 'Any') $crit[] = $t['criteria_quartile'];
                if ($t['criteria_indexing']     !== 'Any') $crit[] = $t['criteria_indexing'];
                if ($t['criteria_grant_level']  !== 'Any') $crit[] = $t['criteria_grant_level'];
                if ($t['criteria_min_amount'] > 0)         $crit[] = '≥RM' . number_format($t['criteria_min_amount']);
                $critStr = $crit ? implode(', ', $crit) : 'Any';
            ?>
            <tr>
                <td style="font-weight:600;font-size:13px"><?= htmlspecialchars($t['lecturer_name']) ?></td>
                <td style="font-size:12px;max-width:200px">
                    <?= htmlspecialchars($t['task_title']) ?>
                    <?php if ($t['task_desc']): ?>
                    <div style="font-size:10px;color:var(--muted)"><?= htmlspecialchars(substr($t['task_desc'],0,60)) ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($t['task_type']) ?></span></td>
                <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($critStr) ?></td>
                <td style="font-size:12px;font-weight:600">
                    <?= (int)$t['progress_count'] ?> / <?= (int)$t['target_count'] ?>
                </td>
                <td style="font-size:12px;color:var(--muted)"><?= $t['deadline'] ?></td>
                <td>
                    <span class="badge <?= $badge ?>" style="font-size:10px"><?= htmlspecialchars($t['status']) ?></span>
                    <?php if ($t['completed_date']): ?>
                    <div style="font-size:9px;color:var(--muted);margin-top:2px"><?= $t['completed_date'] ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-primary btn-sm"
                        onclick='editTask(<?= json_encode([
                            "task_id"=>$t["task_id"],
                            "lecturer_id"=>$t["lecturer_id"],
                            "task_title"=>$t["task_title"],
                            "task_desc"=>$t["task_desc"],
                            "task_type"=>$t["task_type"],
                            "target_count"=>$t["target_count"],
                            "criteria_quartile"=>$t["criteria_quartile"],
                            "criteria_indexing"=>$t["criteria_indexing"],
                            "criteria_grant_level"=>$t["criteria_grant_level"],
                            "criteria_min_amount"=>$t["criteria_min_amount"],
                            "deadline"=>$t["deadline"],
                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteTask(<?= $t['task_id'] ?>, '<?= addslashes($t['task_title']) ?>')">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2.5rem">
                <i class="fas fa-clipboard-list" style="font-size:32px;opacity:.3;display:block;margin-bottom:8px"></i>
                No KPI tasks assigned yet. Click "Assign New KPI" to create your first task.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Assign Task Modal ────────────────────────────────── -->
<div id="taskModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     z-index:1000;align-items:center;justify-content:center;padding:1rem">
    <div style="background:#fff;border-radius:12px;max-width:560px;width:100%;
         max-height:90vh;overflow-y:auto;padding:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h3 style="margin:0">Assign New KPI Task</h3>
            <button onclick="document.getElementById('taskModal').style.display='none'"
                    style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted)">×</button>
        </div>

        <form id="kpiForm">
            <div class="form-group">
                <label class="form-label">Lecturer *</label>
                <select class="form-control" name="lecturer_id" required>
                    <option value="">— Select Lecturer —</option>
                    <?php foreach ($lecturers as $l): ?>
                    <option value="<?= $l['lecturer_id'] ?>">
                        <?= htmlspecialchars($l['full_name']) ?> (<?= htmlspecialchars($l['staff_no']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Task Title *</label>
                <input type="text" class="form-control" name="task_title" required
                       placeholder="e.g. Publish 2 Scopus Q1 journal articles">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="task_desc" rows="2"
                          placeholder="Optional details or instructions"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Task Type *</label>
                    <select class="form-control" name="task_type" id="taskType" required onchange="toggleCriteria()">
                        <option value="Publication">Publication</option>
                        <option value="Grant">Grant</option>
                        <option value="H-Index">H-Index</option>
                        <option value="Research Income">Research Income</option>
                        <option value="IP">IP</option>
                        <option value="Award">Award</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Target Count *</label>
                    <input type="number" class="form-control" name="target_count" value="1" min="1" required>
                </div>
            </div>

            <!-- Publication criteria -->
            <div id="critPublication" class="crit-block">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Required Quartile</label>
                        <select class="form-control" name="criteria_quartile">
                            <option value="Any">Any</option>
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Required Indexing</label>
                        <select class="form-control" name="criteria_indexing">
                            <option value="Any">Any</option>
                            <option value="WOS">WOS</option>
                            <option value="Scopus">Scopus</option>
                            <option value="MyCite">MyCite</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Grant criteria -->
            <div id="critGrant" class="crit-block" style="display:none">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Grant Level</label>
                        <select class="form-control" name="criteria_grant_level">
                            <option value="Any">Any</option>
                            <option value="Universiti">Universiti</option>
                            <option value="National">National</option>
                            <option value="International">International</option>
                            <option value="Industries">Industries</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Amount (RM)</label>
                        <input type="number" class="form-control" name="criteria_min_amount" value="0" min="0">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Deadline *</label>
                <input type="date" class="form-control" name="deadline" required>
            </div>

            <div style="display:flex;gap:8px;margin-top:1rem">
                <button type="button" class="btn btn-outline" style="flex:1"
                        onclick="document.getElementById('taskModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-teal" style="flex:1">
                    <i class="fas fa-paper-plane"></i> Assign Task
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Bulk Assign Modal ────────────────────────────────── -->
<div id="bulkModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     z-index:1000;align-items:center;justify-content:center;padding:1rem">
    <div style="background:#fff;border-radius:12px;max-width:600px;width:100%;
         max-height:90vh;overflow-y:auto;padding:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h3 style="margin:0">Bulk Assign KPI Task</h3>
            <button onclick="document.getElementById('bulkModal').style.display='none'"
                    style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted)">×</button>
        </div>
        <p style="font-size:12px;color:var(--muted);margin-top:0">
            Select multiple lecturers — the same KPI task will be created for each one.
        </p>

        <form id="bulkForm">
            <div class="form-group">
                <label class="form-label">Lecturers * <span id="bulkCount" style="color:var(--teal);font-weight:600"></span></label>
                <div style="border:1px solid var(--border,#e2e8f0);border-radius:8px;padding:.5rem;max-height:160px;overflow-y:auto">
                    <label style="display:flex;align-items:center;gap:6px;padding:4px 6px;font-size:12px;font-weight:600;border-bottom:1px solid #f1f5f9;cursor:pointer">
                        <input type="checkbox" id="bulkSelectAll" onchange="bulkToggleAll(this)"> Select All
                    </label>
                    <?php foreach ($lecturers as $l): ?>
                    <label style="display:flex;align-items:center;gap:6px;padding:5px 6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" class="bulk-lec" name="lecturer_ids[]" value="<?= $l['lecturer_id'] ?>" onchange="bulkUpdateCount()">
                        <?= htmlspecialchars($l['full_name']) ?>
                        <span style="color:var(--muted);font-size:11px">(<?= htmlspecialchars($l['staff_no']) ?>)</span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Task Title *</label>
                <input type="text" class="form-control" name="task_title" required
                       placeholder="e.g. Publish 1 Scopus Q1 journal article">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="task_desc" rows="2" placeholder="Optional"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Task Type *</label>
                    <select class="form-control" name="task_type" id="bulkType" required onchange="bulkToggleCriteria()">
                        <option value="Publication">Publication</option>
                        <option value="Grant">Grant</option>
                        <option value="H-Index">H-Index</option>
                        <option value="Research Income">Research Income</option>
                        <option value="IP">IP</option>
                        <option value="Award">Award</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Target Count *</label>
                    <input type="number" class="form-control" name="target_count" value="1" min="1" required>
                </div>
            </div>

            <div id="bulkCritPublication" class="bulk-crit">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Required Quartile</label>
                        <select class="form-control" name="criteria_quartile">
                            <option value="Any">Any</option><option value="Q1">Q1</option>
                            <option value="Q2">Q2</option><option value="Q3">Q3</option><option value="Q4">Q4</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Required Indexing</label>
                        <select class="form-control" name="criteria_indexing">
                            <option value="Any">Any</option><option value="WOS">WOS</option>
                            <option value="Scopus">Scopus</option><option value="MyCite">MyCite</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="bulkCritGrant" class="bulk-crit" style="display:none">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Grant Level</label>
                        <select class="form-control" name="criteria_grant_level">
                            <option value="Any">Any</option><option value="Universiti">Universiti</option>
                            <option value="National">National</option><option value="International">International</option>
                            <option value="Industries">Industries</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Amount (RM)</label>
                        <input type="number" class="form-control" name="criteria_min_amount" value="0" min="0">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Deadline *</label>
                <input type="date" class="form-control" name="deadline" required>
            </div>

            <div style="display:flex;gap:8px;margin-top:1rem">
                <button type="button" class="btn btn-outline" style="flex:1"
                        onclick="document.getElementById('bulkModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-teal" style="flex:1">
                    <i class="fas fa-users"></i> Assign to Selected
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bulkToggleCriteria() {
    var type = document.getElementById('bulkType').value;
    document.querySelectorAll('.bulk-crit').forEach(b => b.style.display='none');
    if (type === 'Publication') document.getElementById('bulkCritPublication').style.display='block';
    if (type === 'Grant')       document.getElementById('bulkCritGrant').style.display='block';
}
function bulkToggleAll(master) {
    document.querySelectorAll('.bulk-lec').forEach(c => c.checked = master.checked);
    bulkUpdateCount();
}
function bulkUpdateCount() {
    var n = document.querySelectorAll('.bulk-lec:checked').length;
    document.getElementById('bulkCount').textContent = n > 0 ? '(' + n + ' selected)' : '';
}
document.getElementById('bulkForm').addEventListener('submit', function(e){
    e.preventDefault();
    var n = document.querySelectorAll('.bulk-lec:checked').length;
    if (n === 0) { showToast('Please select at least one lecturer', 'error'); return; }
    var fd = new FormData(this);
    fetch('/arams/api/bulk_assign_kpi.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message || 'Tasks assigned!', 'success');
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(res.message || 'Failed', 'error');
            }
        })
        .catch(() => showToast('Error assigning tasks', 'error'));
});
</script>

<script>
function toggleCriteria() {
    var type = document.getElementById('taskType').value;
    document.querySelectorAll('.crit-block').forEach(b => b.style.display='none');
    if (type === 'Publication') document.getElementById('critPublication').style.display='block';
    if (type === 'Grant')       document.getElementById('critGrant').style.display='block';
}

document.getElementById('kpiForm').addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData(this);
    var url = '/arams/api/assign_kpi.php';
    if (editingTaskId) {
        url = '/arams/api/update_kpi.php';
        fd.append('task_id', editingTaskId);
    }
    fetch(url, { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(editingTaskId ? 'KPI task updated!' : 'KPI task assigned successfully!', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Failed', 'error');
            }
        })
        .catch(() => showToast('Error saving task', 'error'));
});
</script>
<script>
function deleteTask(taskId, title) {
    confirmDialog({
        title: 'Delete this KPI task?',
        message: '"' + title + '"<br>This cannot be undone.',
        confirmText: 'Delete',
        danger: true,
        onConfirm: function(){
            fetch('/arams/api/delete_kpi.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ task_id: taskId })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) { showToast('KPI task deleted', 'success'); setTimeout(() => location.reload(), 600); }
                else { showToast(res.message || 'Failed to delete', 'error'); }
            })
            .catch(() => showToast('Error deleting task', 'error'));
        }
    });
}

</script>
<script>
// Track whether the modal is in "edit" mode and which task
var editingTaskId = null;

function editTask(t) {
    editingTaskId = t.task_id;
    var form = document.getElementById('kpiForm');

    // Pre-fill all fields
    form.querySelector('[name=lecturer_id]').value          = t.lecturer_id;
    form.querySelector('[name=task_title]').value           = t.task_title || '';
    form.querySelector('[name=task_desc]').value            = t.task_desc || '';
    form.querySelector('[name=task_type]').value            = t.task_type;
    form.querySelector('[name=target_count]').value         = t.target_count;
    form.querySelector('[name=criteria_quartile]').value    = t.criteria_quartile || 'Any';
    form.querySelector('[name=criteria_indexing]').value    = t.criteria_indexing || 'Any';
    form.querySelector('[name=criteria_grant_level]').value = t.criteria_grant_level || 'Any';
    form.querySelector('[name=criteria_min_amount]').value  = t.criteria_min_amount || 0;
    form.querySelector('[name=deadline]').value             = t.deadline;

    // Show the right criteria block (publication vs grant)
    if (typeof toggleCriteria === 'function') toggleCriteria();

    // Change modal title + submit button to "edit" mode
    var titleEl = document.querySelector('#taskModal h3');
    if (titleEl) titleEl.textContent = 'Edit KPI Task';
    var submitBtn = form.querySelector('button[type=submit]');
    if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Task';

    // Open the modal
    document.getElementById('taskModal').style.display = 'flex';
}

// Reset modal back to "create" mode when opening via the normal "Assign New KPI" button
function resetModalToCreate() {
    editingTaskId = null;
    var form = document.getElementById('kpiForm');
    if (form) form.reset();
    var titleEl = document.querySelector('#taskModal h3');
    if (titleEl) titleEl.textContent = 'Assign New KPI Task';
    var submitBtn = form ? form.querySelector('button[type=submit]') : null;
    if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Assign Task';
    if (typeof toggleCriteria === 'function') toggleCriteria();
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>