<?php
// ============================================================
//  admin/departments.php — Manage Departments
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$user = currentUser();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name']       ?? '');
        $short = strtoupper(trim($_POST['short_name'] ?? ''));
        if ($name && $short) {
            $pdo->prepare("INSERT INTO departments (name, short_name) VALUES (?,?)")
                ->execute([$name, $short]);
            logActivity($pdo, $user['id'], 'add_department', "Added department: $name");
            setFlash('success', "Department \"$name\" added successfully.");
        } else {
            setFlash('error', 'Name and short name are required.');
        }
    }

    if ($action === 'edit') {
        $id    = (int)$_POST['id'];
        $name  = trim($_POST['name']       ?? '');
        $short = strtoupper(trim($_POST['short_name'] ?? ''));
        $active= (int)($_POST['is_active'] ?? 1);
        if ($id && $name && $short) {
            $pdo->prepare("UPDATE departments SET name=?, short_name=?, is_active=? WHERE id=?")
                ->execute([$name, $short, $active, $id]);
            logActivity($pdo, $user['id'], 'edit_department', "Updated department #$id");
            setFlash('success', 'Department updated.');
        } else {
            setFlash('error', 'All fields required.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Check for linked records before deleting
        $checkClasses  = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE department_id=?");
        $checkClasses->execute([$id]);
        $classCount = (int)$checkClasses->fetchColumn();

        $checkUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id=?");
        $checkUsers->execute([$id]);
        $userCount = (int)$checkUsers->fetchColumn();

        if ($classCount > 0 || $userCount > 0) {
            $parts = [];
            if ($classCount > 0)  $parts[] = "$classCount class(es)";
            if ($userCount > 0)   $parts[] = "$userCount user(s)";
            setFlash('error', 'Cannot delete — department has ' . implode(' and ', $parts) . ' linked. Remove or reassign them first.');
        } else {
            $pdo->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
            logActivity($pdo, $user['id'], 'delete_department', "Deleted department #$id");
            setFlash('success', 'Department deleted.');
        }
    }

    header('Location: departments.php'); exit;
}

// Load for editing
$editId  = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
    $s = $pdo->prepare("SELECT * FROM departments WHERE id=?");
    $s->execute([$editId]);
    $editRow = $s->fetch();
}

// All departments with counts
$depts = $pdo->query(
    "SELECT d.*,
        (SELECT COUNT(*) FROM classes c WHERE c.department_id=d.id) AS class_count,
        (SELECT COUNT(*) FROM users u WHERE u.department_id=d.id AND u.role='teacher') AS teacher_count
     FROM departments d ORDER BY d.name"
)->fetchAll();

renderHead('Departments');
?>
<div class="app-layout">
<?php renderSidebar('departments', 'admin', $user); ?>
<div class="main-content">
<?php renderTopbar('Departments'); ?>
<div class="page-body">
    <?= getFlash() ?>

    <div class="page-header">
        <h1>Departments</h1>
        <p>Manage all departments of the college</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start">

        <!-- Table -->
        <div class="card">
            <div class="card-header">
                <h3>All Departments (<?= count($depts) ?>)</h3>
                <input type="text" class="form-control" style="width:200px"
                       placeholder="Search…" data-search-table="dept-table">
            </div>
            <?php if ($depts): ?>
            <div class="table-wrap">
                <table id="dept-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Department Name</th>
                            <th>Short</th>
                            <th>Classes</th>
                            <th>Teachers</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($depts as $i => $d): ?>
                    <tr>
                        <td class="text-muted"><?= $i+1 ?></td>
                        <td class="fw-500"><?= e($d['name']) ?></td>
                        <td><span class="badge badge-expert"><?= e($d['short_name']) ?></span></td>
                        <td><?= (int)$d['class_count'] ?></td>
                        <td><?= (int)$d['teacher_count'] ?></td>
                        <td>
                            <?php if ($d['is_active']): ?>
                            <span class="badge badge-approved">Active</span>
                            <?php else: ?>
                            <span class="badge badge-rejected">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?edit=<?= $d['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                            <form method="POST" style="display:inline" onsubmit="return confirmAction('Delete this department? Linked classes and users will block the delete.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button type="submit" class="btn btn-delete btn-sm">🗑 Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="icon">🏛</div>
                <h3>No departments yet</h3>
                <p>Add your first department using the form.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Add / Edit Form -->
        <div class="card" style="position:sticky;top:80px">
            <div class="card-header">
                <h3><?= $editRow ? '✏️ Edit Department' : '➕ Add Department' ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($editRow): ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id"     value="<?= $editRow['id'] ?>">
                    <?php else: ?>
                    <input type="hidden" name="action" value="add">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Department Name <span style="color:red">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= e($editRow['name'] ?? '') ?>"
                               placeholder="e.g. Computer Science &amp; Engineering">
                    </div>

                    <div class="form-group">
                        <label>Short Name <span style="color:red">*</span></label>
                        <input type="text" name="short_name" class="form-control" required
                               maxlength="20"
                               value="<?= e($editRow['short_name'] ?? '') ?>"
                               placeholder="e.g. CSE">
                    </div>

                    <?php if ($editRow): ?>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" class="form-control">
                            <option value="1" <?= $editRow['is_active'] ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= !$editRow['is_active'] ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary" style="width:100%">
                        <?= $editRow ? '💾 Update Department' : '➕ Add Department' ?>
                    </button>
                    <?php if ($editRow): ?>
                    <a href="departments.php" class="btn btn-outline" style="width:100%;margin-top:8px;justify-content:center">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>
