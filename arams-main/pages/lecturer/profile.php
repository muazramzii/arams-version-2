<?php
// ============================================================
//  ARAMS — Lecturer Profile (complete final version)
// ============================================================
$pageTitle  = 'My Profile';
$activePage = 'profile';
require_once __DIR__ . '/../../includes/header.php';

$db    = getDB();
$lecId = (int)$user['lecturer_id'];

// Always fetch fresh from DB using user_id in case session is stale
$lecRow = $db->prepare("SELECT lecturer_id FROM tbl_lecturer WHERE user_id = ?");
$lecRow->execute([$user['user_id']]);
$fresh = $lecRow->fetch();
if ($fresh) $lecId = (int)$fresh['lecturer_id'];

$st = $db->prepare(
    "SELECT l.*, f.faculty_name, f.faculty_code
     FROM tbl_lecturer l
     JOIN tbl_faculty f ON f.faculty_id = l.faculty_id
     WHERE l.lecturer_id = ?"
);
$st->execute([$lecId]);
$lecturer = $st->fetch();

// Photo
$photo    = $lecturer['profile_photo'] ?? '';
$photoUrl = ($photo && file_exists(__DIR__ . '/../../assets/images/profiles/' . $photo))
            ? '/arams/assets/images/profiles/' . htmlspecialchars($photo)
            : '';
$initials = strtoupper(substr($lecturer['full_name'] ?? 'XX', 0, 2));

$success = $_GET['saved']  ?? '';
$error   = $_GET['error']  ?? '';
?>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profile updated successfully.</div>
<?php endif; ?>
<?php if ($error === 'pwshort'): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Password must be at least 8 characters.</div>
<?php elseif ($error === 'pwmismatch'): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Passwords do not match. Please try again.</div>
<?php elseif ($error === 'filetype'): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid file type. Please upload JPG, PNG, GIF or WEBP only.</div>
<?php elseif ($error === 'filesize'): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> File too large. Maximum size is 2 MB.</div>
<?php endif; ?>

