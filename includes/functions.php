<?php
// ============================================================
//  includes/functions.php — Shared Helper Functions
//  College Bill Generation System — GCEA
// ============================================================

// ── Output helpers ───────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function formatINR(float $amount): string {
    return '₹' . number_format($amount, 2);
}

function fmtDate(string $date, string $fmt = 'd M Y'): string {
    if (!$date || $date === '0000-00-00') return '—';
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '—';
}

// ── Flash messages ───────────────────────────────────────────
function setFlash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['flash'])) return '';
    ['type' => $type, 'msg' => $msg] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icons = ['success'=>'✅','error'=>'❌','warning'=>'⚠️','info'=>'ℹ️'];
    $cls   = match($type) {
        'success' => 'alert-success',
        'error'   => 'alert-error',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return '<div class="alert ' . $cls . ' auto-dismiss">' . ($icons[$type] ?? '') . ' ' . e($msg) . '</div>';
}

// ── Activity log ─────────────────────────────────────────────
function logActivity(PDO $pdo, int $userId, string $action, string $desc = ''): void {
    $pdo->prepare(
        "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?,?,?,?)"
    )->execute([$userId, $action, $desc, $_SERVER['REMOTE_ADDR'] ?? '']);
}

// ── Badges ───────────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'draft'    => ['badge-draft',    '● Draft'],
        'pending'  => ['badge-pending',  '● Pending'],
        'approved' => ['badge-approved', '● Approved'],
        'rejected' => ['badge-rejected', '● Rejected'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-pending', ucfirst($status)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

function teacherTypeBadge(string $type): string {
    $map = [
        'regular'          => ['badge-regular',   'Regular'],
        'expert'           => ['badge-expert',    'Expert'],
        'sectional_expert' => ['badge-sectional', 'Sectional Expert'],
        'adjunct'          => ['badge-adjunct',   'Adjunct'],
    ];
    [$cls, $label] = $map[$type] ?? ['badge-regular', ucfirst($type)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

function modeBadge(string $mode): string {
    $map = [
        'theory'    => ['badge-theory',    'Theory'],
        'practical' => ['badge-practical', 'Practical'],
        'both'      => ['badge-both',      'Theory & Practical'],
    ];
    [$cls, $label] = $map[$mode] ?? ['badge-theory', ucfirst($mode)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

// ── Base path helper (from any subfolder) ───────────────────
function base(string $file = ''): string {
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $root   = realpath(__DIR__ . '/..');
    return $root . '/' . ltrim($file, '/');
}

function assetUrl(string $file): string {
    // Works from admin/, hod/, teacher/, student/, pdf/ (one level deep)
    // and from root index.php
    $depth = substr_count(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/') - 1;
    $prefix = $depth <= 1 ? '' : str_repeat('../', $depth - 1);
    return $prefix . 'assets/' . ltrim($file, '/');
}

function rootPath(string $file = ''): string {
    $depth = substr_count(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/') - 1;
    $prefix = $depth <= 1 ? '' : str_repeat('../', $depth - 1);
    return $prefix . ltrim($file, '/');
}

// ── HTML head + open body + navbar ───────────────────────────
function renderHead(string $title, int $depth = 1): void {
    $asset = $depth === 0 ? 'assets/' : str_repeat('../', $depth) . 'assets/';
    $root  = $depth === 0 ? '' : str_repeat('../', $depth);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — BGS | GCEA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $asset ?>css/style.css">
</head>
<body>
<!-- TOP NAVBAR -->
<!-- Navbar -->
<nav class="navbar">
    <div class="navbar-brand">
        <img src="../assets/images/logo.png" alt="GCEA Logo" class="navbar-logo"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="navbar-logo-fallback" style="display:none">🎓</div>

        <div class="navbar-titles">
            <span class="navbar-college-en">Government College of Engineering Aurangabad, Chhatrapati Sambhajinagar</span>
            <span class="navbar-college-hi">शासकीय अभियांत्रिकी महाविद्यालय औरंगाबाद, छत्रपती संभाजीनगर</span>
        </div>
    </div>

    <div class="navbar-emblems">
        <img src="../assets/images/img2.gif"  alt="Maharashtra Skill Development Department" class="navbar-emblem">
        <img src="../assets/images/img3.png"    alt="Government of Maharashtra Seal" class="navbar-emblem navbar-emblem--crop">
        <img src="../assets/images/img4.jpg"  alt="Government of India Emblem" class="navbar-emblem">
    </div>
</nav>
    <?php
}

function renderFooter(int $depth = 1): void {
    $asset = $depth === 0 ? 'assets/' : str_repeat('../', $depth) . 'assets/';
    ?>
    <script src="<?= $asset ?>js/app.js"></script>
</body>
</html>
    <?php
}

// ── Sidebar renderer ─────────────────────────────────────────
function renderSidebar(string $active, string $role, array $user): void {
    $navs = [
        'admin' => [
            ['key'=>'dashboard',     'href'=>'dashboard.php',     'icon'=>'🏠', 'label'=>'Dashboard'],
            ['key'=>'departments',   'href'=>'departments.php',   'icon'=>'🏛', 'label'=>'Departments'],
            ['key'=>'classes',       'href'=>'classes.php',       'icon'=>'📚', 'label'=>'Classes'],
            ['key'=>'subjects',      'href'=>'subjects.php',      'icon'=>'📖', 'label'=>'Subjects'],
            ['key'=>'manage-hods',   'href'=>'manage-hods.php',   'icon'=>'👥', 'label'=>'Manage HODs'],
            ['key'=>'fund-requests', 'href'=>'fund-requests.php', 'icon'=>'💰', 'label'=>'Fund Requests'],
            ['key'=>'profile',       'href'=>'profile.php',       'icon'=>'👤', 'label'=>'Profile'],
        ],
        'hod' => [
            ['key'=>'dashboard',     'href'=>'dashboard.php',     'icon'=>'🏠', 'label'=>'Dashboard'],
            ['key'=>'requests',      'href'=>'requests.php',      'icon'=>'📥', 'label'=>'Pending Requests'],
            ['key'=>'all-bills',     'href'=>'all-bills.php',     'icon'=>'📋', 'label'=>'All Bills'],
            ['key'=>'manual-bill',   'href'=>'manual-bill.php',   'icon'=>'✏️', 'label'=>'Manual Bill'],
            ['key'=>'other-bills',   'href'=>'other-bills.php',   'icon'=>'📄', 'label'=>'Other Bills'],
            ['key'=>'timetable',     'href'=>'timetable.php',     'icon'=>'📅', 'label'=>'Time Table'],
            ['key'=>'classes',       'href'=>'classes.php',       'icon'=>'📚', 'label'=>'Classes'],
            ['key'=>'subjects',      'href'=>'subjects.php',      'icon'=>'📖', 'label'=>'Subjects'],
            ['key'=>'manage-users',  'href'=>'manage-users.php',  'icon'=>'👨‍🏫', 'label'=>'Manage Users'],
            ['key'=>'profile',       'href'=>'profile.php',       'icon'=>'👤', 'label'=>'Profile'],
        ],
        'teacher' => [
            ['key'=>'dashboard',     'href'=>'dashboard.php',     'icon'=>'🏠', 'label'=>'Dashboard'],
            ['key'=>'lectures',      'href'=>'lectures.php',      'icon'=>'📅', 'label'=>'My Lectures'],
            ['key'=>'generate-bill', 'href'=>'generate-bill.php', 'icon'=>'🧾', 'label'=>'Generate Bill'],
            ['key'=>'my-bills',      'href'=>'my-bills.php',      'icon'=>'📋', 'label'=>'My Bills'],
            ['key'=>'profile',       'href'=>'profile.php',       'icon'=>'👤', 'label'=>'Profile'],
        ],
        'student' => [
            ['key'=>'dashboard',     'href'=>'dashboard.php',     'icon'=>'🏠', 'label'=>'Dashboard'],
            ['key'=>'add-work',      'href'=>'add-work.php',      'icon'=>'➕', 'label'=>'Add Work'],
            ['key'=>'generate-bill', 'href'=>'generate-bill.php', 'icon'=>'🧾', 'label'=>'Generate Bill'],
            ['key'=>'my-bills',      'href'=>'my-bills.php',      'icon'=>'📋', 'label'=>'My Bills'],
            ['key'=>'profile',       'href'=>'profile.php',       'icon'=>'👤', 'label'=>'Profile'],
        ],
    ];

    $items    = $navs[$role] ?? [];
    $initials = getInitials($user['name']);
    $profilePhoto = $user['profile_photo'] ?? null;
    $roleLabel = match($role) {
        'admin'   => 'Super Admin',
        'hod'     => 'HOD',
        'teacher' => 'Teacher',
        'student' => 'Earn & Learn',
        default   => ucfirst($role),
    };
    ?>
    <aside class="sidebar">
        <div class="sidebar-user">
            <div class="user-avatar"><?php if($profilePhoto): ?><img src="../assets/uploads/profiles/<?= e($profilePhoto) ?>" alt="Photo" class="user-avatar-img"><?php else: ?><?= e($initials) ?><?php endif; ?></div>
            <div class="user-info">
                <div class="user-name"><?= e($user['name']) ?></div>
                <div class="user-role"><?= $roleLabel ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <?php foreach ($items as $item): ?>
            <a href="<?= e($item['href']) ?>"
               class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>">
                <span class="nav-icon"><?= $item['icon'] ?></span>
                <?= e($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="btn-logout"
               onclick="return confirmAction('Sign out of your account?')">
                🚪 Sign Out
            </a>
        </div>
    </aside>
    <?php
}

// ── Topbar inside dashboard pages ────────────────────────────
function renderTopbar(string $title): void {
    ?>
    <div class="topbar">
        <div class="topbar-title"><?= e($title) ?></div>
        <div class="topbar-right">
            <span class="topbar-date"><?= date('l, d F Y') ?></span>
        </div>
    </div>
    <?php
}

// ── Full app layout wrappers ──────────────────────────────────
function openLayout(string $active, string $role, array $user): void {
    echo '<div class="app-layout">';
    renderSidebar($active, $role, $user);
    echo '<div class="main-content">';
    renderTopbar(ucwords(str_replace('-', ' ', $active)));
    echo '<div class="page-body">';
}

function closeLayout(int $depth = 1): void {
    echo '</div></div></div>';
    renderFooter($depth);
}

// ── Department name helper ────────────────────────────────────
function deptName(PDO $pdo, int $id): string {
    static $cache = [];
    if (!$id) return '—';
    if (!isset($cache[$id])) {
        $s = $pdo->prepare("SELECT name FROM departments WHERE id=? LIMIT 1");
        $s->execute([$id]);
        $cache[$id] = $s->fetchColumn() ?: '—';
    }
    return $cache[$id];
}

function subjectLabel(PDO $pdo, int $id): string {
    static $cache = [];
    if (!$id) return '—';
    if (!isset($cache[$id])) {
        $s = $pdo->prepare("SELECT subject_name, subject_code FROM subjects WHERE id=? LIMIT 1");
        $s->execute([$id]);
        $row = $s->fetch();
        $cache[$id] = $row ? $row['subject_name'] . ' (' . $row['subject_code'] . ')' : '—';
    }
    return $cache[$id];
}
