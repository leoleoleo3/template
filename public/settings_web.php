<?php
/**
 * Web Settings — Website
 * Manages site branding: name, tagline, color, logo, and favicon.
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once 'include/rbac_init.php';
require_once __DIR__ . '/../core/SettingsManager.php';
require_once __DIR__ . '/../core/FileUploadManager.php';

$settingsManager   = SettingsManager::getInstance($db);
$fileUploadManager = FileUploadManager::getInstance();

// ── AJAX handler ───────────────────────────────────────────────────────────────
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']);
if ($isAjax) {
    // ob_start captures stray warnings before we flush clean JSON
    ob_start();
    startAjax(fn() => hasPagePermission('settings_security', 'view'));
    ob_end_clean();

    $action = $_POST['action'];

    switch ($action) {
        case 'get_settings':
            ob_end_clean();
            $settings = $settingsManager->getWebSettings();
            echo json_encode(['success' => true, 'settings' => $settings]);
            exit;

        case 'update_settings':
            if (!hasPagePermission('settings_security', 'edit')) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }

            $updateData = [];

            // Text settings
            if (isset($_POST['site_name'])) {
                $updateData['site_name'] = trim($_POST['site_name']);
            }
            if (isset($_POST['site_tagline'])) {
                $updateData['site_tagline'] = trim($_POST['site_tagline']);
            }
            if (isset($_POST['primary_color'])) {
                $updateData['primary_color'] = trim($_POST['primary_color']);
            }
            if (isset($_POST['footer_text'])) {
                $updateData['footer_text'] = trim($_POST['footer_text']);
            }

            // Appearance: chrome colors, login hero, dark mode
            $_hexRegex = '/^#[0-9A-Fa-f]{6}$/';
            if (isset($_POST['sidenav_color'])) {
                $v = trim($_POST['sidenav_color']);
                if (preg_match($_hexRegex, $v)) { $updateData['sidenav_color'] = $v; }
            }
            if (isset($_POST['topbar_color'])) {
                $v = trim($_POST['topbar_color']);
                if (preg_match($_hexRegex, $v)) { $updateData['topbar_color'] = $v; }
            }
            if (isset($_POST['login_hero_color_start'])) {
                $v = trim($_POST['login_hero_color_start']);
                if ($v === '' || preg_match($_hexRegex, $v)) { $updateData['login_hero_color_start'] = $v; }
            }
            if (isset($_POST['login_hero_color_end'])) {
                $v = trim($_POST['login_hero_color_end']);
                if ($v === '' || preg_match($_hexRegex, $v)) { $updateData['login_hero_color_end'] = $v; }
            }
            // Checkboxes: presence == true, absence == false. Only apply when the branding form is submitted (site_name is the canonical flag).
            if (isset($_POST['site_name'])) {
                $updateData['login_hero_enabled'] = !empty($_POST['login_hero_enabled']) ? '1' : '0';
                $updateData['dark_mode_enabled']  = !empty($_POST['dark_mode_enabled'])  ? '1' : '0';
            }

            $oldSettings = $settingsManager->getWebSettings();
            $result = $settingsManager->updateWebSettings($updateData);

            if ($result['success']) {
                $newSettings = $settingsManager->getWebSettings();
                $permissionManager->logPermissionChange(
                    $session->getUserId(),
                    'edit',
                    'web_settings',
                    0,
                    $oldSettings,
                    $newSettings
                );
            }

            ob_end_clean();
            echo json_encode($result);
            exit;

        case 'upload_logo':
            if (!hasPagePermission('settings_security', 'edit')) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }

            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No file uploaded']);
                exit;
            }

            // Upload with security validation
            $result = $fileUploadManager->uploadFile(
                $_FILES['logo'],
                'image',
                'branding',
                ['custom_name' => 'logo', 'max_size' => 2097152] // 2MB max
            );

            if ($result['success']) {
                // Delete old logo if exists
                $oldLogo = $settingsManager->get('site_logo');
                if ($oldLogo && $fileUploadManager->fileExists($oldLogo)) {
                    $fileUploadManager->deleteFile($oldLogo);
                }

                // Save new logo path
                $settingsManager->set('site_logo', $result['path'], 'string', 'web', 'Site Logo');

                $permissionManager->logPermissionChange(
                    $session->getUserId(),
                    'upload',
                    'site_logo',
                    0,
                    ['old_logo' => $oldLogo],
                    ['new_logo' => $result['path']]
                );

                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'path' => $result['path'],
                    'url' => $fileUploadManager->getFileUrl($result['path'])
                ]);
            } else {
                ob_end_clean();
                echo json_encode($result);
            }
            exit;

        case 'upload_favicon':
            if (!hasPagePermission('settings_security', 'edit')) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }

            if (!isset($_FILES['favicon']) || $_FILES['favicon']['error'] === UPLOAD_ERR_NO_FILE) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No file uploaded']);
                exit;
            }

            // Upload with security validation
            $result = $fileUploadManager->uploadFile(
                $_FILES['favicon'],
                'image',
                'branding',
                ['custom_name' => 'favicon', 'max_size' => 1048576] // 1MB max
            );

            if ($result['success']) {
                // Delete old favicon if exists
                $oldFavicon = $settingsManager->get('site_favicon');
                if ($oldFavicon && $fileUploadManager->fileExists($oldFavicon)) {
                    $fileUploadManager->deleteFile($oldFavicon);
                }

                // Save new favicon path
                $settingsManager->set('site_favicon', $result['path'], 'string', 'web', 'Site Favicon');

                $permissionManager->logPermissionChange(
                    $session->getUserId(),
                    'upload',
                    'site_favicon',
                    0,
                    ['old_favicon' => $oldFavicon],
                    ['new_favicon' => $result['path']]
                );

                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'path' => $result['path'],
                    'url' => $fileUploadManager->getFileUrl($result['path'])
                ]);
            } else {
                ob_end_clean();
                echo json_encode($result);
            }
            exit;

        case 'upload_logo_dark':
            if (!hasPagePermission('settings_security', 'edit')) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }

            if (!isset($_FILES['logo_dark']) || $_FILES['logo_dark']['error'] === UPLOAD_ERR_NO_FILE) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No file uploaded']);
                exit;
            }

            $result = $fileUploadManager->uploadFile(
                $_FILES['logo_dark'],
                'image',
                'branding',
                ['custom_name' => 'logo_dark', 'max_size' => 2097152]
            );

            if ($result['success']) {
                $oldLogo = $settingsManager->get('site_logo_dark');
                if ($oldLogo && $fileUploadManager->fileExists($oldLogo)) {
                    $fileUploadManager->deleteFile($oldLogo);
                }

                $settingsManager->set('site_logo_dark', $result['path'], 'string', 'web', 'Dark Mode Logo');

                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'path' => $result['path'],
                    'url' => $fileUploadManager->getFileUrl($result['path'])
                ]);
            } else {
                ob_end_clean();
                echo json_encode($result);
            }
            exit;

        case 'remove_logo':
            if (!hasPagePermission('settings_security', 'edit')) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }

            $logoType = $_POST['logo_type'] ?? 'site_logo';
            $allowedTypes = ['site_logo', 'site_favicon', 'site_logo_dark'];

            if (!in_array($logoType, $allowedTypes)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Invalid logo type']);
                exit;
            }

            $currentPath = $settingsManager->get($logoType);
            if ($currentPath && $fileUploadManager->fileExists($currentPath)) {
                $fileUploadManager->deleteFile($currentPath);
            }

            $settingsManager->set($logoType, '', 'string', 'web');

            ob_end_clean();
            echo json_encode(['success' => true]);
            exit;

        default:
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// ── View ───────────────────────────────────────────────────────────────────────
requirePagePermission('settings_security', 'view');

$pageTitle = 'Web Settings';

// Get current settings
$webSettings = $settingsManager->getWebSettings();

// Check permissions
$canEdit = hasPagePermission('settings_security', 'edit');

// Page content
ob_start();
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="fas fa-globe"></i> Web Settings</h4>
            <p class="text-muted mb-0">Customize your site branding, logo, and appearance</p>
        </div>
    </div>

    <div class="row">
        <!-- Branding Settings -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-palette"></i> Site Branding
                </div>
                <div class="card-body">
                    <form id="brandingForm">
                        <input type="hidden" name="csrf_token" value="<?= $session->getCSRFToken() ?>">
                        <input type="hidden" name="action" value="update_settings">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Site Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="site_name" id="siteName"
                                       value="<?= htmlspecialchars($webSettings['site_name']) ?>" required
                                       <?= !$canEdit ? 'disabled' : '' ?>>
                                <small class="text-muted">Displayed in the header and browser title</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Site Tagline</label>
                                <input type="text" class="form-control" name="site_tagline" id="siteTagline"
                                       value="<?= htmlspecialchars($webSettings['site_tagline']) ?>"
                                       <?= !$canEdit ? 'disabled' : '' ?>>
                                <small class="text-muted">Optional subtitle or description</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Primary Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" name="primary_color_picker"
                                           id="primaryColorPicker" value="<?= htmlspecialchars($webSettings['primary_color']) ?>"
                                           <?= !$canEdit ? 'disabled' : '' ?>>
                                    <input type="text" class="form-control" name="primary_color" id="primaryColor"
                                           value="<?= htmlspecialchars($webSettings['primary_color']) ?>"
                                           pattern="^#[0-9A-Fa-f]{6}$" <?= !$canEdit ? 'disabled' : '' ?>>
                                </div>
                                <small class="text-muted">Brand color used throughout the site</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Footer Text</label>
                                <input type="text" class="form-control" name="footer_text" id="footerText"
                                       value="<?= htmlspecialchars($webSettings['footer_text']) ?>"
                                       <?= !$canEdit ? 'disabled' : '' ?>>
                                <small class="text-muted">Custom text displayed in the footer</small>
                            </div>
                        </div>

                        <!-- ─── Appearance: Chrome Colors ─── -->
                        <hr class="my-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-swatchbook text-primary me-2"></i>
                            <h6 class="mb-0">Chrome Colors</h6>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sidebar Background</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" name="sidenav_color_picker"
                                           id="sidenavColorPicker" value="<?= htmlspecialchars($webSettings['sidenav_color']) ?>"
                                           <?= !$canEdit ? 'disabled' : '' ?>>
                                    <input type="text" class="form-control" name="sidenav_color" id="sidenavColor"
                                           value="<?= htmlspecialchars($webSettings['sidenav_color']) ?>"
                                           pattern="^#[0-9A-Fa-f]{6}$" <?= !$canEdit ? 'disabled' : '' ?>>
                                </div>
                                <small class="text-muted">Background color for the left sidebar</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Topbar Background</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" name="topbar_color_picker"
                                           id="topbarColorPicker" value="<?= htmlspecialchars($webSettings['topbar_color']) ?>"
                                           <?= !$canEdit ? 'disabled' : '' ?>>
                                    <input type="text" class="form-control" name="topbar_color" id="topbarColor"
                                           value="<?= htmlspecialchars($webSettings['topbar_color']) ?>"
                                           pattern="^#[0-9A-Fa-f]{6}$" <?= !$canEdit ? 'disabled' : '' ?>>
                                </div>
                                <small class="text-muted">Background color for the top navigation bar</small>
                            </div>
                        </div>

                        <!-- ─── Appearance: Login Hero ─── -->
                        <hr class="my-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-door-open text-primary me-2"></i>
                                <h6 class="mb-0">Login Hero</h6>
                            </div>
                            <a href="login.php" target="_blank" rel="noopener" class="small">
                                <i class="fas fa-external-link-alt me-1"></i> Preview login
                            </a>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="loginHeroEnabled" name="login_hero_enabled" value="1"
                                           <?= !empty($webSettings['login_hero_enabled']) ? 'checked' : '' ?>
                                           <?= !$canEdit ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="loginHeroEnabled">
                                        Show gradient hero behind the login card
                                    </label>
                                </div>
                                <small class="text-muted">Turn off for a flat background on login/register pages.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gradient Start</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" name="login_hero_color_start_picker"
                                           id="loginHeroStartPicker"
                                           value="<?= htmlspecialchars($webSettings['login_hero_color_start'] ?: $webSettings['primary_color']) ?>"
                                           <?= !$canEdit ? 'disabled' : '' ?>>
                                    <input type="text" class="form-control" name="login_hero_color_start" id="loginHeroStart"
                                           value="<?= htmlspecialchars($webSettings['login_hero_color_start']) ?>"
                                           placeholder="Defaults to primary color"
                                           pattern="^(|#[0-9A-Fa-f]{6})$" <?= !$canEdit ? 'disabled' : '' ?>>
                                </div>
                                <small class="text-muted">Leave blank to use the primary color.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gradient End</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" name="login_hero_color_end_picker"
                                           id="loginHeroEndPicker"
                                           value="<?= htmlspecialchars($webSettings['login_hero_color_end'] ?: '#0a58ca') ?>"
                                           <?= !$canEdit ? 'disabled' : '' ?>>
                                    <input type="text" class="form-control" name="login_hero_color_end" id="loginHeroEnd"
                                           value="<?= htmlspecialchars($webSettings['login_hero_color_end']) ?>"
                                           placeholder="Defaults to darker primary"
                                           pattern="^(|#[0-9A-Fa-f]{6})$" <?= !$canEdit ? 'disabled' : '' ?>>
                                </div>
                                <small class="text-muted">Leave blank to auto-darken the primary color.</small>
                            </div>
                        </div>

                        <!-- ─── Appearance: Dark Mode ─── -->
                        <hr class="my-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-moon text-primary me-2"></i>
                            <h6 class="mb-0">Dark Mode</h6>
                        </div>
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="darkModeEnabled" name="dark_mode_enabled" value="1"
                                   <?= !empty($webSettings['dark_mode_enabled']) ? 'checked' : '' ?>
                                   <?= !$canEdit ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="darkModeEnabled">
                                Enable dark mode by default for all users
                            </label>
                        </div>
                        <small class="text-muted d-block mb-3">
                            Sets the tenant default. Individual users can still override the theme from the topbar toggle,
                            and their choice is remembered per device.
                        </small>

                        <?php if ($canEdit): ?>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary" id="saveBrandingBtn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Logo Upload -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-image"></i> Site Logo
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Main Logo</label>
                            <div class="border rounded p-3 text-center bg-light" style="min-height: 120px;">
                                <?php if ($webSettings['site_logo']): ?>
                                <img src="/uploads/<?= htmlspecialchars($webSettings['site_logo']) ?>" alt="Site Logo"
                                     id="logoPreview" class="img-fluid" style="max-height: 100px;">
                                <?php else: ?>
                                <div id="logoPreview" class="text-muted py-4">
                                    <i class="fas fa-image fa-3x mb-2"></i>
                                    <p class="mb-0">No logo uploaded</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($canEdit): ?>
                            <div class="mt-2">
                                <input type="file" class="form-control form-control-sm" id="logoFile" accept="image/*">
                                <small class="text-muted">Recommended: PNG or SVG, max 2MB, 200x50px</small>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-primary" data-action="uploadLogo" data-arg0="logo">
                                    <i class="fas fa-upload"></i> Upload
                                </button>
                                <?php if ($webSettings['site_logo']): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-action="removeLogo" data-arg0="site_logo">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label class="form-label">Dark Mode Logo <span class="badge bg-secondary">Optional</span></label>
                            <div class="border rounded p-3 text-center bg-dark" style="min-height: 120px;">
                                <?php if ($webSettings['site_logo_dark']): ?>
                                <img src="/uploads/<?= htmlspecialchars($webSettings['site_logo_dark']) ?>" alt="Dark Logo"
                                     id="logoDarkPreview" class="img-fluid" style="max-height: 100px;">
                                <?php else: ?>
                                <div id="logoDarkPreview" class="text-white-50 py-4">
                                    <i class="fas fa-image fa-3x mb-2"></i>
                                    <p class="mb-0">No dark logo uploaded</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($canEdit): ?>
                            <div class="mt-2">
                                <input type="file" class="form-control form-control-sm" id="logoDarkFile" accept="image/*">
                                <small class="text-muted">Used when dark mode is active</small>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-primary" data-action="uploadLogo" data-arg0="logo_dark">
                                    <i class="fas fa-upload"></i> Upload
                                </button>
                                <?php if ($webSettings['site_logo_dark']): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-action="removeLogo" data-arg0="site_logo_dark">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Favicon Upload -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-star"></i> Favicon
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <div class="border rounded p-3 bg-light d-inline-block">
                                <?php if ($webSettings['site_favicon']): ?>
                                <img src="/uploads/<?= htmlspecialchars($webSettings['site_favicon']) ?>" alt="Favicon"
                                     id="faviconPreview" style="width: 48px; height: 48px;">
                                <?php else: ?>
                                <div id="faviconPreview" class="text-muted" style="width: 48px; height: 48px; line-height: 48px;">
                                    <i class="fas fa-image fa-2x"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <p class="mb-2">The favicon appears in browser tabs, bookmarks, and mobile home screens.</p>
                            <?php if ($canEdit): ?>
                            <div class="input-group">
                                <input type="file" class="form-control" id="faviconFile" accept=".ico,.png,.svg,image/x-icon,image/png,image/svg+xml">
                                <button type="button" class="btn btn-primary" data-action="uploadLogo" data-arg0="favicon">
                                    <i class="fas fa-upload"></i> Upload
                                </button>
                                <?php if ($webSettings['site_favicon']): ?>
                                <button type="button" class="btn btn-outline-danger" data-action="removeLogo" data-arg0="site_favicon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Recommended: ICO or PNG, 32x32 or 64x64 pixels, max 1MB</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Panel -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 80px;">
                <div class="card-header">
                    <i class="fas fa-eye"></i> Preview
                </div>
                <div class="card-body">
                    <!-- Browser Tab Preview -->
                    <div class="mb-4">
                        <label class="form-label text-muted small">Browser Tab</label>
                        <div class="border rounded p-2 bg-light">
                            <div class="d-flex align-items-center">
                                <div class="me-2" style="width: 16px; height: 16px;">
                                    <?php if ($webSettings['site_favicon']): ?>
                                    <img src="/uploads/<?= htmlspecialchars($webSettings['site_favicon']) ?>"
                                         id="tabFaviconPreview" style="width: 16px; height: 16px;">
                                    <?php else: ?>
                                    <i class="fas fa-globe text-muted" id="tabFaviconPreview" style="font-size: 14px;"></i>
                                    <?php endif; ?>
                                </div>
                                <span class="small text-truncate" id="tabTitlePreview">
                                    <?= htmlspecialchars($webSettings['site_name']) ?> | Dashboard
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Header Preview -->
                    <div class="mb-4">
                        <label class="form-label text-muted small">Header Preview</label>
                        <div class="border rounded p-3" id="headerPreview" style="background: linear-gradient(135deg, var(--preview-color, #0d6efd) 0%, var(--preview-color-dark, #0a58ca) 100%);">
                            <div class="d-flex align-items-center text-white">
                                <?php if ($webSettings['site_logo']): ?>
                                <img src="/uploads/<?= htmlspecialchars($webSettings['site_logo']) ?>"
                                     id="headerLogoPreview" style="max-height: 40px;" class="me-2">
                                <?php else: ?>
                                <i class="fas fa-graduation-cap fa-2x me-2" id="headerLogoPreview"></i>
                                <?php endif; ?>
                                <div>
                                    <strong id="headerNamePreview"><?= htmlspecialchars($webSettings['site_name']) ?></strong>
                                    <br>
                                    <small id="headerTaglinePreview"><?= htmlspecialchars($webSettings['site_tagline']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Color Swatch -->
                    <div>
                        <label class="form-label text-muted small">Color Palette</label>
                        <div class="d-flex gap-2">
                            <div class="rounded" id="colorSwatch1" style="width: 50px; height: 50px; background: <?= htmlspecialchars($webSettings['primary_color']) ?>;"></div>
                            <div class="rounded" id="colorSwatch2" style="width: 50px; height: 50px; background: <?= htmlspecialchars($webSettings['primary_color']) ?>; opacity: 0.7;"></div>
                            <div class="rounded" id="colorSwatch3" style="width: 50px; height: 50px; background: <?= htmlspecialchars($webSettings['primary_color']) ?>; opacity: 0.4;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

// Page scripts
ob_start();
?>
<script nonce="<?= csp_nonce() ?>">
const csrfToken = '<?= $session->getCSRFToken() ?>';

// Branding form submission
document.getElementById('brandingForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = document.getElementById('saveBrandingBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('settings_web.php', { method: 'POST', body: new FormData(this) })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Notify.success('Settings saved successfully');
            updatePreview();
        } else {
            Notify.error(data.error || 'Failed to save settings');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    })
    .catch(err => {
        Notify.error('Error saving settings');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    });
});

// Color picker sync
document.getElementById('primaryColorPicker').addEventListener('input', function() {
    document.getElementById('primaryColor').value = this.value;
    updateColorPreview(this.value);
});

document.getElementById('primaryColor').addEventListener('input', function() {
    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
        document.getElementById('primaryColorPicker').value = this.value;
        updateColorPreview(this.value);
    }
});

// Generic sync helper for paired color-picker + text inputs (chrome + login hero)
(function () {
    function pair(pickerId, textId, allowEmpty) {
        var picker = document.getElementById(pickerId);
        var text = document.getElementById(textId);
        if (!picker || !text) return;
        picker.addEventListener('input', function () { text.value = this.value; });
        text.addEventListener('input', function () {
            var v = this.value.trim();
            if (allowEmpty && v === '') return;
            if (/^#[0-9A-Fa-f]{6}$/.test(v)) picker.value = v;
        });
    }
    pair('sidenavColorPicker',   'sidenavColor',   false);
    pair('topbarColorPicker',    'topbarColor',    false);
    pair('loginHeroStartPicker', 'loginHeroStart', true);
    pair('loginHeroEndPicker',   'loginHeroEnd',   true);
})();

function updateColorPreview(color) {
    document.getElementById('colorSwatch1').style.background = color;
    document.getElementById('colorSwatch2').style.background = color;
    document.getElementById('colorSwatch3').style.background = color;
    document.getElementById('headerPreview').style.setProperty('--preview-color', color);

    // Calculate darker shade
    const darker = adjustBrightness(color, -20);
    document.getElementById('headerPreview').style.setProperty('--preview-color-dark', darker);
}

function adjustBrightness(hex, percent) {
    const num = parseInt(hex.slice(1), 16);
    const r = Math.max(0, Math.min(255, (num >> 16) + percent));
    const g = Math.max(0, Math.min(255, ((num >> 8) & 0x00FF) + percent));
    const b = Math.max(0, Math.min(255, (num & 0x0000FF) + percent));
    return '#' + (1 << 24 | r << 16 | g << 8 | b).toString(16).slice(1);
}

// Live preview updates
document.getElementById('siteName').addEventListener('input', function() {
    document.getElementById('headerNamePreview').textContent = this.value || 'Site Name';
    document.getElementById('tabTitlePreview').textContent = (this.value || 'Site') + ' | Dashboard';
});

document.getElementById('siteTagline').addEventListener('input', function() {
    document.getElementById('headerTaglinePreview').textContent = this.value;
});

// Upload logo
function uploadLogo(type) {
    let fileInput, action, previewId;

    switch (type) {
        case 'logo':
            fileInput = document.getElementById('logoFile');
            action = 'upload_logo';
            previewId = 'logoPreview';
            break;
        case 'logo_dark':
            fileInput = document.getElementById('logoDarkFile');
            action = 'upload_logo_dark';
            previewId = 'logoDarkPreview';
            break;
        case 'favicon':
            fileInput = document.getElementById('faviconFile');
            action = 'upload_favicon';
            previewId = 'faviconPreview';
            break;
    }

    if (!fileInput.files || !fileInput.files[0]) {
        Notify.warning('Please select a file first');
        return;
    }

    const formData = new FormData();
    formData.append('action', action);
    formData.append('csrf_token', csrfToken);

    if (type === 'logo') {
        formData.append('logo', fileInput.files[0]);
    } else if (type === 'logo_dark') {
        formData.append('logo_dark', fileInput.files[0]);
    } else {
        formData.append('favicon', fileInput.files[0]);
    }

    Notify.info('Uploading...');

    fetch('settings_web.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Notify.success('File uploaded successfully');
            // Update preview
            const preview = document.getElementById(previewId);
            if (type === 'favicon') {
                preview.outerHTML = `<img src="${data.url}?t=${Date.now()}" id="${previewId}" style="width: 48px; height: 48px;">`;
                // Update tab preview
                const tabPreview = document.getElementById('tabFaviconPreview');
                tabPreview.outerHTML = `<img src="${data.url}?t=${Date.now()}" id="tabFaviconPreview" style="width: 16px; height: 16px;">`;
            } else {
                preview.outerHTML = `<img src="${data.url}?t=${Date.now()}" id="${previewId}" class="img-fluid" style="max-height: 100px;">`;
                if (type === 'logo') {
                    // Update header preview
                    const headerPreview = document.getElementById('headerLogoPreview');
                    headerPreview.outerHTML = `<img src="${data.url}?t=${Date.now()}" id="headerLogoPreview" style="max-height: 40px;" class="me-2">`;
                }
            }
            // Clear file input
            fileInput.value = '';
            // Reload to update remove buttons
            setTimeout(() => location.reload(), 1000);
        } else {
            Notify.error(data.error || 'Upload failed');
        }
    })
    .catch(err => {
        Notify.error('Upload error: ' + err.message);
    });
}

// Remove logo
function removeLogo(logoType) {
    if (!confirm('Are you sure you want to remove this image?')) return;

    const formData = new FormData();
    formData.append('action', 'remove_logo');
    formData.append('logo_type', logoType);
    formData.append('csrf_token', csrfToken);

    fetch('settings_web.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Notify.success('Image removed');
            location.reload();
        } else {
            Notify.error(data.error || 'Failed to remove image');
        }
    });
}

function updatePreview() {
    const name = document.getElementById('siteName').value;
    const tagline = document.getElementById('siteTagline').value;
    const color = document.getElementById('primaryColor').value;

    document.getElementById('headerNamePreview').textContent = name || 'Site Name';
    document.getElementById('headerTaglinePreview').textContent = tagline;
    document.getElementById('tabTitlePreview').textContent = (name || 'Site') + ' | Dashboard';
    updateColorPreview(color);
}

// Initialize
updatePreview();
</script>
<?php
$pageScripts = ob_get_clean();

// Render layout
include 'include/layout.php';
