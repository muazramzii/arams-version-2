<?php
$pageTitle  = 'User Management';
$activePage = 'users';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$users = $db->query(
    "SELECT u.user_id, u.email, u.role, u.is_active, u.created_at, u.last_login,
            COALESCE(l.full_name, a.name, t.full_name) AS full_name,
            COALESCE(f.faculty_code, ft.faculty_code, 'Admin') AS faculty_code,
            l.staff_no, l.lecturer_id
     FROM tbl_user u
     LEFT JOIN tbl_lecturer l  ON l.user_id  = u.user_id
     LEFT JOIN tbl_admin    a  ON a.user_id  = u.user_id
     LEFT JOIN tbl_tdpp     t  ON t.user_id  = u.user_id
     LEFT JOIN tbl_faculty  f  ON f.faculty_id  = l.faculty_id
     LEFT JOIN tbl_faculty  ft ON ft.faculty_id = t.faculty_id
     ORDER BY u.role DESC, u.created_at ASC"
)->fetchAll();

$faculties = $db->query(
    "SELECT faculty_id, faculty_code, faculty_name FROM tbl_faculty ORDER BY faculty_code"
)->fetchAll();

$researchGroups = $db->query(
    "SELECT group_id, group_code, group_name, faculty_id
     FROM tbl_research_group
     WHERE is_active = 1
     ORDER BY group_name"
)->fetchAll();
?>

<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1>User Management</h1>
        <p>Manage all system accounts — lecturers, TDPP, and admins</p>
    </div>
    <div style="display:flex;gap:8px">
        <button class="btn btn-outline" onclick="openManageGroupsModal()">
            <i class="fas fa-users-cog"></i> Manage Groups
        </button>
        <button class="btn btn-teal" onclick="openCreateUserModal()">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>
</div>

