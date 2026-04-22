<?php
/**
 * 400 Bad Request Error Page
 */
http_response_code(400);
$errorCode = 400;
$errorTitle = 'Bad Request';
$errorMessage = 'The server could not understand your request. Please check your input and try again.';
$errorIcon = 'fa-exclamation-circle';
$showLoginButton = false;
include __DIR__ . '/error_template.php';
