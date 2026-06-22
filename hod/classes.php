<?php
// ============================================================
//  hod/classes.php — HOD: Manage Classes (Year + Semester)
//  Scoped to HOD's own department only
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

// ── POST handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add ──────────────────────────────────────────────────
    if ($action === 'add') {
        $year  = (int)$_POST['year'];
        $sem   = (int)$_POST['semester'];
        $label = trim($_POST['label'] ?? '');
        if ($year && $sem && $label) {
            try {
                $pdo->prepare("INSERT INTO classes (department_id,year,semester,label) VALUES (?,?,?,?)")
                    ->execute([$deptId, $year, $sem, $label]);
                logActivity($pdo, $user['id'], 'add_class', "HOD added class: $label");
                setFlash('success', "Class \"$label\" added successfully.");
            } catch (PDOException $e) {
                setFlash('error', 'A class for this year and semester already exists in your department.');
            }
        } else {
            setFlash('error', 'All fields are required.');
        }
    }

    // ── Edit ─────────────────────────────────────────────────
    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $label  = trim($_POST['label'] ?? '');
        $active = (int)$_POST['is_active'];
        if ($id && $label) {
            // Verify class belongs to this HOD's department
            $check = $pdo->prepare("SELECT id FROM classes WHERE id=? AND department_id=?");
            $check->execute([$id, $deptId]);
            if ($check->fetch()) {
                $pdo->prepare("UPDATE classes SET label=?, is_active=? WHERE id=? AND department_id=?")
                    ->execute([$label, $active, $id, $deptId]);
                logActivity($pdo, $user['id'], 'edit_class', "HOD updated class ID $id: $label");
                setFlash('success', 'Class updated successfully.');
            } else {
                setFlash('error', 'Access denied.');
            }
        } else {
            setFlash('error', 'Label is required.');
        }
    }

    // ── Delete (hard delete if no subjects, else deactivate) ──
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            $check = $pdo->prepare("SELECT id FROM classes WHERE id=? AND department_id=?");
            $check->execute([$id, $deptId]);
            if ($check->fetch()) {
                $subjCount = (int)$pdo->prepare("SELECT COUNT(*) FROM subjects WHERE class_id=?")->execute([$id])
                    ? $pdo->query("SELECT COUNT(*) FROM subjects WHERE class_id=$id")->fetchColumn()
                    : 0;
                $s = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE class_id=?");
                $s->execute([$id]);
                $subjCount = (int)$s->fetchColumn();

                if ($subjCount > 0) {
                    // Soft delete — has subjects
                    $pdo->prepare("UPDATE classes SET is_active=0 WHERE id=? AND department_id=?")
                        ->execute([$id, $deptId]);
                    setFlash('warning', 'Class has subjects — it has been deactivated instead of deleted.');
                } else {
                    $pdo->prepare("DELETE FROM classes WHERE id=? AND department_id=?")->execute([$id, $deptId]);
                    setFlash('success', 'Class deleted successfully.');
                }
                logActivity($pdo, $user['id'], 'delete_class', "HOD deleted/deactivated class ID $id");
            } else {
                setFlash('error', 'Access denied.');
            }
        }
    }

    header('Location: classes.php'); exit;
}

// ── GET: fetch edit row ───────────────────────────────────────
$editId  = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
    $s = $pdo->prepare("SELECT * FROM classes WHERE id=? AND department_id=?");
    $s->execute([$editId, $deptId]);
    $editRow = $s->fetch();
}

// ── Load classes for this dept ────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT c.*,
        (SELECT COUNT(*) FROM subjects s WHERE s.class_id=c.id) AS subject_count
     FROM classes c
     WHERE c.department_id=?
     ORDER BY c.year, c.semester"
);
$stmt->execute([$deptId]);
$classes = $stmt->fetchAll();

// ── Fetch dept short_name for label generation ───────────────
$deptRow  = $pdo->prepare("SELECT name, short_name FROM departments WHERE id=? LIMIT 1");
$deptRow->execute([$deptId]);
$deptRow  = $deptRow->fetch();
$deptName  = $deptRow['name']      ?? ($user['dept_name'] ?: 'Your Department');
$deptShort = $deptRow['short_name'] ?? '';

// ── Build taken year+semester pairs for this dept (for JS filter) ─
$takenPairs = array_map(
    fn($c) => [(int)$c['year'], (int)$c['semester']],
    $classes
);

