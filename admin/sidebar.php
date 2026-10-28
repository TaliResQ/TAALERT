<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="logo-container d-flex align-items-center justify-content-center gap-2">
        <img src="assets/logo1.png" alt="TAALERT Logo"
            style="height: 25px; width: auto; object-fit: contain;">
        <h2 class="top-title">TaliResQ</h2>
    </div>

    <ul class="nav-menu">

        <li class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php" class="nav-link">
                <i class="bi bi-display"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'alerts.php') ? 'active' : ''; ?>">
            <a href="alerts.php" class="nav-link">
                <i class="bi bi-bell"></i>
                <span>Alerts</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'emergency-contact.php') ? 'active' : ''; ?>">
            <a href="emergency-contact.php" class="nav-link">
                <i class="bi bi-person-lines-fill"></i>
                <span>Emergency Contacts</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'safety-info.php') ? 'active' : ''; ?>">
            <a href="safety-info.php" class="nav-link">
                <i class="bi bi-shield-check"></i>
                <span>Safety Info</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'map.php') ? 'active' : ''; ?>">
            <a href="map.php" class="nav-link">
                <i class="bi bi-map"></i>
                <span>Map Monitoring</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'records.php') ? 'active' : ''; ?>">
            <a href="records.php" class="nav-link">
                <i class="bi bi-journal-text"></i>
                <span>Records</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($current_page == 'daily-report.php') ? 'active' : ''; ?>">
            <a href="daily-report.php" class="nav-link">
                <i class="bi bi-journal-text"></i>
                <span>Report</span>
            </a>
        </li>




<li class="nav-item <?php echo ($current_page == 'recovery-requests.php' || $current_page == 'recovery-assistance.php') ? 'active' : ''; ?>">
    <a href="recovery-requests.php" class="nav-link">
        <i class="bi bi-life-preserver"></i>
        <span>Recovery Assistance</span>
    </a>
</li>


    </ul>

    <ul class="bottom-nav">

        <li class="nav-item <?php echo ($current_page == 'edit_account.php') ? 'active' : ''; ?>">
            <a href="edit_account.php" class="nav-link">
                <i class="bi bi-person-gear"></i>
                <span>Edit Account</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right"></i>
                <span>Log Out</span>
            </a>
        </li>

    </ul>
</aside>