<div style="max-width:640px">
<div class="card">

    <!-- ── Profile Header ── -->
    <div style="display:flex;align-items:center;gap:1.25rem;
                margin-bottom:1.5rem;padding-bottom:1.25rem;
                border-bottom:1px solid var(--border)">

        <!-- Avatar with camera overlay -->
        <div style="position:relative;flex-shrink:0">
            <?php if ($photoUrl): ?>
            <img src="<?= $photoUrl ?>" id="avatarPreview"
                 style="width:80px;height:80px;border-radius:50%;
                        object-fit:cover;border:3px solid var(--teal)">
            <?php else: ?>
            <div id="avatarPreview"
                 style="width:80px;height:80px;border-radius:50%;
                        background:linear-gradient(135deg,var(--blue),var(--teal));
                        display:flex;align-items:center;justify-content:center;
                        font-size:26px;font-weight:700;color:#fff;
                        border:3px solid var(--teal)">
                <?= $initials ?>
            </div>
            <?php endif; ?>
            <label for="photoInput"
                   style="position:absolute;bottom:0;right:0;
                          width:26px;height:26px;border-radius:50%;
                          background:var(--teal);color:#fff;
                          display:flex;align-items:center;justify-content:center;
                          cursor:pointer;border:2px solid #fff;font-size:11px"
                   title="Change photo">
                <i class="fas fa-camera"></i>
            </label>
        </div>

        <div>
            <h2 style="font-size:20px;margin:0 0 3px"><?= htmlspecialchars($lecturer['full_name']) ?></h2>
            <p style="font-size:13px;color:var(--muted);margin:0 0 6px">
                <?= htmlspecialchars($lecturer['position'] ?? 'Lecturer') ?> —
                <?= htmlspecialchars($lecturer['faculty_name']) ?>
            </p>
            <span class="badge badge-green">Active</span>
            <?php if ($lecturer['grade']): ?>
            <span class="badge badge-grey" style="margin-left:5px"><?= htmlspecialchars($lecturer['grade']) ?></span>
            <?php endif; ?>
            <div style="font-size:11px;color:var(--muted);margin-top:6px">
                <i class="fas fa-camera" style="margin-right:4px"></i>
                Click the camera icon to change your photo
            </div>
        </div>
    </div>

    <!-- ── Profile Form ── -->
    <form method="POST" action="/arams/api/update_profile.php"
          enctype="multipart/form-data">

        <!-- Hidden file input for photo -->
        <input type="file" id="photoInput" name="profile_photo"
               accept="image/jpeg,image/png,image/gif,image/webp"
               style="display:none" onchange="previewPhoto(this)">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Staff Number</label>
                <input class="form-control"
                       value="<?= htmlspecialchars($lecturer['staff_no']) ?>"
                       readonly style="background:var(--grey);cursor:not-allowed">
                <div class="form-hint">Cannot be changed</div>
            </div>
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input class="form-control" name="full_name"
                       value="<?= htmlspecialchars($lecturer['full_name']) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input class="form-control" value="<?= htmlspecialchars($user['email']) ?>"
                       readonly style="background:var(--grey);cursor:not-allowed">
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input class="form-control" name="phone"
                       value="<?= htmlspecialchars($lecturer['phone'] ?? '') ?>"
                       placeholder="07-4533XXX">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Department</label>
            <input class="form-control" name="department"
                   value="<?= htmlspecialchars($lecturer['department'] ?? '') ?>"
                   placeholder="e.g. Jabatan Kejuruteraan Perisian">
        </div>

        <div class="form-group">
            <label class="form-label">Field of Specialisation</label>
            <input class="form-control" name="specialisation"
                   value="<?= htmlspecialchars($lecturer['specialisation'] ?? '') ?>"
                   placeholder="e.g. Machine Learning, Information Systems">
        </div>

        <div class="form-group">
            <label class="form-label">Research Centre / Focus Group</label>
            <input class="form-control" name="research_centre"
                   value="<?= htmlspecialchars($lecturer['research_centre'] ?? '') ?>"
                   placeholder="e.g. Applied Information System (AiS)">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Research Group Category</label>
                <select class="form-control" name="research_group_category">
                    <option value="">— Select —</option>
                    <?php foreach(['FG','CoR','CoI','CoE','External'] as $cat): ?>
                    <option value="<?= $cat ?>"
                        <?= ($lecturer['research_group_category'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= $cat ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Status Researcher</label>
                <select class="form-control" name="status_researcher">
                    <option value="">— Select —</option>
                    <?php foreach(['Principal Researcher','Head of Group','Others'] as $st): ?>
                    <option value="<?= $st ?>"
                        <?= ($lecturer['status_researcher'] ?? '') === $st ? 'selected' : '' ?>>
                        <?= $st ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Managerial Position</label>
            <div style="display:flex;gap:1.5rem;margin-top:4px">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                    <input type="radio" name="managerial_position" value="1"
                           <?= ($lecturer['managerial_position'] ?? 0) ? 'checked' : '' ?>>
                    Yes — I hold a managerial position (Ketua Pusat / Penyelidik Utama)
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                    <input type="radio" name="managerial_position" value="0"
                           <?= !($lecturer['managerial_position'] ?? 0) ? 'checked' : '' ?>>
                    No
                </label>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:1.25rem 0">
        <h4 style="font-size:14px;margin-bottom:1rem;color:var(--muted)">Research Profile IDs</h4>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Scopus Author ID</label>
                <input class="form-control" name="scopus_id"
                       value="<?= htmlspecialchars($lecturer['scopus_id'] ?? '') ?>"
                       placeholder="e.g. 57188671418">
                <div class="form-hint">
                    <a href="https://www.scopus.com/search/form.uri#author"
                       target="_blank" style="color:var(--teal)">Find on Scopus ↗</a>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">ORCID ID</label>
                <input class="form-control" name="orcid_id"
                       value="<?= htmlspecialchars($lecturer['orcid_id'] ?? '') ?>"
                       placeholder="0000-0002-XXXX-XXXX">
                <div class="form-hint">
                    <a href="https://orcid.org" target="_blank"
                       style="color:var(--teal)">Get ORCID ↗</a>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">ResearcherID / Publons</label>
                <input class="form-control" name="researcher_id"
                       value="<?= htmlspecialchars($lecturer['researcher_id'] ?? '') ?>"
                       placeholder="e.g. AAL-7526-2021">
            </div>
            <div class="form-group">
                <label class="form-label">Lens ID</label>
                <input class="form-control" name="lens_id"
                       value="<?= htmlspecialchars($lecturer['lens_id'] ?? '') ?>"
                       placeholder="e.g. 462115374">
                <div class="form-hint">
                    <a href="https://www.lens.org" target="_blank"
                       style="color:var(--teal)">Find on Lens.org ↗</a>
                </div>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:1.25rem 0">
        <h4 style="font-size:14px;margin-bottom:1rem;color:var(--muted)">Change Password</h4>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">New Password</label>
                <div style="position:relative">
                    <input class="form-control" type="password" id="profilePw1"
                           name="new_password" minlength="8" autocomplete="new-password"
                           placeholder="Leave blank to keep current"
                           style="padding-right:42px">
                    <button type="button"
                            onclick="toggleProfilePw('profilePw1','profilePwIcon1')"
                            style="position:absolute;right:0;top:0;height:100%;width:40px;
                                   background:none;border:none;cursor:pointer;color:#94a3b8;
                                   display:flex;align-items:center;justify-content:center;
                                   border-radius:0 8px 8px 0;transition:.15s"
                            onmouseenter="this.style.color='#0B3C5D'"
                            onmouseleave="this.style.color='#94a3b8'"
                            title="Show/hide password">
                        <i class="fas fa-eye" id="profilePwIcon1" style="font-size:14px"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div style="position:relative">
                    <input class="form-control" type="password" id="profilePw2"
                           name="confirm_password" minlength="8" autocomplete="new-password"
                           placeholder="Re-enter new password"
                           style="padding-right:42px">
                    <button type="button"
                            onclick="toggleProfilePw('profilePw2','profilePwIcon2')"
                            style="position:absolute;right:0;top:0;height:100%;width:40px;
                                   background:none;border:none;cursor:pointer;color:#94a3b8;
                                   display:flex;align-items:center;justify-content:center;
                                   border-radius:0 8px 8px 0;transition:.15s"
                            onmouseenter="this.style.color='#0B3C5D'"
                            onmouseleave="this.style.color='#94a3b8'"
                            title="Show/hide password">
                        <i class="fas fa-eye" id="profilePwIcon2" style="font-size:14px"></i>
                    </button>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-teal">
            <i class="fas fa-save"></i> Save Profile Changes
        </button>
    </form>
</div>
</div>

<script>
function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('avatarPreview');
        if (preview.tagName === 'IMG') {
            preview.src = e.target.result;
        } else {
            const img = document.createElement('img');
            img.id    = 'avatarPreview';
            img.src   = e.target.result;
            img.style.cssText = 'width:80px;height:80px;border-radius:50%;' +
                                'object-fit:cover;border:3px solid var(--teal)';
            preview.parentNode.replaceChild(img, preview);
        }
    };
    reader.readAsDataURL(input.files[0]);
}

function toggleProfilePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
    input.focus();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>