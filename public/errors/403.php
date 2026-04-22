<?php
/**
 * 403 Forbidden Error Page
 */
http_response_code(403);
$errorCode = 403;
$errorTitle = 'Forbidden';
$errorMessage = "You don't have permission to view this page. Please contact the administrator if you believe this is an error.";
$errorIcon = 'fa-shield-alt';
$showLoginButton = false;
$showLogoutButton = true;
include __DIR__ . '/error_template.php';
