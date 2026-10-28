
<?php
session_start();
require_once '../config/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($title === '' || $message === '') {
        $error = 'Please enter both the alert title and message.';
    } elseif (mb_strlen($title) > 150) {
        $error = 'The alert title must not exceed 150 characters.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO public.alerts (title, message)
                 VALUES (:title, :message)'
            );

            $stmt->execute([
                ':title' => $title,
                ':message' => $message
            ]);

            // Prevent the form from submitting twice on refresh.
            header('Location: alerts.php?success=1');
            exit;
        } catch (PDOException $e) {
            error_log('Create alert error: ' . $e->getMessage());
            $error = 'Unable to save the alert. Please try again.';
        }
    }
}

if (isset($_GET['success'])) {
    $success = 'Alert created successfully!';
}

try {
    $stmt = $pdo->query(
        'SELECT id, title, message, created_at, is_published
         FROM public.alerts
         ORDER BY created_at DESC'
    );

    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Load alerts error: ' . $e->getMessage());
    $alerts = [];
    $error = $error ?: 'Unable to load alerts from the database.';
}

function h($value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alert Management | TaliResQ</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">

    <style>
        .alert-form {
            display: grid;
            gap: 16px;
        }

        .alert-form label {
            font-weight: 600;
            margin-bottom: 6px;
        }

        .alert-form .form-control {
            border-radius: 10px;
            padding: 11px 13px;
        }

        .create-alert-btn {
            background: #052241;
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 11px 18px;
            font-weight: 600;
        }

        .create-alert-btn:hover {
            background: #0d4f8b;
            color: #fff;
        }

        .alert-message {
            min-width: 220px;
            max-width: 420px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .alert-table th {
            white-space: nowrap;
        }

        .alert-empty {
            padding: 32px !important;
            text-align: center;
            color: #777;
        }
    </style>
</head>

<body>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="dashboard-wrapper">

        <h2 class="dashboard-title">ALERTS</h2>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                <?= h($success) ?>
                <button type="button" class="btn-close"
                        data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= h($error) ?>
                <button type="button" class="btn-close"
                        data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="dash-card mb-4">
            <h3 class="card-title">
                <i class="bi bi-megaphone me-2"></i>
                Create New Alert
            </h3>

            <form method="POST" action="alerts.php" class="alert-form">
                <div>
                    <label for="title" class="form-label">
                        Alert Title
                    </label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        class="form-control"
                        maxlength="150"
                        placeholder="e.g. Flood Warning"
                        required
                        value="<?= h($_POST['title'] ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="message" class="form-label">
                        Alert Message
                    </label>
                    <textarea
                        id="message"
                        name="message"
                        class="form-control"
                        rows="4"
                        placeholder="Enter the emergency announcement or safety instructions..."
                        required
                    ><?= h($_POST['message'] ?? '') ?></textarea>
                </div>

                <div>
                    <button type="submit" class="btn create-alert-btn">
                        <i class="bi bi-send me-2"></i>
                        Publish Alert
                    </button>
                </div>
            </form>
        </div>

        <div class="dash-card">
            <h3 class="card-title">Alert History</h3>

            <div class="table-responsive">
                <table class="table table-hover align-middle alert-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Date Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($alerts)): ?>
                        <?php foreach ($alerts as $alert): ?>
                            <tr>
                                <td><?= h($alert['id']) ?></td>
                                <td><?= h($alert['title']) ?></td>
                                <td class="alert-message">
                                    <?= h($alert['message']) ?>
                                </td>
                                <td>
                                    <?= h(date(
                                        'M d, Y h:i A',
                                        strtotime($alert['created_at'])
                                    )) ?>
                                </td>
                                <td>
                                    <?php if ($alert['is_published']): ?>
                                        <span class="badge text-bg-success">
                                            Published
                                        </span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">
                                            Draft
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="alert-empty">
                                <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>
                                No alerts found. Create your first alert above.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
