<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

$bills = $pdo->prepare(
    "SELECT b.*, u.name AS tname, u.teacher_type, u.teacher_mode, s.subject_name, s.subject_code
     FROM bills b
     JOIN users u ON u.id=b.teacher_id
     LEFT JOIN subjects s ON s.id=u.subject_id
     WHERE b.status='pending' AND u.department_id=?
     ORDER BY b.submitted_at ASC"
); $bills->execute([$deptId]); $bills = $bills->fetchAll();

renderHead('Pending Requests');
?>
<div class="app-layout">
<?php renderSidebar('requests','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Pending Requests'); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header">
        <h1>Pending Requests</h1>
        <p><?= count($bills) ?> bill<?= count($bills)!=1?'s':'' ?> awaiting your review</p>
    </div>

    <div class="card">
        <?php if($bills): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Teacher</th><th>Type</th><th>Subject</th><th>Month</th><th>Theory Hrs</th><th>Prac. Hrs</th><th>Amount</th><th>Submitted</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach($bills as $b): ?>
                <tr>
                    <td>
                        <div class="fw-500"><?= e($b['tname']) ?></div>
                        <div><?= teacherTypeBadge($b['teacher_type']??'regular') ?></div>
                    </td>
                    <td><?= modeBadge($b['teacher_mode']??'theory') ?></td>
                    <td class="text-sm">
                        <?= e($b['subject_name']??'—') ?>
                        <?php if($b['subject_code']): ?>
                        <br><span class="badge badge-expert" style="font-size:.68rem"><?= e($b['subject_code']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-500"><?= e($b['month_year']) ?></td>
                    <td><?= number_format($b['total_theory_hrs'],1) ?> hrs</td>
                    <td><?= number_format($b['total_practical_hrs'],1) ?> hrs</td>
                    <td class="fw-600"><?= formatINR($b['total_amount']) ?></td>
                    <td class="text-sm text-muted"><?= fmtDate($b['submitted_at'],'d M Y') ?></td>
                    <td><a href="request-detail.php?id=<?= $b['id'] ?>" class="btn btn-primary btn-sm">Review →</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><div class="icon">🎉</div><h3>No pending requests</h3><p>All bills have been reviewed.</p></div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>
