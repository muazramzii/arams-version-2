/* ============================================================
   ARAMS — Main JavaScript
   Universiti Tun Hussein Onn Malaysia (UTHM)
   ============================================================ */

'use strict';

// ── SIDEBAR TOGGLE ───────────────────────────────────────────
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth <= 768) {
        sb.classList.toggle('mobile-open');
    } else {
        sb.classList.toggle('collapsed');
        localStorage.setItem('sidebar-collapsed', sb.classList.contains('collapsed'));
    }
}

// Restore sidebar state on load — default is EXPANDED
(function () {
    const sb = document.getElementById('sidebar');
    if (sb && window.innerWidth > 768) {
        // Only collapse if user explicitly collapsed it
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            sb.classList.add('collapsed');
        } else {
            // Default: always expanded, remove collapsed class
            sb.classList.remove('collapsed');
        }
    }
})();

// Close sidebar on outside click (mobile)
document.addEventListener('click', function (e) {
    const sb = document.getElementById('sidebar');
    if (sb && window.innerWidth <= 768 && sb.classList.contains('mobile-open')) {
        if (!sb.contains(e.target) && !e.target.closest('.sidebar-toggle')) {
            sb.classList.remove('mobile-open');
        }
    }
});

// ── TOAST ────────────────────────────────────────────────────
function showToast(message, type = 'default', duration = 3200) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'toast show' + (type !== 'default' ? ' ' + type : '');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => { toast.classList.remove('show'); }, duration);
}

// ── MODAL ────────────────────────────────────────────────────
function openModal(html) {
    const overlay = document.getElementById('modalOverlay');
    const box     = document.getElementById('modalBox');
    if (!overlay || !box) return;
    box.innerHTML = html;
    overlay.classList.add('open');
}

function closeModal() {
    const overlay = document.getElementById('modalOverlay');
    if (overlay) overlay.classList.remove('open');
}

