<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

$fStatus  = $_GET['status']  ?? '';
$fTeacher = (int)($_GET['teacher'] ?? 0);
$fMonth   = (int)($_GET['month']   ?? 0);
$fYear    = (int)($_GET['year']    ?? 0);

$sql = "SELECT b.*, u.name AS tname, u.teacher_type
        FROM bills b JOIN users u ON u.id=b.teacher_id
        WHERE u.department_id=?";
$params = [$deptId];
if ($fStatus)  { $sql.=" AND b.status=?";                $params[]=$fStatus; }
if ($fTeacher) { $sql.=" AND b.teacher_id=?";            $params[]=$fTeacher; }
if ($fMonth)   { $sql.=" AND MONTH(b.period_from)=?";    $params[]=$fMonth; }
if ($fYear)    { $sql.=" AND YEAR(b.period_from)=?";     $params[]=$fYear; }
$sql .= " ORDER BY b.submitted_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$bills = $stmt->fetchAll();

$teachers = $pdo->prepare("SELECT id,name FROM users WHERE role='teacher' AND department_id=? AND is_active=1 ORDER BY name");
$teachers->execute([$deptId]); $teachers=$teachers->fetchAll();

$totalAmt = array_sum(array_column($bills,'total_amount'));

renderHead('All Bills');
?>
<div class="app-layout">
<?php renderSidebar('all-bills','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('All Bills'); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>All Bills</h1><p>Complete bill history for your department</p></div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:1.2rem">
        <div class="card-body" style="padding:.9rem">
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group" style="margin:0">
                    <label>Status</label>
                    <select name="status" class="form-control" style="width:180px">
                        <option value="">All</option>
                        <option value="draft"    <?= $fStatus==='draft'   ?'selected':'' ?>>Draft</option>
                        <option value="pending"  <?= $fStatus==='pending' ?'selected':'' ?>>Pending</option>
                        <option value="approved" <?= $fStatus==='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected" <?= $fStatus==='rejected'?'selected':'' ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Teacher</label>
                    <select name="teacher" class="form-control" style="width:200px">
                        <option value="">All Teachers</option>
                        <?php foreach($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $fTeacher==$t['id']?'selected':'' ?>><?= e($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Month</label>
                    <select name="month" class="form-control" style="width:180px">
                        <option value="">All Months</option>
                        <?php for($m=1;$m<=12;$m++): ?>
                        <option value="<?= $m ?>" <?= $fMonth==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Year</label>
                    <select name="year" class="form-control" style="width:180px">
                        <option value="">All</option>
                        <?php for($y=date('Y');$y>=date('Y')-4;$y--): ?>
                        <option value="<?= $y ?>" <?= $fYear==$y?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="padding: 10px 20px;">Filter</button>
                <a href="all-bills.php" class="btn btn-outline btn-sm" style="padding: 7px 15px;">Clear</a>
            </form>
        </div>
    </div>

    <?php if($bills): ?>
    <div class="d-flex gap-10 align-center mb-2 text-sm text-muted">
        <span>Showing <strong style="color:var(--text)"><?= count($bills) ?></strong> bill<?= count($bills)!=1?'s':'' ?></span>
        <span>·</span>
        <span>Total: <strong style="color:var(--text)"><?= formatINR($totalAmt) ?></strong></span>
    </div>
    <?php endif; ?>

    <div class="card">
        <?php if($bills): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Teacher</th><th>Month</th><th>Theory Hrs</th><th>Prac. Hrs</th><th>Amount</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($bills as $b): ?>
                <tr>
                    <td class="text-muted"><?= $b['id'] ?></td>
                    <td>
                        <div class="fw-500"><?= e($b['tname']) ?></div>
                        <div><?= teacherTypeBadge($b['teacher_type']??'regular') ?></div>
                    </td>
                    <td class="fw-500"><?= e($b['month_year']) ?></td>
                    <td><?= number_format($b['total_theory_hrs'],1) ?></td>
                    <td><?= number_format($b['total_practical_hrs'],1) ?></td>
                    <td class="fw-600"><?= formatINR($b['total_amount']) ?></td>
                    <td><?= statusBadge($b['status']) ?></td>
                    <td class="text-sm text-muted"><?= fmtDate($b['submitted_at'],'d M Y') ?></td>
                    <td>
                        <div class="d-flex gap-8">
                            <a href="request-detail.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">View</a>
                            <?php if($b['status']==='approved'): ?>
                            <a href="../pdf/generate.php?id=<?= $b['id'] ?>" class="btn btn-success btn-sm" target="_blank">PDF</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><div class="icon">📋</div><h3>No bills found</h3><p>Try adjusting your filters.</p></div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>