<div class="search-row">
    <input type="text" class="search-input" placeholder="Search users…"
           oninput="filterTable(this,'userTable')">
    <select class="filter-select" onchange="filterBySelect(this,'userTable',2)">
        <option value="">All Roles</option>
        <option>Lecturer</option>
        <option>TDPP</option>
        <option>Admin</option>
    </select>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="arams-table" id="userTable">
            <thead><tr>
                <th>#</th><th>Name</th><th>Role</th><th>Status</th>
                <th>Email</th><th>Faculty</th><th>Last Login</th><th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
            <?php
                // avatar/badge colour per role
                $avatarColor = $u['role']==='Admin' ? 'var(--blue)'
                             : ($u['role']==='TDPP' ? '#8b5cf6' : 'var(--teal)');
                $roleBadge   = $u['role']==='Admin' ? 'badge-blue'
                             : ($u['role']==='TDPP' ? 'badge-purple' : 'badge-teal');
                $roleIcon    = $u['role']==='Admin' ? 'shield-alt'
                             : ($u['role']==='TDPP' ? 'user-check' : 'graduation-cap');
            ?>
            <tr id="urow-<?= $u['user_id'] ?>">
                <td style="color:var(--muted);font-size:12px"><?= $i+1 ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:9px">
                        <div style="width:32px;height:32px;border-radius:50%;
                                    background:<?= $avatarColor ?>;
                                    display:flex;align-items:center;justify-content:center;
                                    color:#fff;font-size:11px;font-weight:700;flex-shrink:0">
                            <?= strtoupper(substr($u['full_name']??'?',0,2)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($u['full_name']??'—') ?></div>
                            <?php if ($u['staff_no']): ?>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($u['staff_no']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge <?= $roleBadge ?>">
                        <i class="fas fa-<?= $roleIcon ?>" style="font-size:10px;margin-right:3px"></i>
                        <?= $u['role'] ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $u['is_active']?'badge-green':'badge-red' ?>">
                        <?= $u['is_active']?'Active':'Inactive' ?>
                    </span>
                </td>
                <td style="font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge badge-grey"><?= htmlspecialchars($u['faculty_code']) ?></span></td>
                <td style="font-size:12px;color:var(--muted)">
                    <?= $u['last_login'] ? date('d M Y',strtotime($u['last_login'])) : 'Never' ?>
                </td>
                <td>
                    <?php if ($u['role'] === 'TDPP'): ?>
                        <span class="badge badge-grey" style="font-size:11px">
                            <i class="fas fa-eye" style="font-size:10px;margin-right:3px"></i> View only
                        </span>
                    <?php else: ?>
                    <div style="display:flex;gap:5px">
                        <button class="btn btn-outline btn-sm"
                                onclick="openEditUserModal(
                                    <?= $u['user_id'] ?>,
                                    '<?= htmlspecialchars(addslashes($u['full_name']??'')) ?>',
                                    '<?= htmlspecialchars($u['email']) ?>',
                                    <?= $u['is_active'] ?>)">Edit</button>
                        <?php if ($u['is_active']): ?>
                        <button class="btn btn-danger btn-sm"
                                onclick="toggleUserStatus(<?= $u['user_id'] ?>,0)">Deactivate</button>
                        <?php else: ?>
                        <button class="btn btn-success btn-sm"
                                onclick="toggleUserStatus(<?= $u['user_id'] ?>,1)">Activate</button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="stat-row" style="margin-top:1rem;padding:1rem;background:var(--card);
     border:1px solid var(--border);border-radius:var(--radius)">
    <?php
    $total    = count($users);
    $active   = count(array_filter($users, fn($u) => $u['is_active']));
    $lecCount = count(array_filter($users, fn($u) => $u['role']==='Lecturer'));
    $tdppCount= count(array_filter($users, fn($u) => $u['role']==='TDPP'));
    $admCount = count(array_filter($users, fn($u) => $u['role']==='Admin'));
    ?>
    <div class="stat-item"><span>Total Users</span><strong><?= $total ?></strong></div>
    <div class="stat-item"><span>Active</span><strong><?= $active ?></strong></div>
    <div class="stat-item"><span>Lecturers</span><strong><?= $lecCount ?></strong></div>
    <div class="stat-item"><span>TDPP</span><strong><?= $tdppCount ?></strong></div>
    <div class="stat-item"><span>Admins</span><strong><?= $admCount ?></strong></div>
</div>

<script>
const facultyOpts = `
    <option value="">— Select Faculty (Lecturer only) —</option>
    <?php foreach ($faculties as $f): ?>
    <option value="<?= $f['faculty_id'] ?>">
        <?= htmlspecialchars($f['faculty_code']) ?> — <?= htmlspecialchars($f['faculty_name']) ?>
    </option>
    <?php endforeach; ?>
`;

const groupOpts = `
    <option value="">— Select Research Group —</option>
    <?php foreach ($researchGroups as $g): ?>
    <option value="<?= $g['group_id'] ?>"><?= htmlspecialchars($g['group_name']) ?></option>
    <?php endforeach; ?>
`;

const groupFacultyOpts = `
    <option value="">— University-wide —</option>
    <?php foreach ($faculties as $f): ?>
    <option value="<?= $f['faculty_id'] ?>"><?= htmlspecialchars($f['faculty_code']) ?></option>
    <?php endforeach; ?>
`;

function eyeBtn(inputId, iconId) {
    return `<button type="button"
        onclick="togglePwField('${inputId}','${iconId}')"
        style="position:absolute;right:0;top:0;height:100%;width:40px;background:none;
               border:none;cursor:pointer;color:#94a3b8;display:flex;align-items:center;
               justify-content:center;border-radius:0 8px 8px 0;transition:.15s"
        onmouseenter="this.style.color='#0B3C5D'"
        onmouseleave="this.style.color='#94a3b8'"
        title="Show/hide password">
        <i class="fas fa-eye" id="${iconId}" style="font-size:14px"></i>
    </button>`;
}

function pwField(id, iconId, name, placeholder, required = true) {
    const req = required ? 'required minlength="8"' : 'minlength="8"';
    return `<div style="position:relative">
        <input class="form-control" type="password" id="${id}" name="${name}"
               ${req} placeholder="${placeholder}" style="padding-right:42px">
        ${eyeBtn(id, iconId)}
    </div>`;
}

function togglePwField(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!inp || !icon) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
    inp.focus();
}

