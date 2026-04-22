<?php
/**
 * 401 Unauthorized Error Page
 */
http_response_code(401);
$errorCode = 401;
$errorTitle = 'Unauthorized';
$errorMessage = 'You need to log in to access this page.';
$errorIcon = 'fa-lock';
$showLoginButton = true;
include __DIR__ . '/error_template.php';
