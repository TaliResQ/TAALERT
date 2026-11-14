<?php
/*
|--------------------------------------------------------------------------
| TALIRESQ - EMERGENCY HOTLINES & SAFETY INFORMATION
|--------------------------------------------------------------------------
| Hotlines table:       public.hotlines (based on your INSERT statement)
| Safety guides table:  public.safety_guides
| PHP 7.4+
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection error: $pdo was not initialized in config/db.php.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/**
 * Locate a table by its exact table name and required columns.
 * Prefers public, then checks all other accessible schemas.
 */
function resolveTable(PDO $pdo, $tableName, array $requiredColumns)
{
    $sql = "
        SELECT table_schema, column_name
        FROM information_schema.columns
        WHERE table_name = :table_name
          AND table_schema NOT IN ('pg_catalog', 'information_schema')
        ORDER BY CASE WHEN table_schema = 'public' THEN 0 ELSE 1 END,
                 table_schema, ordinal_position
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':table_name' => $tableName]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $schemas = [];
    foreach ($rows as $row) {
        $schemas[$row['table_schema']][] = $row['column_name'];
    }

    foreach ($schemas as $schema => $columns) {
        if (count(array_diff($requiredColumns, $columns)) === 0) {
            return '"' . str_replace('"', '""', $schema) . '"."' .
                   str_replace('"', '""', $tableName) . '"';
        }
    }

    $found = [];
    foreach ($schemas as $schema => $columns) {
        $found[] = $schema . '.' . $tableName . ' [' . implode(', ', $columns) . ']';
    }

    $detail = $found
        ? ' Found: ' . implode('; ', $found)
        : ' No table with that name is visible to this database connection.';

    throw new RuntimeException(
        'Could not find a ' . $tableName . ' table containing these columns: ' .
        implode(', ', $requiredColumns) . '.' . $detail
    );
}

try {
    // Your supplied INSERT statement targets public.hotlines.
    $hotlinesTable = resolveTable($pdo, 'hotlines', [
       'hotline_id', 'area_name', 'title', 'contact_number', 'description', 'is_emergency', 'created_by', 'created_at', 'updated_at'
    ]);

    // Use the table's primary-key columns in all record queries.
    $guidesTable = resolveTable($pdo, 'safety_guides', [
       'guide_id', 'hazard_type', 'phase', 'title', 'content', 'is_published', 'created_at', 'updated_at', 'created_by'
    ]);
} catch (Exception $tableError) {
    die('TaliResq table configuration error: ' .
        htmlspecialchars($tableError->getMessage(), ENT_QUOTES, 'UTF-8'));
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectPage()
{
    header('Location: safety-info.php');
    exit;
}

function postValue($key)
{
    return trim((string)($_POST[$key] ?? ''));
}

function validRecordId($value)
{
    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]) !== false;
}

function isPublished($value)
{
    return $value === true || $value === 't' || $value === 1 || $value === '1';
}

$success = '';
$error = '';