function openCreateUserModal() {
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">Create User Account</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form id="createUserForm" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input class="form-control" name="full_name" required placeholder="Dr. / Pn. / En. ...">
                </div>
                <div class="form-group">
                    <label class="form-label">Staff No</label>
                    <input class="form-control" name="staff_no" placeholder="UTH...">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input class="form-control" name="email" type="email" required placeholder="name@uthm.edu.my">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select class="form-control" name="role" required
                            onchange="toggleFacultyField(this.value)">
                        <option value="Lecturer">Lecturer</option>
                        <option value="Admin">Admin (TNCPI)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <select class="form-control" name="position">
                        <option>Lecturer</option>
                        <option>Senior Lecturer</option>
                        <option>Associate Professor</option>
                        <option>Professor</option>
                    </select>
                </div>
            </div>
            <div class="form-group" id="facultyField">
                <label class="form-label">Faculty</label>
                <select class="form-control" name="faculty_id">
                    ${facultyOpts}
                </select>
            </div>
            <div id="researchGroupFields">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Research Group Category</label>
                        <select class="form-control" name="research_group_category"
                                onchange="toggleGroupName(this.value)">
                            <option value="">— Select —</option>
                            <option value="FG">FG (Focus Group)</option>
                            <option value="External">External</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status Researcher</label>
                        <select class="form-control" name="status_researcher">
                            <option value="">— Select —</option>
                            <option>Principal Researcher</option>
                            <option>Head of the Group</option>
                            <option>Others</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="groupNameDropdownWrap" style="display:none">
                    <label class="form-label">Research Group Name</label>
                    <select class="form-control" name="research_group_id" id="groupNameDropdown">
                        ${groupOpts}
                    </select>
                </div>
                <div class="form-group" id="groupNameTextWrap" style="display:none">
                    <label class="form-label">Research Group Name</label>
                    <input class="form-control" name="research_centre_other" id="groupNameText"
                           placeholder="Type the group / centre name">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password *</label>
                ${pwField('cPw','cPwIcon','password','Minimum 8 characters')}
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password *</label>
                ${pwField('cPwConf','cPwConfIcon','confirm_password','Re-enter password')}
                <div id="cPwErr" style="font-size:12px;color:#dc2626;margin-top:4px;display:none">
                    <i class="fas fa-exclamation-circle"></i> Passwords do not match.
                </div>
            </div>
        </form>
        <button class="btn btn-teal btn-full" style="margin-top:1rem"
                onclick="submitCreate()">
            <i class="fas fa-user-plus"></i> Create Account
        </button>`);
}

function toggleFacultyField(role) {
    const f  = document.getElementById('facultyField');
    const rg = document.getElementById('researchGroupFields');
    const isLec = role !== 'Admin';
    if (f)  f.style.display  = isLec ? 'block' : 'none';
    if (rg) rg.style.display = isLec ? 'block' : 'none';
}

function toggleGroupName(cat) {
    const ddWrap = document.getElementById('groupNameDropdownWrap');
    const txWrap = document.getElementById('groupNameTextWrap');
    const ddSel  = document.getElementById('groupNameDropdown');
    const txInp  = document.getElementById('groupNameText');
    if (!ddWrap || !txWrap) return;
    if (cat === 'FG') {
        ddWrap.style.display = 'block'; txWrap.style.display = 'none';
        if (txInp) txInp.value = '';
    } else if (cat === 'External' || cat === 'Others') {
        ddWrap.style.display = 'none'; txWrap.style.display = 'block';
        if (ddSel) ddSel.value = '';
    } else {
        ddWrap.style.display = 'none'; txWrap.style.display = 'none';
        if (ddSel) ddSel.value = '';
        if (txInp) txInp.value = '';
    }
}

function submitCreate() {
    const form = document.getElementById('createUserForm');
    const pw   = document.getElementById('cPw');
    const conf = document.getElementById('cPwConf');
    const err  = document.getElementById('cPwErr');
    err.style.display = 'none';
    pw.style.borderColor = conf.style.borderColor = '';
    if (!form.checkValidity()) { form.reportValidity(); return; }
    if (pw.value !== conf.value) {
        err.style.display = 'block';
        pw.style.borderColor = conf.style.borderColor = '#dc2626';
        conf.focus(); return;
    }
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    fetch('/arams/api/add_lecturer.php', { method:'POST', body: new FormData(form) })
        .then(r => r.json())
        .then(res => {
            if (res.success) { showToast(res.message,'success'); closeModal(); setTimeout(()=>location.reload(),1200); }
            else showToast(res.message,'error');
        })
        .catch(() => showToast('Network error.','error'))
        .finally(() => { btn.disabled=false; btn.innerHTML='<i class="fas fa-user-plus"></i> Create Account'; });
}

function openEditUserModal(userId, name, email, isActive) {
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">Edit User</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form id="editUserForm" method="POST">
            <input type="hidden" name="user_id" value="${userId}">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input class="form-control" name="full_name" value="${escapeHtml(name)}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input class="form-control" value="${escapeHtml(email)}"
                       readonly style="background:var(--grey);cursor:not-allowed">
            </div>
            <div class="form-group">
                <label class="form-label">New Password
                    <span style="font-size:11px;color:var(--muted);font-weight:400">
                        — leave blank to keep current</span>
                </label>
                ${pwField('ePw','ePwIcon','new_password','Leave blank to keep current', false)}
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                ${pwField('ePwConf','ePwConfIcon','confirm_password','Re-enter new password', false)}
                <div id="ePwErr" style="font-size:12px;color:#dc2626;margin-top:4px;display:none">
                    <i class="fas fa-exclamation-circle"></i> Passwords do not match.
                </div>
            </div>
        </form>
        <button class="btn btn-primary btn-full" style="margin-top:1rem"
                onclick="submitEdit()">
            <i class="fas fa-save"></i> Save Changes
        </button>`);
}

