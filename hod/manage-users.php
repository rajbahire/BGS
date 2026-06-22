<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_teacher') {
        $name   = trim($_POST['name']   ?? '');
        $email  = trim($_POST['email']  ?? '');
        $phone  = trim($_POST['phone']  ?? '');
        $type   = $_POST['teacher_type']  ?? 'regular';
        $mode   = $_POST['teacher_mode']  ?? 'theory';
        $subj   = (int)$_POST['subject_id'];
        $rateT  = (float)$_POST['rate_theory'];
        $rateP  = (float)$_POST['rate_practical'];
        $rateO  = (float)$_POST['rate_other'];
        $appNo  = trim($_POST['appointment_order_no'] ?? '');
        $pass   = $_POST['password'] ?? 'teacher@1234';

        if ($name && $email) {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
            if ($dup->fetch()) { setFlash('error','Email already exists.'); }
            else {
                $pdo->prepare("INSERT INTO users (name,email,password,role,department_id,teacher_type,teacher_mode,subject_id,rate_theory,rate_practical,rate_other,appointment_order_no,phone) VALUES (?,?,?,'teacher',?,?,?,?,?,?,?,?,?)")
                    ->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$deptId,$type,$mode,$subj?:null,$rateT,$rateP,$rateO,$appNo,$phone]);
                logActivity($pdo,$user['id'],'add_teacher',"Added teacher: $name");
                setFlash('success',"Teacher \"$name\" added. Password: $pass");
            }
        } else { setFlash('error','Name and email are required.'); }
    }

    if ($action === 'add_student') {
        $name    = trim($_POST['name']   ?? '');
        $email   = trim($_POST['email']  ?? '');
        $phone   = trim($_POST['phone']  ?? '');
        $classId = (int)$_POST['class_id'];
        $rate    = (float)$_POST['rate_per_hour'];
        $pass    = $_POST['password'] ?? 'student@1234';
        if ($name && $email) {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
            if ($dup->fetch()) { setFlash('error','Email already exists.'); }
            else {
                $pdo->prepare("INSERT INTO users (name,email,password,role,department_id,class_id,rate_per_hour,phone) VALUES (?,?,?,'student',?,?,?,?)")
                    ->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$deptId,$classId?:null,$rate,$phone]);
                logActivity($pdo,$user['id'],'add_student',"Added student: $name");
                setFlash('success',"Student \"$name\" added. Password: $pass");
            }
        } else { setFlash('error','Name and email required.'); }
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $name   = trim($_POST['name']  ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $active = (int)$_POST['is_active'];
        $type   = $_POST['teacher_type']  ?? null;
        $mode   = $_POST['teacher_mode']  ?? null;
        $subj   = (int)($_POST['subject_id'] ?? 0);
        $rateT  = (float)($_POST['rate_theory']     ?? 0);
        $rateP  = (float)($_POST['rate_practical']   ?? 0);
        $rateO  = (float)($_POST['rate_other']       ?? 0);
        $rateH  = (float)($_POST['rate_per_hour']    ?? 0);
        $appNo  = trim($_POST['appointment_order_no']?? '');
        $pdo->prepare("UPDATE users SET name=?,phone=?,is_active=?,teacher_type=?,teacher_mode=?,subject_id=?,rate_theory=?,rate_practical=?,rate_other=?,rate_per_hour=?,appointment_order_no=? WHERE id=? AND department_id=?")
            ->execute([$name,$phone,$active,$type?:null,$mode?:null,$subj?:null,$rateT,$rateP,$rateO,$rateH,$appNo,$id,$deptId]);
        setFlash('success','User updated.');
    }

    if ($action === 'reset_password') {
        $id   = (int)$_POST['id'];
        $role = $_POST['urole'] ?? 'teacher';
        $pass = $role === 'student' ? 'student@1234' : 'teacher@1234';
        $pdo->prepare("UPDATE users SET password=? WHERE id=? AND department_id=?")->execute([password_hash($pass,PASSWORD_DEFAULT),$id,$deptId]);
        setFlash('success',"Password reset to: $pass");
    }

    if ($action === 'deactivate') {
        $id     = (int)$_POST['id'];
        $urole  = $_POST['urole'] ?? 'teacher';
        if ($id) {
            $check = $pdo->prepare("SELECT id,name FROM users WHERE id=? AND department_id=?");
            $check->execute([$id, $deptId]);
            $row = $check->fetch();
            if ($row) {
                $pdo->prepare("UPDATE users SET is_active=0 WHERE id=? AND department_id=?")->execute([$id,$deptId]);
                logActivity($pdo,$user['id'],'deactivate_user',"HOD deactivated {$urole}: {$row['name']}");
                setFlash('warning',"User \"{$row['name']}\" has been deactivated.");
            } else {
                setFlash('error','User not found or access denied.');
            }
        }
    }

    if ($action === 'activate') {
        $id    = (int)$_POST['id'];
        $urole = $_POST['urole'] ?? 'teacher';
        if ($id) {
            $check = $pdo->prepare("SELECT id,name FROM users WHERE id=? AND department_id=?");
            $check->execute([$id, $deptId]);
            $row = $check->fetch();
            if ($row) {
                $pdo->prepare("UPDATE users SET is_active=1 WHERE id=? AND department_id=?")->execute([$id,$deptId]);
                logActivity($pdo,$user['id'],'activate_user',"HOD activated {$urole}: {$row['name']}");
                setFlash('success',"User \"{$row['name']}\" has been reactivated.");
            } else {
                setFlash('error','User not found or access denied.');
            }
        }
    }

    header('Location: manage-users.php?tab='.($_POST['tab']??'teachers')); exit;
}

