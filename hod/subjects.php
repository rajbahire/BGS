<?php
// ============================================================
//  hod/subjects.php — HOD: Manage Subjects
//  Scoped to HOD's own department classes only
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

// ── Helper: verify a class belongs to this HOD's dept ────────
function classOwnedByHod(PDO $pdo, int $classId, int $deptId): bool {
    $s = $pdo->prepare("SELECT id FROM classes WHERE id=? AND department_id=?");
    $s->execute([$classId, $deptId]);
    return (bool)$s->fetch();
}

// ── POST handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add subject ──────────────────────────────────────────
    if ($action === 'add') {
        $classId = (int)$_POST['class_id'];
        $name    = trim($_POST['subject_name'] ?? '');
        $code    = strtoupper(trim($_POST['subject_code'] ?? ''));
        $mode    = $_POST['mode'] ?? 'theory';

        if ($classId && $name && $code) {
            if (!classOwnedByHod($pdo, $classId, $deptId)) {
                setFlash('error', 'Access denied — class does not belong to your department.');
            } else {
                try {
                    $pdo->prepare("INSERT INTO subjects (class_id,subject_name,subject_code,mode) VALUES (?,?,?,?)")
                        ->execute([$classId, $name, $code, $mode]);
                    logActivity($pdo, $user['id'], 'add_subject', "HOD added subject: $name ($code)");
                    setFlash('success', "Subject \"$name\" added successfully.");
                } catch (PDOException $e) {
                    setFlash('error', 'Failed to add subject. Please try again.');
                }
            }
        } else {
            setFlash('error', 'All required fields must be filled.');
        }
    }

    // ── Edit subject ─────────────────────────────────────────
    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $name   = trim($_POST['subject_name'] ?? '');
        $code   = strtoupper(trim($_POST['subject_code'] ?? ''));
        $mode   = $_POST['mode'] ?? 'theory';
        $active = (int)$_POST['is_active'];

        if ($id && $name && $code) {
            // Verify subject belongs to a class in this HOD's dept
            $verify = $pdo->prepare(
                "SELECT s.id FROM subjects s
                 JOIN classes c ON c.id=s.class_id
                 WHERE s.id=? AND c.department_id=?"
            );
            $verify->execute([$id, $deptId]);
            if ($verify->fetch()) {
                $pdo->prepare("UPDATE subjects SET subject_name=?,subject_code=?,mode=?,is_active=? WHERE id=?")
                    ->execute([$name, $code, $mode, $active, $id]);
                logActivity($pdo, $user['id'], 'edit_subject', "HOD updated subject ID $id: $name ($code)");
                setFlash('success', 'Subject updated successfully.');
            } else {
                setFlash('error', 'Access denied.');
            }
        } else {
            setFlash('error', 'All required fields must be filled.');
        }
    }

    // ── Delete subject ───────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            $verify = $pdo->prepare(
                "SELECT s.id FROM subjects s
                 JOIN classes c ON c.id=s.class_id
                 WHERE s.id=? AND c.department_id=?"
            );
            $verify->execute([$id, $deptId]);
            if ($verify->fetch()) {
                // Check if any teacher is assigned to this subject
                $assigned = $pdo->prepare("SELECT COUNT(*) FROM users WHERE subject_id=? AND role='teacher'");
                $assigned->execute([$id]);
                $count = (int)$assigned->fetchColumn();

                if ($count > 0) {
                    // Soft delete — teachers assigned
                    $pdo->prepare("UPDATE subjects SET is_active=0 WHERE id=?")->execute([$id]);
                    setFlash('warning', "Subject has $count teacher(s) assigned — it has been deactivated instead of deleted.");
                } else {
                    $pdo->prepare("DELETE FROM subjects WHERE id=?")->execute([$id]);
                    setFlash('success', 'Subject deleted successfully.');
                }
                logActivity($pdo, $user['id'], 'delete_subject', "HOD deleted/deactivated subject ID $id");
            } else {
                setFlash('error', 'Access denied.');
            }
        }
    }

    $filterClass = (int)($_POST['filter_class'] ?? 0);
    header('Location: subjects.php' . ($filterClass ? "?class=$filterClass" : '')); exit;
}

// ── GET: fetch edit row ───────────────────────────────────────
$editId  = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
    $s = $pdo->prepare(
        "SELECT s.*, c.label AS class_label FROM subjects s
         JOIN classes c ON c.id=s.class_id
         WHERE s.id=? AND c.department_id=?"
    );
    $s->execute([$editId, $deptId]);
    $editRow = $s->fetch();
}

$filterClass = (int)($_GET['class'] ?? 0);

// ── All classes for this dept (for filter + add form) ─────────
$deptClasses = $pdo->prepare(
    "SELECT * FROM classes WHERE department_id=? AND is_active=1 ORDER BY year, semester"
);
$deptClasses->execute([$deptId]);
$deptClasses = $deptClasses->fetchAll();

// ── Load subjects ─────────────────────────────────────────────
$sql    = "SELECT s.*, c.label AS class_label, c.year, c.semester
           FROM subjects s
           JOIN classes c ON c.id=s.class_id
           WHERE c.department_id=?";
$params = [$deptId];
if ($filterClass) { $sql .= " AND s.class_id=?"; $params[] = $filterClass; }
$sql .= " ORDER BY c.year, c.semester, s.subject_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$subjects = $stmt->fetchAll();

$deptName = $user['dept_name'] ?: 'Your Department';