/* --------------------------------------------------------------------------
   HANDLE FORM SUBMISSIONS
-------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_hotline'])) {
            $agency = postValue('agency');
            $number = postValue('contact_number');
            $description = postValue('description');

            if ($agency === '' || $number === '' || $description === '') {
                throw new Exception('Please complete all hotline fields.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO ' . $hotlinesTable . '
                    (area_name, title, contact_number, description, is_emergency)
                 VALUES
                    (:area_name, :title, :contact_number, :description, TRUE)'
            );
            $stmt->execute([
                ':area_name' => $agency,
                ':title' => $agency,
                ':contact_number' => $number,
                ':description' => $description
            ]);

            $_SESSION['flash_success'] = 'Emergency hotline added successfully.';
            redirectPage();
        }

        if (isset($_POST['update_hotline'])) {
            $id = $_POST['id'] ?? '';
            if (!validRecordId($id)) {
                throw new Exception('Invalid hotline record ID.');
            }

            $agency = postValue('agency');
            $number = postValue('contact_number');
            $description = postValue('description');

            if ($agency === '' || $number === '' || $description === '') {
                throw new Exception('Please complete all hotline fields.');
            }

            $stmt = $pdo->prepare(
                'UPDATE ' . $hotlinesTable . '
                 SET area_name = :area_name,
                     title = :title,
                     contact_number = :contact_number,
                     description = :description,
                     updated_at = NOW()
                 WHERE hotline_id = :id'
            );
            $stmt->execute([
                ':area_name' => $agency,
                ':title' => $agency,
                ':contact_number' => $number,
                ':description' => $description,
                ':id' => (int)$id
            ]);

            $_SESSION['flash_success'] = 'Emergency hotline updated successfully.';
            redirectPage();
        }

        if (isset($_POST['delete_hotline'])) {
            $id = $_POST['id'] ?? '';
            if (!validRecordId($id)) {
                throw new Exception('Invalid hotline record ID.');
            }

            $stmt = $pdo->prepare(
                'DELETE FROM ' . $hotlinesTable . ' WHERE hotline_id = :id'
            );
            $stmt->execute([':id' => (int)$id]);

            $_SESSION['flash_success'] = 'Emergency hotline deleted.';
            redirectPage();
        }

        if (isset($_POST['add_guide'])) {
            $title = postValue('title');
            $phase = postValue('phase');
            $hazard = postValue('hazard_type');
            $content = postValue('content');
            $published = isset($_POST['is_published']);

            if ($title === '' || $content === '') {
                throw new Exception('Please provide a title and safety instructions.');
            }
            if (!in_array($phase, ['Before', 'During', 'After'], true)) {
                throw new Exception('Invalid disaster phase.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO ' . $guidesTable . '
                    (title, phase, hazard_type, content, is_published)
                 VALUES
                    (:title, :phase, :hazard_type, :content, :is_published)'
            );
            $stmt->bindValue(':title', $title);
            $stmt->bindValue(':phase', $phase);
            $stmt->bindValue(':hazard_type', $hazard);
            $stmt->bindValue(':content', $content);
            $stmt->bindValue(':is_published', $published, PDO::PARAM_BOOL);
            $stmt->execute();

            $_SESSION['flash_success'] = 'Safety information added successfully.';
            redirectPage();
        }

        if (isset($_POST['update_guide'])) {
            $id = $_POST['id'] ?? '';
            if (!validRecordId($id)) {
                throw new Exception('Invalid safety guide record ID.');
            }

            $title = postValue('title');
            $phase = postValue('phase');
            $hazard = postValue('hazard_type');
            $content = postValue('content');
            $published = isset($_POST['is_published']);

            if ($title === '' || $content === '') {
                throw new Exception('Please provide a title and safety instructions.');
            }
            if (!in_array($phase, ['Before', 'During', 'After'], true)) {
                throw new Exception('Invalid disaster phase.');
            }

            $stmt = $pdo->prepare(
                'UPDATE ' . $guidesTable . '
                 SET title = :title,
                     phase = :phase,
                     hazard_type = :hazard_type,
                     content = :content,
                     is_published = :is_published,
                     updated_at = NOW()
                 WHERE guide_id = :id'
            );
            $stmt->bindValue(':title', $title);
            $stmt->bindValue(':phase', $phase);
            $stmt->bindValue(':hazard_type', $hazard);
            $stmt->bindValue(':content', $content);
            $stmt->bindValue(':is_published', $published, PDO::PARAM_BOOL);
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['flash_success'] = 'Safety information updated successfully.';
            redirectPage();
        }

        if (isset($_POST['delete_guide'])) {
            $id = $_POST['id'] ?? '';
            if (!validRecordId($id)) {
                throw new Exception('Invalid safety guide record ID.');
            }

            $stmt = $pdo->prepare(
                'DELETE FROM ' . $guidesTable . ' WHERE guide_id = :id'
            );
            $stmt->execute([':id' => (int)$id]);

            $_SESSION['flash_success'] = 'Safety information deleted.';
            redirectPage();
        }
    } catch (PDOException $ex) {
        error_log('TaliResq safety-info database error: ' . $ex->getMessage());
        $error = 'Database error: ' . $ex->getMessage();
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

/* FLASH MESSAGE */
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

/* --------------------------------------------------------------------------
   FETCH HOTLINES AND SAFETY GUIDES
-------------------------------------------------------------------------- */
$contacts = [];
$guides = [];

try {
    $stmt = $pdo->query(
        'SELECT hotline_id, area_name, title, contact_number, description
         FROM ' . $hotlinesTable . '
         WHERE is_emergency = TRUE
         ORDER BY hotline_id ASC'
    );
    $contacts = $stmt->fetchAll();

    // IMPORTANT: concatenate the resolved table name outside the SQL string.
    $guideSql = "
        SELECT guide_id, title, phase, hazard_type, content,
               is_published, created_at, updated_at
        FROM " . $guidesTable . "
        ORDER BY
            CASE phase
                WHEN 'Before' THEN 1
                WHEN 'During' THEN 2
                WHEN 'After' THEN 3
                ELSE 4
            END,
            created_at DESC
    ";
    $stmt = $pdo->query($guideSql);
    $guides = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log('TaliResq safety-info fetch error: ' . $ex->getMessage());
    if ($error === '') {
        $error = 'Unable to load records. ' . $ex->getMessage();
    }
}