function submitEdit() {
    const form = document.getElementById('editUserForm');
    const pw   = document.getElementById('ePw');
    const conf = document.getElementById('ePwConf');
    const err  = document.getElementById('ePwErr');
    err.style.display = 'none';
    pw.style.borderColor = conf.style.borderColor = '';
    if (!form.checkValidity()) { form.reportValidity(); return; }
    if (pw.value !== '' && pw.value !== conf.value) {
        err.style.display = 'block';
        pw.style.borderColor = conf.style.borderColor = '#dc2626';
        conf.focus(); return;
    }
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    fetch('/arams/api/update_user.php', { method:'POST', body: new FormData(form) })
        .then(r => r.json())
        .then(res => {
            if (res.success) { showToast(res.message,'success'); closeModal(); setTimeout(()=>location.reload(),1200); }
            else showToast(res.message,'error');
        })
        .catch(() => showToast('Network error.','error'))
        .finally(() => { btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save Changes'; });
}

function toggleUserStatus(userId, newStatus) {
    confirmDialog({
        title: (newStatus ? 'Activate' : 'Deactivate') + ' this user?',
        message: newStatus ? 'The user will regain access to the system.' : 'The user will lose access until reactivated.',
        confirmText: newStatus ? 'Activate' : 'Deactivate',
        danger: !newStatus,
        onConfirm: function(){
            fetch('/arams/api/toggle_user.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({user_id: userId, is_active: newStatus})
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) { showToast('User updated.','success'); setTimeout(()=>location.reload(),1000); }
                else showToast(res.message,'error');
            });
        }
    });
}

