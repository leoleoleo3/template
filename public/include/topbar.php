<?php
// Get user info for display
$topbarUserName = $session->get('name', $session->get('first_name', 'User'));
$topbarUserEmail = $session->get('email', '');

// Get web settings (loaded by layout.php via web_settings.php)
global $siteName, $siteLogoUrl, $primaryColor;
?>
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <!-- Navbar Brand-->
    <a class="navbar-brand ps-3" href="index.php" style="color: <?= htmlspecialchars($primaryColor ?? '#0d6efd') ?>;">
        <img src="<?= htmlspecialchars($siteLogoUrl) ?>" alt="<?= htmlspecialchars($siteName ?? 'Template') ?>" style="max-height: 35px; vertical-align: middle;">
        <?= htmlspecialchars($siteName ?? 'Template') ?>
    </a>
    <!-- Sidebar Toggle-->
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>

    <!-- Spacer -->
    <div class="ms-auto"></div>

    <!-- User Dropdown -->
    <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user fa-fw"></i>
                <span class="d-none d-md-inline ms-1"><?= htmlspecialchars($topbarUserName) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                <li class="dropdown-header">
                    <strong><?= htmlspecialchars($topbarUserName) ?></strong>
                    <?php if ($topbarUserEmail): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($topbarUserEmail) ?></small>
                    <?php endif; ?>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </li>
    </ul>
</nav>