$groupedGuides = ['Before' => [], 'During' => [], 'After' => []];
foreach ($guides as $guide) {
    if (isset($groupedGuides[$guide['phase']])) {
        $groupedGuides[$guide['phase']][] = $guide;
    }
}

$phaseInfo = [
    'Before' => [
        'icon' => 'bi-shield-check',
        'description' => 'Prepare yourself, your family, and your home before a disaster happens.',
        'class' => 'before'
    ],
    'During' => [
        'icon' => 'bi-exclamation-triangle',
        'description' => 'Follow safety precautions and protect yourself while a disaster is happening.',
        'class' => 'during'
    ],
    'After' => [
        'icon' => 'bi-heart-pulse',
        'description' => 'Stay safe, assess your surroundings, and begin recovery after a disaster.',
        'class' => 'after'
    ]
];

$hazards = [
    'General', 'Typhoon', 'Flood', 'Earthquake', 'Landslide',
    'Volcanic Eruption', 'Ashfall', 'Fire', 'Other'
];

$publishedCount = count(array_filter($guides, function ($guide) {
    return isPublished($guide['is_published']);
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Hotlines &amp; Safety Information | TaliResq</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css?v=20261114i">
    <link rel="stylesheet" href="assets/safety-info.css">
    <link rel="icon" type="image/png" href="assets/log.png">
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main-content">
<div class="dashboard-wrapper">

    <div class="page-heading">
        <div>
            <span class="page-eyebrow">TALIRESQ MANAGEMENT</span>
            <h2 class="dashboard-title admin-page-title">EMERGENCY HOTLINES &amp; SAFETY INFORMATION</h2>
            <p class="page-subtitle">Manage emergency contacts and disaster preparedness information for the community.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="overview-card">
                <div class="overview-icon hotline-icon"><i class="bi bi-telephone-fill"></i></div>
                <div><span>Emergency Hotlines</span><h3><?= count($contacts) ?></h3></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="overview-card">
                <div class="overview-icon guide-icon"><i class="bi bi-journal-check"></i></div>
                <div><span>Safety Guides</span><h3><?= count($guides) ?></h3></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="overview-card">
                <div class="overview-icon published-icon"><i class="bi bi-broadcast"></i></div>
                <div><span>Published Guides</span><h3><?= $publishedCount ?></h3></div>
            </div>
        </div>
    </div>

    <!-- EMERGENCY HOTLINES -->
    <section class="content-section">
        <div class="section-heading">
            <div class="section-title-group">
                <div class="section-icon hotline-icon"><i class="bi bi-telephone-fill"></i></div>
                <div><h4>Emergency Hotlines</h4><p>Important contact numbers for emergency assistance.</p></div>
            </div>
            <button class="btn primary-action" data-bs-toggle="modal" data-bs-target="#addHotlineModal">
                <i class="bi bi-plus-lg me-1"></i>Add Hotline
            </button>
        </div>

        <div class="table-responsive">
            <table class="table hotline-table align-middle">
                <thead><tr><th>AGENCY</th><th>CONTACT NUMBER</th><th>DESCRIPTION</th><th class="text-center">ACTION</th></tr></thead>
                <tbody>
                <?php if ($contacts): ?>
                    <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td><div class="agency-name"><i class="bi bi-building me-2"></i><?= e($contact['title'] ?: $contact['area_name']) ?></div></td>
                        <td><span class="contact-number"><?= e($contact['contact_number']) ?></span></td>
                        <td><span class="description-text"><?= e($contact['description']) ?></span></td>
                        <td class="text-center">
                            <button class="btn btn-sm edit-action" data-bs-toggle="modal" data-bs-target="#editHotline<?= (int)$contact['hotline_id'] ?>" title="Edit"><i class="bi bi-pencil-square"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this emergency hotline?');">
                                <input type="hidden" name="id" value="<?= (int)$contact['hotline_id'] ?>">
                                <button type="submit" name="delete_hotline" class="btn btn-sm delete-action" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <div class="modal fade" id="editHotline<?= (int)$contact['hotline_id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                            <form method="POST">
                                <div class="modal-header"><h5 class="modal-title">Edit Emergency Hotline</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <input type="hidden" name="id" value="<?= (int)$contact['hotline_id'] ?>">
                                    <label>AGENCY</label>
                                    <input type="text" name="agency" class="form-control mb-3" value="<?= e($contact['title'] ?: $contact['area_name']) ?>" required>
                                    <label>CONTACT NUMBER</label>
                                    <input type="text" name="contact_number" class="form-control mb-3" value="<?= e($contact['contact_number']) ?>" required>
                                    <label>DESCRIPTION</label>
                                    <textarea name="description" class="form-control" rows="3" required><?= e($contact['description']) ?></textarea>
                                </div>
                                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" name="update_hotline" class="btn btn-primary">Save Changes</button></div>
                            </form>
                        </div></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-state"><i class="bi bi-telephone-x"></i><p>No emergency hotlines added yet.</p></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- DISASTER SAFETY INFORMATION -->
    <section class="content-section safety-section">
        <div class="section-heading">
            <div class="section-title-group">
                <div class="section-icon guide-icon"><i class="bi bi-book-half"></i></div>
                <div><h4>Disaster Safety Information</h4><p>Manage instructions and safety reminders for residents.</p></div>
            </div>
            <button class="btn primary-action" data-bs-toggle="modal" data-bs-target="#addGuideModal"><i class="bi bi-plus-lg me-1"></i>Add Safety Guide</button>
        </div>

        <div class="phase-navigation">
            <button class="phase-tab active" data-target="before-guides"><i class="bi bi-shield-check"></i>Before a Disaster <span><?= count($groupedGuides['Before']) ?></span></button>
            <button class="phase-tab" data-target="during-guides"><i class="bi bi-exclamation-triangle"></i>During a Disaster <span><?= count($groupedGuides['During']) ?></span></button>
            <button class="phase-tab" data-target="after-guides"><i class="bi bi-heart-pulse"></i>After a Disaster <span><?= count($groupedGuides['After']) ?></span></button>
        </div>

        <div class="guide-search mb-3">
            <i class="bi bi-search"></i>
            <input type="text" id="guideSearch" class="form-control" placeholder="Search safety guides or disaster types...">
        </div>

        <?php foreach ($phaseInfo as $phase => $info): ?>
            <?php $sectionId = strtolower($phase) . '-guides'; ?>
            <div class="guide-panel <?= $phase === 'Before' ? 'active' : '' ?>" id="<?= e($sectionId) ?>">
                <div class="phase-introduction <?= e($info['class']) ?>">
                    <div class="phase-intro-icon"><i class="bi <?= e($info['icon']) ?>"></i></div>
                    <div><h5><?= e(strtoupper($phase)) ?> A DISASTER</h5><p><?= e($info['description']) ?></p></div>
                </div>

                <div class="row g-3 guide-list">
                <?php if (!empty($groupedGuides[$phase])): ?>
                    <?php foreach ($groupedGuides[$phase] as $guide): ?>
                        <?php $guidePublished = isPublished($guide['is_published']); ?>
                        <div class="col-md-6 guide-item">
                            <div class="guide-card">
                                <div class="guide-card-header">
                                    <span class="hazard-badge"><i class="bi bi-tag-fill me-1"></i><?= e($guide['hazard_type']) ?></span>
                                    <span class="publish-status <?= $guidePublished ? 'published' : 'unpublished' ?>"><?= $guidePublished ? 'Published' : 'Unpublished' ?></span>
                                </div>
                                <h5><?= e($guide['title']) ?></h5>
                                <p class="guide-content"><?= nl2br(e($guide['content'])) ?></p>
                                <div class="guide-card-footer">
                                    <small><i class="bi bi-calendar3 me-1"></i><?= !empty($guide['created_at']) ? date('M d, Y', strtotime($guide['created_at'])) : '' ?></small>
                                    <div class="guide-actions">
                                        <button class="btn btn-sm edit-action" data-bs-toggle="modal" data-bs-target="#editGuide<?= (int)$guide['guide_id'] ?>"><i class="bi bi-pencil-square"></i>Edit</button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this safety guide?');">
                                            <input type="hidden" name="id" value="<?= (int)$guide['guide_id'] ?>">
                                            <button type="submit" name="delete_guide" class="btn btn-sm delete-action"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="editGuide<?= (int)$guide['guide_id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header"><h5 class="modal-title">Edit Safety Information</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?= (int)$guide['guide_id'] ?>">
                                        <label>GUIDE TITLE</label>
                                        <input type="text" name="title" class="form-control mb-3" value="<?= e($guide['title']) ?>" required>
                                        <div class="row">
                                            <div class="col-md-6 mb-3"><label>DISASTER PHASE</label>
                                                <select name="phase" class="form-select" required>
                                                    <option value="Before" <?= $guide['phase'] === 'Before' ? 'selected' : '' ?>>Before a Disaster</option>
                                                    <option value="During" <?= $guide['phase'] === 'During' ? 'selected' : '' ?>>During a Disaster</option>
                                                    <option value="After" <?= $guide['phase'] === 'After' ? 'selected' : '' ?>>After a Disaster</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3"><label>DISASTER TYPE</label>
                                                <select name="hazard_type" class="form-select" required>
                                                    <?php foreach ($hazards as $hazard): ?>
                                                        <option value="<?= e($hazard) ?>" <?= $guide['hazard_type'] === $hazard ? 'selected' : '' ?>><?= e($hazard) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <label>SAFETY INSTRUCTIONS</label>
                                        <textarea name="content" class="form-control mb-3" rows="6" required><?= e($guide['content']) ?></textarea>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="publish<?= (int)$guide['guide_id'] ?>" name="is_published" <?= $guidePublished ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="publish<?= (int)$guide['guide_id'] ?>">Publish this safety guide</label>
                                        </div>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" name="update_guide" class="btn btn-primary">Save Changes</button></div>
                                </form>
                            </div></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="empty-guide"><i class="bi bi-journal-plus"></i><h6>No safety information yet</h6><p>Add safety instructions for this disaster phase to help residents stay informed and prepared.</p></div></div>
                <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
</div>
</main>

<!-- ADD HOTLINE MODAL -->
<div class="modal fade" id="addHotlineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Add Emergency Hotline</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label>AGENCY</label><input type="text" name="agency" class="form-control mb-3" placeholder="e.g. MDRRMO Talisay" required>
                <label>CONTACT NUMBER</label><input type="text" name="contact_number" class="form-control mb-3" placeholder="e.g. 0939 912 6233" required>
                <label>DESCRIPTION</label><textarea name="description" class="form-control" rows="3" placeholder="Emergency response and assistance" required></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_hotline" class="btn btn-primary">Save Hotline</button></div>
        </form>
    </div></div>
</div>

<!-- ADD SAFETY GUIDE MODAL -->
<div class="modal fade" id="addGuideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Add Disaster Safety Information</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label>GUIDE TITLE</label><input type="text" name="title" class="form-control mb-3" placeholder="e.g. Preparing an Emergency Go-Bag" required>
                <div class="row">
                    <div class="col-md-6 mb-3"><label>DISASTER PHASE</label>
                        <select name="phase" class="form-select" required>
                            <option value="">Select phase</option><option value="Before">Before a Disaster</option><option value="During">During a Disaster</option><option value="After">After a Disaster</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3"><label>DISASTER TYPE</label>
                        <select name="hazard_type" class="form-select" required>
                            <?php foreach ($hazards as $hazard): ?><option value="<?= e($hazard) ?>"><?= e($hazard) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <label>SAFETY INSTRUCTIONS</label><textarea name="content" class="form-control mb-3" rows="7" placeholder="Enter the safety instructions and important reminders..." required></textarea>
                <div class="form-check"><input type="checkbox" class="form-check-input" id="newGuidePublished" name="is_published" checked><label class="form-check-label" for="newGuidePublished">Publish this safety guide immediately</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_guide" class="btn btn-primary">Save Safety Information</button></div>
        </form>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.phase-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.phase-tab').forEach(function (item) {
            item.classList.remove('active');
        });
        document.querySelectorAll('.guide-panel').forEach(function (panel) {
            panel.classList.remove('active');
        });
        this.classList.add('active');
        var targetPanel = document.getElementById(this.dataset.target);
        if (targetPanel) targetPanel.classList.add('active');
        var search = document.getElementById('guideSearch');
        search.value = '';
        filterGuides('');
    });
});

var searchInput = document.getElementById('guideSearch');
function filterGuides(search) {
    var activePanel = document.querySelector('.guide-panel.active');
    if (!activePanel) return;
    activePanel.querySelectorAll('.guide-item').forEach(function (item) {
        item.style.display = item.textContent.toLowerCase().includes(search.toLowerCase()) ? '' : 'none';
    });
}
searchInput.addEventListener('input', function () {
    filterGuides(this.value);
});
</script>
</body>
</html>