// ── CONFIRM DIALOG (replaces the native browser confirm) ─────
let _confirmCb = null;
function confirmDialog(opts) {
    opts = opts || {};
    const title   = opts.title       || 'Please confirm';
    const message = opts.message     || 'Are you sure?';
    const okText  = opts.confirmText || 'Confirm';
    const noText  = opts.cancelText  || 'Cancel';
    const danger  = !!opts.danger;
    _confirmCb = (typeof opts.onConfirm === 'function') ? opts.onConfirm : null;
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">${title}</h3>
        </div>
        <p style="font-size:14px;line-height:1.55;margin:0 0 1.25rem;color:#334155">${message}</p>
        <div style="display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-outline" onclick="closeModal()">${noText}</button>
            <button class="btn ${danger ? 'btn-danger' : 'btn-teal'}" onclick="_runConfirm()">${okText}</button>
        </div>`);
}
function _runConfirm() {
    const cb = _confirmCb;
    _confirmCb = null;
    closeModal();
    if (cb) cb();
}

// Overlay click does NOT close the modal (prevents accidental data loss).
// Modal closes only via the × button, a Cancel button, or the Esc key.
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const overlay = document.getElementById('modalOverlay');
        if (overlay && overlay.classList.contains('open')) closeModal();
    }
});

// ── NOTIFICATIONS ────────────────────────────────────────────
function toggleNotif() {
    const dd = document.getElementById('notifDropdown');
    if (!dd) return;
    dd.classList.toggle('open');
    if (dd.classList.contains('open')) loadNotifications();
}

function loadNotifications() {
    const list = document.getElementById('notif-list');
    if (!list) return;
    fetch('/arams/api/get_notifications.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.data.length) {
                list.innerHTML = '<p class="notif-empty">No notifications</p>';
                return;
            }
            list.innerHTML = data.data.slice(0, 5).map(n => `
                <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}">
                    <div>${escapeHtml(n.message)}</div>
                    <div class="notif-time">${n.created_at}</div>
                </div>`).join('');
        })
        .catch(() => { list.innerHTML = '<p class="notif-empty">Failed to load</p>'; });
}

// Close notif on outside click
document.addEventListener('click', function (e) {
    const wrap = document.getElementById('notifWrap');
    if (wrap && !wrap.contains(e.target)) {
        const dd = document.getElementById('notifDropdown');
        if (dd) dd.classList.remove('open');
    }
});

// ── TABLE FILTER ─────────────────────────────────────────────
function filterTable(inputEl, tableId) {
    const val  = inputEl.value.toLowerCase();
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}

function filterBySelect(selectEl, tableId, colIndex) {
    const val  = selectEl.value.toLowerCase();
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach(row => {
        const cell = row.cells[colIndex];
        if (!val || (cell && cell.textContent.toLowerCase().includes(val))) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// ── TABS ─────────────────────────────────────────────────────
function switchTab(tabId, btn, groupClass = '.tab-panel') {
    // Deactivate all tabs in the same group
    const parent = btn.closest('.tabs');
    if (parent) {
        parent.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
    }
    btn.classList.add('active');

    // Hide all panels, show target
    document.querySelectorAll(groupClass).forEach(p => p.style.display = 'none');
    const target = document.getElementById(tabId);
    if (target) target.style.display = 'block';
}

// ── AJAX FORM SUBMIT ─────────────────────────────────────────
function submitForm(formId, endpoint, onSuccess) {
    const form = document.getElementById(formId);
    if (!form) return;
    const data = new FormData(form);
    const btn  = form.querySelector('[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

    fetch(endpoint, { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message, 'success');
                closeModal();
                if (typeof onSuccess === 'function') onSuccess(res);
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(() => showToast('Network error. Please try again.', 'error'))
        .finally(() => {
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
        });
}

// ── APPROVE / REJECT ROW ─────────────────────────────────────
function approveRecord(dataId, endpoint, rowEl) {
    confirmDialog({
        title: 'Approve submission?',
        message: 'This record will be marked as validated.',
        confirmText: 'Approve',
        onConfirm: function(){
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ data_id: dataId, action: 'approve' })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Record approved successfully.', 'success');
                    if (rowEl) { rowEl.style.opacity = '.4'; setTimeout(() => rowEl.remove(), 400); }
                } else { showToast(res.message, 'error'); }
            });
        }
    });
}

function rejectRecord(dataId, endpoint, rowEl) {
    const reason = prompt('Reason for rejection (optional):');
    if (reason === null) return; // cancelled
    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ data_id: dataId, action: 'reject', remarks: reason })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast('Record rejected. Lecturer will be notified.', 'error');
            if (rowEl) { rowEl.style.opacity = '.4'; setTimeout(() => rowEl.remove(), 400); }
        } else { showToast(res.message, 'error'); }
    });
}

// ── CONFIRM DELETE ────────────────────────────────────────────
function confirmDelete(endpoint, dataId, rowEl, label = 'this record') {
    confirmDialog({
        title: 'Delete ' + label + '?',
        message: 'This action cannot be undone.',
        confirmText: 'Delete',
        danger: true,
        onConfirm: function(){
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ data_id: dataId })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Deleted successfully.', 'success');
                    if (rowEl) { rowEl.style.opacity='.4'; setTimeout(() => rowEl.remove(), 350); }
                } else { showToast(res.message, 'error'); }
            });
        }
    });
}

// ── BAR CHART RENDERER ────────────────────────────────────────
function renderBarChart(containerId, data, color1 = '#0B3C5D', color2 = '#1B998B') {
    const el = document.getElementById(containerId);
    if (!el) return;
    const max = Math.max(...data.map(d => d.value), 1);
    el.innerHTML = data.map((d, i) => {
        const pct = Math.round((d.value / max) * 100);
        const col = i >= Math.floor(data.length / 2) ? color2 : color1;
        return `<div class="bar-col">
            <div class="bar-val">${d.value}</div>
            <div class="bar" style="height:${pct}%;background:${col}"></div>
            <div class="bar-label">${escapeHtml(d.label)}</div>
        </div>`;
    }).join('');
}

// ── DONUT CHART (SVG) ─────────────────────────────────────────
function renderDonut(containerId, segments) {
    // segments: [{label, value, color}]
    const el = document.getElementById(containerId);
    if (!el) return;
    const total = segments.reduce((s, d) => s + d.value, 0) || 1;
    const r = 46, cx = 60, cy = 60, stroke = 18;
    const circ = 2 * Math.PI * r;

    let offset = circ * 0.25; // start at top
    const arcs = segments.map(seg => {
        const dash  = (seg.value / total) * circ;
        const gap   = circ - dash;
        const arc   = `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none"
            stroke="${seg.color}" stroke-width="${stroke}"
            stroke-dasharray="${dash.toFixed(1)} ${gap.toFixed(1)}"
            stroke-dashoffset="${offset.toFixed(1)}"
            transform="rotate(-90 ${cx} ${cy})"/>`;
        offset -= dash;
        return arc;
    }).join('');

    const legend = segments.map(seg => `
        <div class="legend-item">
            <div class="legend-dot" style="background:${seg.color}"></div>
            <span>${escapeHtml(seg.label)}</span>
            <span class="legend-val">${seg.value}</span>
        </div>`).join('');

    el.innerHTML = `
        <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
            <svg width="120" height="120" viewBox="0 0 120 120">
                <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="#e2e8f0" stroke-width="${stroke}"/>
                ${arcs}
                <text x="${cx}" y="${cy - 4}" text-anchor="middle" font-size="18" font-weight="700" fill="#1E293B">${total}</text>
                <text x="${cx}" y="${cy + 14}" text-anchor="middle" font-size="10" fill="#64748B">total</text>
            </svg>
            <div class="donut-legend">${legend}</div>
        </div>`;
}

// ── UTILITY ───────────────────────────────────────────────────
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatRM(n) {
    return 'RM ' + Number(n).toLocaleString('en-MY', { minimumFractionDigits: 2 });
}

// ── APPROVE / REJECT with in-system modal (overrides confirm/prompt versions) ──

// Generic confirm modal — reuses the existing .modal-overlay / openModal system.
// onConfirm runs when user clicks the confirm button.
function confirmModal(opts, onConfirm) {
    var title   = opts.title   || 'Confirm';
    var message = opts.message || 'Are you sure?';
    var confirmText = opts.confirmText || 'Confirm';
    var confirmClass = opts.confirmClass || 'btn-primary';
    var withReason = !!opts.withReason;       // show a textarea (for reject reason)
    var reasonLabel = opts.reasonLabel || 'Reason (optional):';

    var reasonHtml = withReason
        ? '<div style="margin-top:12px">'
        +   '<label style="font-size:13px;color:var(--muted);display:block;margin-bottom:4px">' + reasonLabel + '</label>'
        +   '<textarea id="cm-reason" rows="3" style="width:100%;padding:8px;border:1px solid var(--grey-mid);border-radius:6px;font-family:inherit;font-size:13px;resize:vertical" placeholder="Type a reason..."></textarea>'
        + '</div>'
        : '';

    var html =
        '<div style="padding:4px 2px">'
      +   '<h3 style="margin:0 0 8px;font-size:17px;display:flex;align-items:center;gap:8px">'
      +     '<i class="fas fa-circle-question" style="color:var(--blue)"></i> ' + title
      +   '</h3>'
      +   '<p style="margin:0;color:var(--muted);font-size:14px;line-height:1.5">' + message + '</p>'
      +   reasonHtml
      +   '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px">'
      +     '<button class="btn btn-outline btn-sm" id="cm-cancel">Cancel</button>'
      +     '<button class="btn ' + confirmClass + ' btn-sm" id="cm-confirm">' + confirmText + '</button>'
      +   '</div>'
      + '</div>';

    openModal(html);

    var cancelBtn  = document.getElementById('cm-cancel');
    var confirmBtn = document.getElementById('cm-confirm');
    if (cancelBtn)  cancelBtn.onclick  = function(){ closeModal(); };
    if (confirmBtn) confirmBtn.onclick = function(){
        var reason = withReason ? (document.getElementById('cm-reason').value || '') : null;
        closeModal();
        onConfirm(reason);
    };
}

function approveRecord(dataId, endpoint, rowEl) {
    confirmModal({
        title: 'Approve Submission',
        message: 'Approve this submission? Approving may auto-complete the lecturer\'s matching KPI tasks.',
        confirmText: 'Approve',
        confirmClass: 'btn-success'
    }, function() {
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ data_id: dataId, action: 'approve' })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('Record approved successfully.', 'success');
                if (rowEl) { rowEl.style.opacity = '.4'; setTimeout(() => rowEl.remove(), 400); }
            } else { showToast(res.message, 'error'); }
        })
        .catch(function(){ showToast('Error approving record.', 'error'); });
    });
}

function rejectRecord(dataId, endpoint, rowEl) {
    confirmModal({
        title: 'Reject Submission',
        message: 'Reject this submission? The lecturer will be notified.',
        confirmText: 'Reject',
        confirmClass: 'btn-danger',
        withReason: true,
        reasonLabel: 'Reason for rejection (optional):'
    }, function(reason) {
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ data_id: dataId, action: 'reject', remarks: reason || '' })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('Record rejected. Lecturer will be notified.', 'error');
                if (rowEl) { rowEl.style.opacity = '.4'; setTimeout(() => rowEl.remove(), 400); }
            } else { showToast(res.message, 'error'); }
        })
        .catch(function(){ showToast('Error rejecting record.', 'error'); });
    });
}