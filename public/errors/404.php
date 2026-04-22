<?php
/**
 * 404 Not Found Error Page
 */
http_response_code(404);
$errorCode = 404;
$errorTitle = 'Page Not Found';
$errorMessage = 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.';
$errorIcon = 'fa-search';
$showLoginButton = false;
include __DIR__ . '/error_template.php';
