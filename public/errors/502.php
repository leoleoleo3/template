<?php
/**
 * 502 Bad Gateway Error Page
 */
http_response_code(502);
$errorCode = 502;
$errorTitle = 'Bad Gateway';
$errorMessage = 'The server received an invalid response from an upstream server. Please try again later.';
$errorIcon = 'fa-server';
$showLoginButton = false;
include __DIR__ . '/error_template.php';
