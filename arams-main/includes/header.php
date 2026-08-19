<?php
// ============================================================
//  ARAMS — Shared Page Header
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user        = currentUser();
$unreadCount = getUnreadNotifCount($user['user_id']);
$isAdmin     = ($user['role'] === 'Admin');
$isTDPP      = ($user['role'] === 'TDPP');

// ── Resolve display name (Admin/TDPP have no tbl_lecturer row) ──
$displayName = $user['name'] ?? '';
if ($isTDPP) {
    $tdppRow = (getDB())->prepare("SELECT full_name FROM tbl_tdpp WHERE user_id=?");
    $tdppRow->execute([$user['user_id']]);
    $tdppName = $tdppRow->fetchColumn();
    if ($tdppName) $displayName = $tdppName;
}
if (empty(trim($displayName))) $displayName = $user['email'];

$initials = strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', array_filter(explode(' ', trim($displayName))))));
$initials = substr($initials, 0, 2) ?: 'U';

// ── Load profile photo from DB (lecturers only) ───────────
$sidebarPhoto = '';
if (!$isAdmin && !$isTDPP) {
    $photoSt = (getDB())->prepare(
        "SELECT profile_photo FROM tbl_lecturer WHERE user_id = ?"
    );
    $photoSt->execute([$user['user_id']]);
    $photoRow  = $photoSt->fetch();
    $photoFile = $photoRow['profile_photo'] ?? '';
    if ($photoFile) {
        $photoPath = __DIR__ . '/../assets/images/profiles/' . $photoFile;
        if (file_exists($photoPath)) {
            $sidebarPhoto = '/arams/assets/images/profiles/' . htmlspecialchars($photoFile);
            $_SESSION['profile_photo'] = $photoFile;
        }
    }
}

$lecNav = [
    ['id' => 'dashboard', 'label' => 'Dashboard',          'icon' => 'tachometer-alt', 'url' => '/arams/pages/lecturer/dashboard.php'],
    ['id' => 'profile',   'label' => 'My Profile',          'icon' => 'user-circle',    'url' => '/arams/pages/lecturer/profile.php'],
    ['id' => 'research',  'label' => 'Research Management', 'icon' => 'flask',          'url' => '/arams/pages/lecturer/research.php'],
    ['id' => 'tasks',     'label' => 'My KPI Tasks',        'icon' => 'list-check',     'url' => '/arams/pages/lecturer/tasks.php'],
    ['id' => 'timeline',  'label' => 'Timeline',            'icon' => 'history',        'url' => '/arams/pages/lecturer/timeline.php'],
    ['id' => 'analytics', 'label' => 'Analytics',           'icon' => 'chart-line',     'url' => '/arams/pages/lecturer/analytics.php'],
];
$admNav = [
    ['id' => 'dashboard',  'label' => 'Dashboard',       'icon' => 'tachometer-alt', 'url' => '/arams/pages/admin/dashboard.php'],
    ['id' => 'lecturers',  'label' => 'All Lecturers',   'icon' => 'users',          'url' => '/arams/pages/admin/lecturers.php'],
    ['id' => 'researchgroups', 'label' => 'Research Groups', 'icon' => 'sitemap',    'url' => '/arams/pages/admin/research_groups.php'],
  
    ['id' => 'analytics',  'label' => 'Analytics',       'icon' => 'chart-line',     'url' => '/arams/pages/admin/analytics.php'],
    ['id' => 'reports',    'label' => 'Reports',         'icon' => 'file-alt',       'url' => '/arams/pages/admin/reports.php'],
    ['id' => 'users',      'label' => 'User Management', 'icon' => 'users-cog',      'url' => '/arams/pages/admin/users.php'],
     ['id' => 'audit',      'label' => 'Audit Log',       'icon' => 'shield-alt',     'url' => '/arams/pages/admin/audit_log.php'],
];
$tdppNav = [
    ['id' => 'dashboard',  'label' => 'Dashboard',       'icon' => 'tachometer-alt', 'url' => '/arams/pages/tdpp/dashboard.php'],
    ['id' => 'lecturers',  'label' => 'My Lecturers',    'icon' => 'users',          'url' => '/arams/pages/tdpp/lecturers.php'],
    ['id' => 'kpi',        'label' => 'KPI Tasks',       'icon' => 'list-check',     'url' => '/arams/pages/tdpp/kpi.php'],
    ['id' => 'validation', 'label' => 'Validation',      'icon' => 'check-circle',   'url' => '/arams/pages/tdpp/validation.php'],
    ['id' => 'analytics',  'label' => 'Analytics',       'icon' => 'chart-line',     'url' => '/arams/pages/tdpp/analytics.php'],
    ['id' => 'users',      'label' => 'Faculty Members', 'icon' => 'users',          'url' => '/arams/pages/tdpp/users.php'],
];

