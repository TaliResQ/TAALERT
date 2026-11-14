<?php
$current_page = isset($current_page) && is_string($current_page)
    ? basename($current_page)
    : basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar <?php echo ($current_page == 'index.php') ? 'sidebar-dashboard' : ''; ?>" aria-label="Admin navigation">
    <div class="logo-container d-flex align-items-center justify-content-center gap-2">
        <img class="sidebar-logo" src="assets/logo1.png" alt="TAALERT Logo">
        <div class="sidebar-brand-copy">
            <h2 class="top-title"><span class="brand-name-primary">Tali</span><span class="brand-name-accent">ResQ</span></h2>
            <span class="sidebar-brand-caption">Admin portal</span>
        </div>
    </div>

    <ul class="nav-menu" aria-label="Main menu">
        <li class="nav-section-label">Workspace</li>

        <li class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php" class="nav-link" title="Dashboard" <?php echo ($current_page == 'index.php') ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'user-verification.php') ? 'active' : ''; ?>">
            <a href="user-verification.php" class="nav-link" title="User Verification" <?php echo ($current_page == 'user-verification.php') ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-person-check"></i>
                <span>User Verification</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'alerts.php') ? 'active' : ''; ?>">
            <a href="alerts.php" class="nav-link" title="Alerts" <?php echo ($current_page == 'alerts.php') ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-bell-fill"></i>
                <span>Alerts</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'safety-info.php') ? 'active' : ''; ?>">
            <a href="safety-info.php" class="nav-link" title="Safety Information" <?php echo ($current_page == 'safety-info.php') ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-shield-check"></i>
                <span>Safety Information</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'map.php') ? 'active' : ''; ?>">
            <a href="map.php" class="nav-link" title="Map Monitoring" <?php echo ($current_page == 'map.php') ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-map"></i>
                <span>Map Monitoring</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'records.php') ? 'active' : ''; ?>">
            <a href="records.php" class="nav-link" title="Records" <?php echo ($current_page == 'records.php') ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-journal-text"></i>
                <span>Records</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'daily-report.php') ? 'active' : ''; ?>">
            <a href="daily-report.php" class="nav-link" title="Reports" <?php echo ($current_page == 'daily-report.php') ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-graph-up-arrow"></i>
                <span>Report</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'recovery-requests.php' || $current_page == 'recovery-assistance.php') ? 'active' : ''; ?>">
            <a href="recovery-requests.php" class="nav-link" title="Recovery Assistance" <?php echo ($current_page == 'recovery-requests.php' || $current_page == 'recovery-assistance.php') ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-life-preserver"></i>
                <span>Recovery Assistance</span>
            </a>
        </li>


    </ul>

</aside>