renderHead('HOD — Classes');
?>
<div class="app-layout">
<?php renderSidebar('classes','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Manage Classes'); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header">
        <h1>Classes</h1>
        <p>Manage year &amp; semester classes for <strong><?= e($deptName) ?></strong></p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start">

        <!-- Class List Table -->
        <div class="card">
            <div class="card-header">
                <h3>Classes</h3>
            </div>
            <?php if ($classes): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Label</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Subjects</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($classes as $i => $c): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-500"><?= e($c['label']) ?></td>
                        <td>
                            <?php
                            $yLabels = [1=>'FY (1st)',2=>'SY (2nd)',3=>'TY (3rd)',4=>'LY (4th)'];
                            echo $yLabels[$c['year']] ?? $c['year'];
                            ?>
                        </td>
                        <td>Sem <?= (int)$c['semester'] ?></td>
                        <td>
                            <span class="badge badge-draft"><?= (int)$c['subject_count'] ?> subjects</span>
                        </td>
                        <td>
                            <?= $c['is_active']
                                ? '<span class="badge badge-approved">Active</span>'
                                : '<span class="badge badge-rejected">Inactive</span>' ?>
                        </td>
                        <td>
                            <div class="d-flex gap-8">
                                <a href="?edit=<?= $c['id'] ?>" class="btn btn-outline btn-sm">✏️ Edit</a>
                                <form method="POST" style="margin:0"
                                      onsubmit="return confirmAction('<?= $c['subject_count'] > 0 ? 'This class has subjects and will be deactivated. Continue?' : 'Delete this class permanently?' ?>')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm"
                                            style="background:rgba(239,68,68,.1);color:#EF4444;border:1px solid rgba(239,68,68,.3)">
                                        🗑 <?= $c['subject_count'] > 0 ? 'Deactivate' : 'Delete' ?>
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
            <div class="empty-state">
                <div class="icon">📚</div>
                <h3>No classes yet</h3>
                <p>Add a class using the form on the right.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Add / Edit Form -->
        <div class="card" style="position:sticky;top:80px">
            <div class="card-header">
                <h3><?= $editRow ? '✏️ Edit Class' : '➕ Add Class' ?></h3>
                <?php if ($editRow): ?>
                <a href="classes.php" class="btn btn-outline btn-sm">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" id="class-form">
                    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
                    <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
                    <?php endif; ?>

                    <!-- Department (read-only display) -->
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" class="form-control" value="<?= e($deptName) ?>" disabled
                               style="background:var(--bg);color:var(--light)">
                    </div>

                    <?php if (!$editRow): ?>
                    <!-- Year -->
                    <div class="form-group">
                        <label>Year <span style="color:red">*</span></label>
                        <select name="year" id="sel-year" class="form-control" required
                                onchange="filterSemesters(); autoLabel();">
                            <option value="">— Select Year —</option>
                            <option value="1">1st Year (FY)</option>
                            <option value="2">2nd Year (SY)</option>
                            <option value="3">3rd Year (TY)</option>
                            <option value="4">4th Year (LY)</option>
                        </select>
                    </div>

                    <!-- Semester -->
                    <div class="form-group">
                        <label>Semester <span style="color:red">*</span></label>
                        <select name="semester" id="sel-sem" class="form-control" required
                                onchange="autoLabel()">
                            <option value="">— Select Year first —</option>
                        </select>
                    </div>

                    <?php else: ?>
                    <!-- Show year/sem as read-only when editing -->
                    <div class="form-group">
                        <label>Year</label>
                        <input type="text" class="form-control" disabled
                               value="<?= [1=>'FY (1st)',2=>'SY (2nd)',3=>'TY (3rd)',4=>'LY (4th)'][$editRow['year']] ?? $editRow['year'] ?>"
                               style="background:var(--bg);color:var(--light)">
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <input type="text" class="form-control" disabled
                               value="Semester <?= (int)$editRow['semester'] ?>"
                               style="background:var(--bg);color:var(--light)">
                    </div>
                    <?php endif; ?>

                    <!-- Label -->
                    <div class="form-group">
                        <label>Class Label <span style="color:red">*</span></label>
                        <input type="text" name="label" id="lbl-input" class="form-control" required
                               value="<?= e($editRow['label'] ?? '') ?>"
                               placeholder="e.g. FY <?= e($deptShort ?: 'DEPT') ?> Sem 1">
                    </div>

                    <!-- Status (edit only) -->
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
                        <?= $editRow ? '💾 Update Class' : '➕ Add Class' ?>
                    </button>
                    <?php if ($editRow): ?>
                    <a href="classes.php" class="btn btn-outline" style="width:100%;margin-top:8px;justify-content:center">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>
<script>
const deptShort  = '<?= e($deptShort) ?>';
const yLabels    = {1:'FY',2:'SY',3:'TY',4:'LY'};
const takenPairs = <?= json_encode($takenPairs) ?>; // [[year,sem], ...] already taken

function filterSemesters() {
    const year = document.getElementById('sel-year');
    const sem  = document.getElementById('sel-sem');
    if (!year || !sem) return;

    const yearV   = parseInt(year.value) || 0;
    const prevVal = sem.value;

    if (!yearV) {
        sem.innerHTML = '<option value="">— Select Year first —</option>';
        return;
    }

    // Collect taken semesters for this year
    const taken = new Set();
    takenPairs.forEach(([y, s]) => { if (y === yearV) taken.add(s); });

    sem.innerHTML = '<option value="">— Select Semester —</option>';
    let added = 0;
    [1,2,3,4,5,6,7,8].forEach(s => {
        if (!taken.has(s)) {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = 'Semester ' + s;
            if (parseInt(prevVal) === s) opt.selected = true;
            sem.appendChild(opt);
            added++;
        }
    });

    if (added === 0) {
        sem.innerHTML = '<option value="">All semesters taken for this year</option>';
    }
}

function autoLabel() {
    const year = document.getElementById('sel-year');
    const sem  = document.getElementById('sel-sem');
    const lbl  = document.getElementById('lbl-input');
    if (!year || !sem || !lbl) return;
    const yL = yLabels[year.value] || '';
    if (yL && sem.value) {
        lbl.value = yL + ' ' + deptShort + ' Sem ' + sem.value;
    }
}
</script>