$editId  = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) { $s=$pdo->prepare("SELECT * FROM users WHERE id=? AND department_id=?"); $s->execute([$editId,$deptId]); $editRow=$s->fetch(); }

$activeTab = $_GET['tab'] ?? 'teachers';

$teachers = $pdo->prepare(
    "SELECT u.*, s.subject_name, s.subject_code FROM users u
     LEFT JOIN subjects s ON s.id=u.subject_id
     WHERE u.role='teacher' AND u.department_id=? ORDER BY u.name"
); $teachers->execute([$deptId]); $teachers=$teachers->fetchAll();

$students = $pdo->prepare(
    "SELECT u.*, c.label AS class_label FROM users u
     LEFT JOIN classes c ON c.id=u.class_id
     WHERE u.role='student' AND u.department_id=? ORDER BY u.name"
); $students->execute([$deptId]); $students=$students->fetchAll();

$subjects = $pdo->prepare(
    "SELECT s.*, c.label AS class_label FROM subjects s
     JOIN classes c ON c.id=s.class_id
     WHERE c.department_id=? AND s.is_active=1 ORDER BY c.year,c.semester,s.subject_name"
); $subjects->execute([$deptId]); $subjects=$subjects->fetchAll();

$classes = $pdo->prepare("SELECT * FROM classes WHERE department_id=? AND is_active=1 ORDER BY year,semester");
$classes->execute([$deptId]); $classes=$classes->fetchAll();

