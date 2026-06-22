<?php
// ============================================================
//  admin/manage-hods.php — Manage HODs
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dept  = (int)$_POST['department_id'];
        $pass  = $_POST['password'] ?? 'hod@1234';

        if ($name && $email && $dept) {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $dup->execute([$email]);
            if ($dup->fetch()) {
                setFlash('error', 'Email already exists.');
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name,email,password,role,department_id,phone) VALUES (?,?,?,'hod',?,?)")
                    ->execute([$name,$email,$hash,$dept,$phone]);
                logActivity($pdo,$user['id'],'add_hod',"Added HOD: $name");
                setFlash('success', "HOD \"$name\" added. Password: $pass");
            }
        } else {
            setFlash('error', 'Name, email and department are required.');
        }
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $name   = trim($_POST['name']  ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $dept   = (int)$_POST['department_id'];
        $active = (int)$_POST['is_active'];
        $pdo->prepare("UPDATE users SET name=?,phone=?,department_id=?,is_active=? WHERE id=? AND role='hod'")
            ->execute([$name,$phone,$dept,$active,$id]);
        logActivity($pdo,$user['id'],'edit_hod',"Updated HOD #$id");
        setFlash('success', 'HOD updated.');
    }

    if ($action === 'reset_password') {
        $id   = (int)$_POST['id'];
        $pass = 'hod@1234';
        $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='hod'")
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
        setFlash('success', "Password reset to: $pass");
    }

    header('Location: manage-hods.php'); exit;
}

$editId  = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
    $s = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='hod'");
    $s->execute([$editId]); $editRow = $s->fetch();
}

$depts = $pdo->query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
$hods  = $pdo->query(
    "SELECT u.*, d.name AS dept_name FROM users u
     LEFT JOIN departments d ON d.id=u.department_id
     WHERE u.role='hod' ORDER BY u.name"
)->fetchAll();

renderHead('Manage HODs');
?>
<div class="app-layout">
<?php renderSidebar('manage-hods','admin',$user); ?>
<div class="main-content">
<?php renderTopbar('Manage HODs'); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header">
        <h1>Manage HODs</h1>
        <p>Add and manage Head of Department accounts</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start">

        <!-- List -->
        <div class="card">
            <div class="card-header">
                <h3>HODs (<?= count($hods) ?>)</h3>
            </div>
            <?php if ($hods): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($hods as $i => $h): ?>
                    <tr>
                        <td class="text-muted"><?= $i+1 ?></td>
                        <td>
                            <div class="fw-500"><?= e($h['name']) ?></div>
                            <div class="text-xs text-muted"><?= e($h['phone'] ?? '') ?></div>
                        </td>
                        <td class="text-sm"><?= e($h['email']) ?></td>
                        <td><?= e($h['dept_name'] ?? '—') ?></td>
                        <td><?= $h['is_active'] ? '<span class="badge badge-approved">Active</span>' : '<span class="badge badge-rejected">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-8">
                                <a href="?edit=<?= $h['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                    <button class="btn btn-outline btn-sm"
                                            onclick="return confirmAction('Reset password to hod@1234?')">🔑
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><div class="icon">👥</div><h3>No HODs added yet</h3></div>
            <?php endif; ?>
        </div>

        <!-- Form -->
        <div class="card" style="position:sticky;top:80px">
            <div class="card-header"><h3><?= $editRow ? '✏️ Edit HOD' : '➕ Add HOD' ?></h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
                    <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Full Name <span style="color:red">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= e($editRow['name'] ?? '') ?>" placeholder="Dr. Full Name">
                    </div>

                    <?php if (!$editRow): ?>
                    <div class="form-group">
                        <label>Email <span style="color:red">*</span></label>
                        <input type="email" name="email" class="form-control" required
                               placeholder="hod@gcea.edu">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="text" name="password" class="form-control" value="hod@1234">
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>Email (read-only)</label>
                        <input type="email" class="form-control" value="<?= e($editRow['email']) ?>" disabled>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Department <span style="color:red">*</span></label>
                        <select name="department_id" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach ($depts as $d): ?>
                            <option value="<?= $d['id'] ?>"
                                <?= ($editRow['department_id'] ?? 0) == $d['id'] ? 'selected' : '' ?>>
                                <?= e($d['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control"
                               value="<?= e($editRow['phone'] ?? '') ?>" placeholder="10-digit number">
                    </div>

                    <?php if ($editRow): ?>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" class="form-control">
                            <option value="1" <?= $editRow['is_active']?'selected':'' ?>>Active</option>
                            <option value="0" <?= !$editRow['is_active']?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary" style="width:100%">
                        <?= $editRow ? '💾 Update HOD' : '➕ Add HOD' ?>
                    </button>
                    <?php if ($editRow): ?>
                    <a href="manage-hods.php" class="btn btn-outline" style="width:100%;margin-top:8px;justify-content:center">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>
