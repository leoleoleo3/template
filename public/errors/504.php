<?php
/**
 * 504 Gateway Timeout Error Page
 */
http_response_code(504);
$errorCode = 504;
$errorTitle = 'Gateway Timeout';
$errorMessage = 'The server took too long to respond. Please try again later.';
$errorIcon = 'fa-hourglass-half';
$showLoginButton = false;
include __DIR__ . '/error_template.php';
