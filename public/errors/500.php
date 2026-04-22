<?php
/**
 * 500 Internal Server Error Page
 */
http_response_code(500);
$errorCode = 500;
$errorTitle = 'Internal Server Error';
$errorMessage = 'Something went wrong on our end. Please try again later or contact the administrator if the problem persists.';
$errorIcon = 'fa-exclamation-triangle';
$showLoginButton = false;
include __DIR__ . '/error_template.php';