renderHead('Manage Users');
?>
<div class="app-layout">
<?php renderSidebar('manage-users','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Manage Users'); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>Manage Users</h1><p>Add and manage teachers and Earn &amp; Learn students</p></div>

    <!-- Tabs -->
    <div class="d-flex gap-8 mb-2" style="border-bottom:1px solid var(--border);padding-bottom:0">
        <a href="?tab=teachers" class="btn <?= $activeTab==='teachers'?'btn-primary':'btn-outline' ?> btn-sm">👨‍🏫 Teachers (<?= count($teachers) ?>)</a>
        <a href="?tab=students" class="btn <?= $activeTab==='students'?'btn-primary':'btn-outline' ?> btn-sm">🎓 E&L Students (<?= count($students) ?>)</a>
    </div>

    <?php if($activeTab==='teachers'): ?>
    <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start">
        <div class="card">
            <div class="card-header"><h3>Teachers (<?= count($teachers) ?>)</h3></div>
            <?php if($teachers): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Type</th><th>Subject</th><th>Rate T/P</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($teachers as $t): ?>
                    <tr>
                        <td>
                            <div class="fw-500"><?= e($t['name']) ?></div>
                            <div class="text-xs text-muted"><?= e($t['email']) ?></div>
                        </td>
                        <td><?= teacherTypeBadge($t['teacher_type']??'regular') ?><br><?= modeBadge($t['teacher_mode']??'theory') ?></td>
                        <td class="text-sm"><?= $t['subject_name'] ? e($t['subject_name']).'<br><span class="badge badge-expert" style="font-size:.66rem">'.e($t['subject_code']).'</span>' : '—' ?></td>
                        <td class="text-sm"><?= formatINR($t['rate_theory']) ?> / <?= formatINR($t['rate_practical']) ?></td>
                        <td><?= $t['is_active']?'<span class="badge badge-approved">Active</span>':'<span class="badge badge-rejected">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-8" style="flex-wrap:wrap">
                                <a href="?edit=<?= $t['id'] ?>&tab=teachers" class="btn btn-outline btn-sm">✏️ Edit</a>
                                <form method="POST" style="margin:0"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="urole" value="teacher"><input type="hidden" name="tab" value="teachers"><button class="btn btn-outline btn-sm" onclick="return confirmAction('Reset password to teacher@1234?')">🔑</button></form>
                                <?php if ($t['is_active']): ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Deactivate this teacher? They will lose access.')"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="urole" value="teacher"><input type="hidden" name="tab" value="teachers"><button class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#EF4444;border:1px solid rgba(239,68,68,.3)">🚫 Deactivate</button></form>
                                <?php else: ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Reactivate this teacher?')"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="urole" value="teacher"><input type="hidden" name="tab" value="teachers"><button class="btn btn-sm" style="background:rgba(34,197,94,.1);color:#16A34A;border:1px solid rgba(34,197,94,.3)">✅ Reactivate</button></form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><div class="icon">👨‍🏫</div><h3>No teachers added yet</h3></div><?php endif; ?>
        </div>

        <!-- Add/Edit Teacher Form -->
        <div class="card" style="position:sticky;top:80px">
            <div class="card-header"><h3><?= $editRow?'✏️ Edit':'➕ Add Teacher' ?></h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editRow?'edit':'add_teacher' ?>">
                    <input type="hidden" name="tab" value="teachers">
                    <?php if($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
                    <div class="form-group"><label>Full Name <span style="color:red">*</span></label><input type="text" name="name" class="form-control" required value="<?= e($editRow['name']??'') ?>"></div>
                    <?php if(!$editRow): ?>
                    <div class="form-group"><label>Email <span style="color:red">*</span></label><input type="email" name="email" class="form-control" required placeholder="teacher@gcea.edu"></div>
                    <div class="form-group"><label>Password</label><input type="text" name="password" class="form-control" value="teacher@1234"></div>
                    <?php endif; ?>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= e($editRow['phone']??'') ?>"></div>
                    <div class="form-group"><label>Teacher Type <span style="color:red">*</span></label>
                        <select name="teacher_type" class="form-control">
                            <?php foreach(['regular'=>'Regular','expert'=>'Expert','sectional_expert'=>'Sectional Expert','adjunct'=>'Adjunct'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= ($editRow['teacher_type']??'')===$v?'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Mode <span style="color:red">*</span></label>
                        <select name="teacher_mode" class="form-control">
                            <option value="theory"    <?= ($editRow['teacher_mode']??'')==='theory'   ?'selected':'' ?>>Theory</option>
                            <option value="practical" <?= ($editRow['teacher_mode']??'')==='practical'?'selected':'' ?>>Practical</option>
                            <option value="both"      <?= ($editRow['teacher_mode']??'')==='both'     ?'selected':'' ?>>Theory & Practical</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Assigned Subject <span style="color:red">*</span></label>
                        <select name="subject_id" class="form-control">
                            <option value="">— None —</option>
                            <?php foreach($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($editRow['subject_id']??0)==$s['id']?'selected':'' ?>><?= e($s['subject_name'].' ('.$s['subject_code'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Appointment Order No. <span style="color:red">*</span></label><input type="text" name="appointment_order_no" class="form-control" value="<?= e($editRow['appointment_order_no']??'') ?>"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                        <div class="form-group"><label>Rate Theory (₹) <span style="color:red">*</span></label><input type="number" name="rate_theory" class="form-control" step="0.01" min="0" value="<?= $editRow['rate_theory']??0 ?>"></div>
                        <div class="form-group"><label>Rate Practical (₹) <span style="color:red">*</span></label><input type="number" name="rate_practical" class="form-control" step="0.01" min="0" value="<?= $editRow['rate_practical']??0 ?>"></div>
                        <div class="form-group"><label>Rate Other (₹) <span style="color:red">*</span></label><input type="number" name="rate_other" class="form-control" step="0.01" min="0" value="<?= $editRow['rate_other']??0 ?>"></div>
                    </div>
                    <?php if($editRow): ?><div class="form-group"><label>Status</label><select name="is_active" class="form-control"><option value="1" <?= $editRow['is_active']?'selected':'' ?>>Active</option><option value="0" <?= !$editRow['is_active']?'selected':'' ?>>Inactive</option></select></div><?php endif; ?>
                    <button type="submit" class="btn btn-primary" style="width:100%"><?= $editRow?'💾 Update':'➕ Add Teacher' ?></button>
                    <?php if($editRow): ?><a href="?tab=teachers" class="btn btn-outline" style="width:100%;margin-top:8px;justify-content:center">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <?php else: // students tab ?>
    <div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start">
        <div class="card">
            <div class="card-header"><h3>Earn &amp; Learn Students (<?= count($students) ?>)</h3></div>
            <?php if($students): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Class</th><th>Rate/Hr</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($students as $s): ?>
                    <tr>
                        <td class="fw-500"><?= e($s['name']) ?></td>
                        <td class="text-sm"><?= e($s['email']) ?></td>
                        <td class="text-sm"><?= e($s['class_label']??'—') ?></td>
                        <td><?= formatINR($s['rate_per_hour']) ?>/hr</td>
                        <td><?= $s['is_active']?'<span class="badge badge-approved">Active</span>':'<span class="badge badge-rejected">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-8" style="flex-wrap:wrap">
                                <a href="?edit=<?= $s['id'] ?>&tab=students" class="btn btn-outline btn-sm">✏️ Edit</a>
                                <form method="POST" style="margin:0"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="urole" value="student"><input type="hidden" name="tab" value="students"><button class="btn btn-outline btn-sm" onclick="return confirmAction('Reset password to student@1234?')">🔑</button></form>
                                <?php if ($s['is_active']): ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Deactivate this student? They will lose access.')"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="urole" value="student"><input type="hidden" name="tab" value="students"><button class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#EF4444;border:1px solid rgba(239,68,68,.3)">🚫 Deactivate</button></form>
                                <?php else: ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Reactivate this student?')"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="urole" value="student"><input type="hidden" name="tab" value="students"><button class="btn btn-sm" style="background:rgba(34,197,94,.1);color:#16A34A;border:1px solid rgba(34,197,94,.3)">✅ Reactivate</button></form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><div class="icon">🎓</div><h3>No students added yet</h3></div><?php endif; ?>
        </div>

        <!-- Add/Edit Student Form -->
        <div class="card" style="position:sticky;top:80px">
            <div class="card-header"><h3><?= ($editRow&&$editRow['role']==='student')?'✏️ Edit Student':'➕ Add Student' ?></h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?= ($editRow&&$editRow['role']==='student')?'edit':'add_student' ?>">
                    <input type="hidden" name="tab" value="students">
                    <?php if($editRow&&$editRow['role']==='student'): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
                    <div class="form-group"><label>Full Name <span style="color:red">*</span></label><input type="text" name="name" class="form-control" placeholder="Enter Full Name" required value="<?= e(($editRow&&$editRow['role']==='student')?$editRow['name']:'') ?>"></div>
                    <?php if(!($editRow&&$editRow['role']==='student')): ?>
                    <div class="form-group"><label>Email <span style="color:red">*</span></label><input type="email" name="email" class="form-control" required placeholder="student@gcea.edu"></div>
                    <div class="form-group"><label>Password</label><input type="text" name="password" class="form-control" value="student@1234"></div>
                    <?php endif; ?>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" placeholder="Phone Number" value="<?= e(($editRow&&$editRow['role']==='student')?$editRow['phone']??'':'') ?>"></div>
                    <div class="form-group"><label>Class  <span style="color:red">*</span></label>
                        <select name="class_id" class="form-control" required>
                            <option value="">— Select Class —</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($editRow['class_id']??0)==$c['id'])?'selected':'' ?>><?= e($c['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Rate per Hour (₹) <span style="color:red">*</span></label><input type="number" name="rate_per_hour" class="form-control" step="0.01" min="0" value="<?= ($editRow&&$editRow['role']==='student')?$editRow['rate_per_hour']:50 ?>" required></div>
                    <?php if($editRow&&$editRow['role']==='student'): ?><div class="form-group"><label>Status</label><select name="is_active" class="form-control"><option value="1" <?= $editRow['is_active']?'selected':'' ?>>Active</option><option value="0" <?= !$editRow['is_active']?'selected':'' ?>>Inactive</option></select></div><?php endif; ?>
                    <button type="submit" class="btn btn-primary" style="width:100%"><?= ($editRow&&$editRow['role']==='student')?'💾 Update':'➕ Add Student' ?></button>
                    <?php if($editRow&&$editRow['role']==='student'): ?><a href="?tab=students" class="btn btn-outline" style="width:100%;margin-top:8px;justify-content:center">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
<?php renderFooter(); ?>
