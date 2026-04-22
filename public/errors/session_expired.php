<?php
/**
 * Session Expired Page
 */
http_response_code(401);
$errorCode = '';
$errorTitle = 'Session Expired';
$errorMessage = 'Your session has expired due to inactivity. Please log in again to continue.';
$errorIcon = 'fa-clock';
$showLoginButton = true;
include __DIR__ . '/error_template.php';