// ── Manage Research Groups ───────────────────────────────────
function openManageGroupsModal() {
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">Manage Research Groups</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form id="addGroupForm" onsubmit="return false" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem">
            <div class="form-group" style="flex:2;min-width:180px;margin:0">
                <label class="form-label">Group Name *</label>
                <input class="form-control" name="group_name" placeholder="e.g. Data Analytics, Sciences and Modeling (DASM)">
            </div>
            <div class="form-group" style="flex:1;min-width:90px;margin:0">
                <label class="form-label">Code</label>
                <input class="form-control" name="group_code" placeholder="DASM">
            </div>
            <div class="form-group" style="flex:1;min-width:110px;margin:0">
                <label class="form-label">Faculty</label>
                <select class="form-control" name="faculty_id">${groupFacultyOpts}</select>
            </div>
            <button class="btn btn-teal" onclick="submitAddGroup()" style="height:38px">
                <i class="fas fa-plus"></i> Add
            </button>
        </form>
        <div class="table-wrap" style="max-height:320px;overflow:auto">
            <table class="arams-table">
                <thead><tr><th>Group</th><th>Faculty</th><th>Status</th><th>Action</th></tr></thead>
                <tbody id="groupListBody">
                    <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:1rem">Loading…</td></tr>
                </tbody>
            </table>
        </div>`);
    loadGroupsList();
}

let _groupsCache = [];
function loadGroupsList() {
    fetch('/arams/api/manage_groups.php?action=list')
        .then(r => r.json())
        .then(res => {
            const body = document.getElementById('groupListBody');
            if (!body) return;
            _groupsCache = (res.success && res.data.groups) ? res.data.groups : [];
            if (!_groupsCache.length) {
                body.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:1rem">No groups yet.</td></tr>';
                return;
            }
            body.innerHTML = _groupsCache.map(g => {
                const fac = g.faculty_code ? `<span class="badge badge-grey">${escapeHtml(g.faculty_code)}</span>` : '<span style="color:var(--muted);font-size:12px">University-wide</span>';
                const status = g.is_active==1 ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-red">Inactive</span>';
                const toggleBtn = g.is_active==1
                    ? `<button class="btn btn-danger btn-sm" onclick="toggleGroup(${g.group_id},0)">Deactivate</button>`
                    : `<button class="btn btn-success btn-sm" onclick="toggleGroup(${g.group_id},1)">Activate</button>`;
                return `<tr>
                    <td style="font-size:13px">${escapeHtml(g.group_name)}${g.group_code?` <span style="color:var(--muted);font-size:11px">(${escapeHtml(g.group_code)})</span>`:''}</td>
                    <td>${fac}</td>
                    <td>${status}</td>
                    <td><div style="display:flex;gap:5px">
                        <button class="btn btn-outline btn-sm" onclick="openEditGroup(${g.group_id})">Edit</button>
                        ${toggleBtn}
                    </div></td>
                </tr>`;
            }).join('');
        });
}

function submitAddGroup() {
    const form = document.getElementById('addGroupForm');
    const fd = new FormData(form);
    fd.append('action','add');
    if (!fd.get('group_name').trim()) { showToast('Group name is required.','error'); return; }
    fetch('/arams/api/manage_groups.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            showToast(res.message, res.success?'success':'error');
            if (res.success) { form.reset(); loadGroupsList(); }
        });
}

function openEditGroup(id) {
    const g = _groupsCache.find(x => x.group_id == id);
    if (!g) return;
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">Edit Research Group</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form id="editGroupForm" onsubmit="return false">
            <input type="hidden" name="group_id" value="${g.group_id}">
            <div class="form-group">
                <label class="form-label">Group Name *</label>
                <input class="form-control" name="group_name" value="${escapeHtml(g.group_name)}">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Code</label>
                    <input class="form-control" name="group_code" value="${escapeHtml(g.group_code||'')}">
                </div>
                <div class="form-group">
                    <label class="form-label">Faculty</label>
                    <select class="form-control" name="faculty_id" id="editGroupFac">${groupFacultyOpts}</select>
                </div>
            </div>
        </form>
        <div style="display:flex;gap:8px;margin-top:1rem">
            <button class="btn btn-outline btn-full" onclick="openManageGroupsModal()">Back</button>
            <button class="btn btn-primary btn-full" onclick="submitEditGroup()"><i class="fas fa-save"></i> Save</button>
        </div>`);
    const sel = document.getElementById('editGroupFac');
    if (sel) sel.value = g.faculty_id || '';
}

function submitEditGroup() {
    const form = document.getElementById('editGroupForm');
    const fd = new FormData(form);
    fd.append('action','edit');
    if (!fd.get('group_name').trim()) { showToast('Group name is required.','error'); return; }
    fetch('/arams/api/manage_groups.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            showToast(res.message, res.success?'success':'error');
            if (res.success) openManageGroupsModal();
        });
}

function toggleGroup(id, active) {
    const fd = new FormData();
    fd.append('action','toggle');
    fd.append('group_id', id);
    fd.append('is_active', active);
    fetch('/arams/api/manage_groups.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => { showToast(res.message, res.success?'success':'error'); if (res.success) loadGroupsList(); });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>