renderHead('HOD — Subjects');
?>
<div class="app-layout">
<?php renderSidebar('subjects','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Manage Subjects'); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header">
        <h1>Subjects</h1>
        <p>Manage subjects for <strong><?= e($deptName) ?></strong> classes</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start">

        <!-- Left: Filter + Table -->
        <div>
            <!-- Filter bar -->
            <div class="card" style="margin-bottom:1rem">
                <div class="card-body" style="padding:.9rem">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                        <div class="form-group" style="margin:0;flex:1;min-width:200px">
                            <label>Filter by Class</label>
                            <select name="class" class="form-control" onchange="this.form.submit()">
                                <option value="">All Classes</option>
                                <?php foreach ($deptClasses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['label']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($filterClass): ?>
                        <a href="subjects.php" class="btn btn-outline btn-sm"><?= svgIcon('close') ?> Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Subjects Table -->
            <div class="card">
                <div class="card-header">
                    <h3>Subjects (<?= count($subjects) ?>)</h3>
                    <?php if ($filterClass): ?>
                    <span class="badge badge-expert">Filtered</span>
                    <?php endif; ?>
                </div>
                <?php if ($subjects): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Subject</th>
                                <th>Code</th>
                                <th>Class</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subjects as $i => $s): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td class="fw-500"><?= e($s['subject_name']) ?></td>
                            <td><span class="badge badge-expert"><?= e($s['subject_code']) ?></span></td>
                            <td class="text-sm"><?= e($s['class_label']) ?></td>
                            <td><?= modeBadge($s['mode']) ?></td>
                            <td>
                                <?= $s['is_active']
                                    ? '<span class="badge badge-approved">Active</span>'
                                    : '<span class="badge badge-rejected">Inactive</span>' ?>
                            </td>
                            <td>
                                <div class="d-flex gap-8">
                                    <a href="?edit=<?= $s['id'] ?><?= $filterClass ? "&class=$filterClass" : '' ?>"
                                       class="btn btn-outline btn-sm"><?= svgIcon('edit') ?> Edit</a>
                                    <form method="POST" style="margin:0"
                                          onsubmit="return confirmAction('Delete / deactivate this subject?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
                                        <button type="submit" class="btn btn-sm btn-delete"><?= svgIcon('delete') ?> Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="icon"><?= svgIcon('subjects') ?></div>
                    <h3>No subjects found</h3>
                    <p><?= $filterClass ? 'No subjects in this class.' : 'Add a subject using the form on the right.' ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Add / Edit Form -->
        <div class="card" style="position:sticky;top:80px">
            <div class="card-header">
                <h3><?= $editRow ? svgIcon('edit') . ' Edit Subject' : svgIcon('add') . ' Add Subject' ?></h3>
                <?php if ($editRow): ?>
                <a href="subjects.php" class="btn btn-outline btn-sm">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
                    <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
                    <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
                    <?php endif; ?>

                    <?php if (!$editRow): ?>
                    <!-- Class selector (only for add) -->
                    <div class="form-group">
                        <label>Class <span style="color:red">*</span></label>
                        <?php if (empty($deptClasses)): ?>
                        <div class="alert alert-warning" style="font-size:.85rem;margin:0">
                            <?= svgIcon('warning') ?> No active classes found. <a href="classes.php">Add a class first.</a>
                        </div>
                        <?php else: ?>
                        <select name="class_id" class="form-control" required>
                            <option value="">— Select Class —</option>
                            <?php foreach ($deptClasses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['label']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <!-- Show class label read-only when editing -->
                    <div class="form-group">
                        <label>Class</label>
                        <input type="text" class="form-control" disabled
                               value="<?= e($editRow['class_label'] ?? '—') ?>"
                               style="background:var(--bg);color:var(--light)">
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Subject Name <span style="color:red">*</span></label>
                        <input type="text" name="subject_name" class="form-control" required
                               value="<?= e($editRow['subject_name'] ?? '') ?>"
                               placeholder="e.g. Data Structures">
                    </div>

                    <div class="form-group">
                        <label>Subject Code <span style="color:red">*</span></label>
                        <input type="text" name="subject_code" class="form-control" required
                               style="text-transform:uppercase"
                               value="<?= e($editRow['subject_code'] ?? '') ?>"
                               placeholder="e.g. CS301">
                    </div>

                    <div class="form-group">
                        <label>Mode <span style="color:red">*</span></label>
                        <select name="mode" class="form-control" required>
                            <option value="">— Select Mode —</option>
                            <option value="theory"    <?= ($editRow['mode'] ?? '') === 'theory'    ? 'selected' : '' ?>>Theory</option>
                            <option value="practical" <?= ($editRow['mode'] ?? '') === 'practical' ? 'selected' : '' ?>>Practical</option>
                            <option value="theory & practical" <?= ($editRow['mode'] ?? '') === 'theory & practical' ? 'selected' : '' ?>>Theory &amp; Practical</option>
                        </select>
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

                    <button type="submit" class="btn btn-primary" style="width:100%"
                            <?= empty($deptClasses) && !$editRow ? 'disabled' : '' ?>>
                        <?= $editRow ? svgIcon('save') . ' Update Subject' : svgIcon('add') . ' Add Subject' ?>
                    </button>
                    <?php if ($editRow): ?>
                    <a href="subjects.php" class="btn btn-outline" style="width:100%;margin-top:8px;justify-content:center">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>
