<?php
/**
 * 503 Service Unavailable Error Page
 */
http_response_code(503);
$errorCode = 503;
$errorTitle = 'Service Unavailable';
$errorMessage = 'The server is currently unable to handle the request. This may be due to maintenance or temporary overloading. Please try again later.';
$errorIcon = 'fa-tools';
$showLoginButton = false;
include __DIR__ . '/error_template.php';
