<?php
// ============================================================
//  admin/dashboard.php — Super Admin Dashboard
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

// Stats
$totalDepts   = (int)$pdo->query("SELECT COUNT(*) FROM departments WHERE is_active=1")->fetchColumn();
$totalHODs    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='hod' AND is_active=1")->fetchColumn();
$totalTeachers= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND is_active=1")->fetchColumn();
$totalStudents= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn();
$pendingFunds = (int)$pdo->query("SELECT COUNT(*) FROM fund_requests WHERE status='pending'")->fetchColumn();
$approvedFunds= (int)$pdo->query("SELECT COUNT(*) FROM fund_requests WHERE status='approved'")->fetchColumn();
$totalDisbursed=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fund_requests WHERE status='approved'")->fetchColumn();
$totalBills   = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE status='approved'")->fetchColumn();

// Recent fund requests
$recentFunds = $pdo->query(
    "SELECT fr.*, u.name AS hod_name, d.name AS dept_name
     FROM fund_requests fr
     JOIN users u ON u.id=fr.hod_id
     JOIN departments d ON d.id=fr.department_id
     ORDER BY fr.requested_at DESC LIMIT 6"
)->fetchAll();

// Recent activity
$activity = $pdo->query(
    "SELECT a.*, u.name FROM activity_log a
     LEFT JOIN users u ON u.id=a.user_id
     ORDER BY a.created_at DESC LIMIT 8"
)->fetchAll();

renderHead('Admin Dashboard');
?>
<div class="app-layout">
<?php renderSidebar('dashboard', 'admin', $user); ?>
<div class="main-content">
<?php renderTopbar('Admin Dashboard'); ?>
<div class="page-body">
    <?= getFlash() ?>

    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= e($user['name']) ?> — System overview for <?= date('F Y') ?></p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">🏛</div>
            <div><div class="stat-label">Departments</div><div class="stat-value"><?= $totalDepts ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">👥</div>
            <div><div class="stat-label">Active HODs</div><div class="stat-value"><?= $totalHODs ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">👨‍🏫</div>
            <div><div class="stat-label">Teachers</div><div class="stat-value"><?= $totalTeachers ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal">🎓</div>
            <div><div class="stat-label">E&L Students</div><div class="stat-value"><?= $totalStudents ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">⏳</div>
            <div><div class="stat-label">Pending Fund Req.</div><div class="stat-value"><?= $pendingFunds ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div><div class="stat-label">Approved Bills</div><div class="stat-value"><?= $totalBills ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">💰</div>
            <div><div class="stat-label">Total Disbursed</div><div class="stat-value sm"><?= formatINR($totalDisbursed) ?></div></div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="d-flex gap-10 flex-wrap mb-2">
        <a href="fund-requests.php"    class="btn btn-primary">
            💰 Fund Requests
            <?php if ($pendingFunds): ?>
            <span style="background:#EF4444;color:#fff;font-size:.68rem;font-weight:700;
                  padding:1px 6px;border-radius:20px"><?= $pendingFunds ?></span>
            <?php endif; ?>
        </a>
        <a href="departments.php"   class="btn btn-outline">🏛 Departments</a>
        <a href="classes.php"       class="btn btn-outline">📚 Classes</a>
        <a href="subjects.php"      class="btn btn-outline">📖 Subjects</a>
        <a href="manage-hods.php"   class="btn btn-outline">👥 Manage HODs</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

        <!-- Fund Requests -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Fund Requests</h3>
                <a href="fund-requests.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <?php if ($recentFunds): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>HOD</th><th>Department</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentFunds as $fr): ?>
                    <tr>
                        <td class="fw-500"><?= e($fr['hod_name']) ?></td>
                        <td><?= e($fr['dept_name']) ?></td>
                        <td class="fw-600"><?= formatINR($fr['amount']) ?></td>
                        <td><?= statusBadge($fr['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="icon">💰</div><h3>No fund requests yet</h3></div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header"><h3>Recent Activity</h3></div>
            <?php if ($activity): ?>
            <div>
                <?php
                $icons = ['login'=>'🔑','logout'=>'🚪','submit_bill'=>'📤',
                          'approve_bill'=>'✅','reject_bill'=>'❌','create_other_bill'=>'📄',
                          'add_lecture'=>'📅','add_teacher'=>'➕','approve_fund'=>'💰'];
                foreach ($activity as $a):
                    $icon = $icons[$a['action']] ?? '📋';
                ?>
                <div style="display:flex;gap:10px;align-items:flex-start;
                            padding:9px 1.3rem;border-bottom:1px solid var(--border)">
                    <div style="width:30px;height:30px;background:var(--bg);border-radius:7px;
                                display:flex;align-items:center;justify-content:center;
                                font-size:.88rem;flex-shrink:0"><?= $icon ?></div>
                    <div>
                        <div class="fw-500" style="font-size:.82rem"><?= e($a['name'] ?? 'System') ?></div>
                        <div class="text-muted text-xs"><?= e($a['description'] ?: $a['action']) ?></div>
                        <div class="text-xs" style="color:var(--light)"><?= fmtDate($a['created_at'],'d M, h:i A') ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="icon">📋</div><h3>No activity yet</h3></div>
            <?php endif; ?>
        </div>

    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>
