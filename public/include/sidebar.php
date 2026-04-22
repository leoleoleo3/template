<?php
/**
 * Dynamic Sidebar - Uses RBAC System
 * Displays only pages the user has permission to access
 *
 * Required variables (should be set before including this file):
 * - $accessiblePages: Array of pages from PageManager::getMenuTree()
 * - $session: Session instance
 * - $userRole: User role information (optional)
 */

// Get user information
$userName = $session->get('name', $session->get('first_name', 'User'));
$userEmail = $session->get('email', '');
$roleName = isset($userRole) ? $userRole['display_name'] : 'User';

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Function to check if page or any child is active
function isPageOrChildActive($page, $currentPage) {
    // Check if this page is active
    if ($page['route'] === $currentPage) {
        return true;
    }

    // Check if any child is active
    if (!empty($page['children'])) {
        foreach ($page['children'] as $child) {
            if (isPageOrChildActive($child, $currentPage)) {
                return true;
            }
        }
    }

    return false;
}

// Function to render menu items recursively (supports unlimited nesting depth)
function renderMenuItem($page, $level = 0, $parentId = 'sidenavAccordion') {
    global $currentPage;

    $hasChildren = !empty($page['children']);
    $collapseId = 'collapse_' . $page['id'];
    $isActive = ($page['route'] === $currentPage);
    $hasActiveChild = isPageOrChildActive($page, $currentPage);

    if ($hasChildren) {
        // Parent item with children (can be nested at any level)
        // Keep expanded if has active child
        $collapseClass = $hasActiveChild ? '' : ' collapsed';
        $expandedAttr = $hasActiveChild ? 'true' : 'false';
        $showClass = $hasActiveChild ? ' show' : '';

        echo '<a class="nav-link' . $collapseClass . '" href="#" ';
        echo 'data-bs-toggle="collapse" ';
        echo 'data-bs-target="#' . $collapseId . '" ';
        echo 'aria-expanded="' . $expandedAttr . '" ';
        echo 'aria-controls="' . $collapseId . '">';
        echo '<div class="sb-nav-link-icon"><i class="' . htmlspecialchars($page['icon']) . '"></i></div>';
        echo htmlspecialchars($page['display_name']);
        echo '<div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>';
        echo '</a>';

        // Collapsible container for children
        // For nested levels (level > 0), don't use accordion parent to allow independent behavior
        echo '<div class="collapse' . $showClass . '" id="' . $collapseId . '"';
        if ($level === 0) {
            echo ' data-bs-parent="#' . $parentId . '"';
        }
        echo '>';
        echo '<nav class="sb-sidenav-menu-nested nav">';

        // Render children recursively (supports child of child of child...)
        foreach ($page['children'] as $child) {
            renderMenuItem($child, $level + 1, $collapseId);
        }

        echo '</nav></div>';
    } else {
        // Leaf item without children
        $route = $page['route'] ?? '#';
        $activeClass = $isActive ? ' active' : '';
        echo '<a class="nav-link' . $activeClass . '" href="' . htmlspecialchars($route) . '">';
        echo '<div class="sb-nav-link-icon"><i class="' . htmlspecialchars($page['icon']) . '"></i></div>';
        echo htmlspecialchars($page['display_name']);
        echo '</a>';
    }
}
?>
<div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <?php if (isset($accessiblePages) && !empty($accessiblePages)): ?>
                    <?php foreach ($accessiblePages as $page): ?>
                        <?php renderMenuItem($page); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback if no pages are accessible -->
                    <div class="sb-sidenav-menu-heading">No Pages Available</div>
                    <a class="nav-link" href="index.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-home"></i></div>
                        Home
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="sb-sidenav-footer">
            <div class="small">Logged in as:</div>
            <div class="text-truncate" title="<?= htmlspecialchars($userEmail) ?>">
                <?= htmlspecialchars($userName) ?>
            </div>
            <div class="small text-muted"><?= htmlspecialchars($roleName) ?></div>
        </div>
    </nav>
</div>

<script nonce="<?= csp_nonce() ?>">
// Remember menu state with localStorage
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidenavAccordion');
    if (!sidebar) return;

    // Save menu state when collapsed/expanded
    sidebar.addEventListener('show.bs.collapse', function(e) {
        const menuId = e.target.id;
        localStorage.setItem('sidebar_' + menuId, 'open');
    });

    sidebar.addEventListener('hide.bs.collapse', function(e) {
        const menuId = e.target.id;
        localStorage.setItem('sidebar_' + menuId, 'closed');
    });

    // Restore menu state from localStorage (manual preference overrides active-child expansion)
    document.querySelectorAll('.collapse').forEach(function(collapse) {
        const menuId = collapse.id;
        const savedState = localStorage.getItem('sidebar_' + menuId);

        if (savedState === 'open' && !collapse.classList.contains('show')) {
            new bootstrap.Collapse(collapse, { toggle: false }).show();
        } else if (savedState === 'closed' && collapse.classList.contains('show')) {
            new bootstrap.Collapse(collapse, { toggle: false }).hide();
        }
    });
});
</script>