if ($isAdmin) {
    $navItems   = $admNav;
    $dashUrl    = '/arams/pages/admin/dashboard.php';
    $portalName = 'Admin Panel';
} elseif ($isTDPP) {
    $navItems   = $tdppNav;
    $dashUrl    = '/arams/pages/tdpp/dashboard.php';
    $portalName = 'TDPP Portal';
} else {
    $navItems   = $lecNav;
    $dashUrl    = '/arams/pages/lecturer/dashboard.php';
    $portalName = 'Lecturer Portal';
}

$profileUrl = '/arams/pages/lecturer/profile.php';
$logoPath   = __DIR__ . '/../assets/images/uthm_logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'ARAMS') ?> — UTHM ARAMS</title>
    <link rel="stylesheet" href="/arams/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body<?= !empty($bodyClass) ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?>>
<div class="app-shell">

<!-- ── SIDEBAR ───────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">

    <!-- ① LOGO — top, clickable to dashboard -->
    <a href="<?= $dashUrl ?>" style="text-decoration:none;color:inherit" title="Go to Dashboard">
        <div class="sidebar-logo" style="cursor:pointer">
            <div class="sidebar-logo-icon" style="background:transparent;padding:0;flex-shrink:0">
                <?php if (file_exists($logoPath)): ?>
                <img src="/arams/assets/images/uthm_logo.png"
                     alt="UTHM Logo"
                     style="width:38px;height:38px;object-fit:contain;border-radius:6px">
                <?php else: ?>
                <i class="fas fa-chart-bar"></i>
                <?php endif; ?>
            </div>
            <div class="sidebar-logo-text">
                <span class="sidebar-title">UTHM ARAMS</span>
                <span class="sidebar-subtitle"><?= $portalName ?></span>
            </div>
        </div>
    </a>

    <!-- ② USER PROFILE — directly below logo, same original style -->
    <div class="sidebar-user" style="border-top:none;border-bottom:1px solid rgba(255,255,255,.1)">
        <div class="sidebar-user-row">
            <?php if ($sidebarPhoto): ?>
            <img src="<?= $sidebarPhoto ?>"
                 alt="Profile photo"
                 style="width:46px;height:46px;border-radius:50%;
                        object-fit:cover;flex-shrink:0;
                        border:2px solid rgba(255,255,255,.3)">
            <?php else: ?>
            <div class="sidebar-avatar"><?= $initials ?></div>
            <?php endif; ?>
            <div class="sidebar-user-info">
                <p><?= htmlspecialchars($displayName) ?></p>
                <span><?= htmlspecialchars($user['email']) ?></span>
            </div>
        </div>
    </div>

    <!-- ③ NAV ITEMS — below profile, scrollable -->
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= $item['url'] ?>"
           class="nav-link <?= ($activePage ?? '') === $item['id'] ? 'active' : '' ?>">
            <i class="fas fa-<?= $item['icon'] ?> nav-icon"></i>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- ④ LOGOUT — very bottom, same original style -->
    <div class="sidebar-user" style="border-top:1px solid rgba(255,255,255,.1)">
        <a href="/arams/api/logout.php" class="sidebar-logout">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

</aside>

<!-- ── MAIN AREA ─────────────────────────────────────── -->
<div class="main-wrap">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h2 class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h2>
                <p class="topbar-sub">Welcome back, <?= htmlspecialchars(explode(' ', $displayName)[0]) ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <!-- Notifications -->
            <div class="notif-wrap" id="notifWrap">
                <button class="topbar-btn" onclick="toggleNotif()" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>Notifications</span>
                        <a href="/arams/api/mark_notif_read.php" class="notif-mark-all">Mark all read</a>
                    </div>
                    <div id="notif-list"><p class="notif-empty">Loading...</p></div>
                    <div class="notif-footer"><a href="#">View all</a></div>
                </div>
            </div>
            <!-- Topbar avatar -->
            <?php if ($sidebarPhoto): ?>
            <img src="<?= $sidebarPhoto ?>"
                 alt="Profile"
                 style="width:34px;height:34px;border-radius:50%;object-fit:cover;
                        border:2px solid var(--border);cursor:pointer"
                 onclick="window.location='<?= $profileUrl ?>'"
                 title="My Profile">
            <?php else: ?>
            <div class="topbar-avatar"
                 <?= (!$isAdmin && !$isTDPP) ? "onclick=\"window.location='{$profileUrl}'\" style=\"cursor:pointer\" title=\"My Profile\"" : '' ?>>
                <?= $initials ?>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Page Content -->
    <main class="page-content">