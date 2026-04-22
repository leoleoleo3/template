<?php
/**
 * Authentication Footer Include
 * Shared footer for all authentication pages (login, register, forgot password)
 */

// Get web settings (loaded by layout.php via web_settings.php)
global $siteName, $footerText;
$currentYear = date('Y');
$displayFooterText = !empty($footerText) ? $footerText : 'Copyright &copy; ' . htmlspecialchars($siteName ?? 'Template') . ' ' . $currentYear;
?>
            <div id="layoutAuthentication_footer">
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted"><?= $displayFooterText ?></div>
                            <div>
                                <a href="#">Privacy Policy</a>
                                &middot;
                                <a href="#">Terms &amp; Conditions</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script nonce="<?= csp_nonce() ?>" src="js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script nonce="<?= csp_nonce() ?>" src="js/scripts.js"></script>
    </body>
</html>
