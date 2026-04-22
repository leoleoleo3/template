<?php
/**
 * Maintenance Mode Page
 */
http_response_code(503);
$errorCode = '';
$errorTitle = 'Under Maintenance';
$errorMessage = 'We are currently performing scheduled maintenance. Please check back soon. We apologize for any inconvenience.';
$errorIcon = 'fa-wrench';
$showLoginButton = false;
$isMaintenance = true;
include __DIR__ . '/error_template.php';
